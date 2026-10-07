<?php

namespace Tests\Feature;

use App\Jobs\SyncJdihRegulations;
use App\Models\JdihTarget;
use App\Models\Regulation;
use App\Models\RegulationType;
use App\Models\Sector;
use App\Models\User;
use App\Services\ScrapingFailureSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ScrapingFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_sub_admin_can_view_only_jdih_sync_failures(): void
    {
        $this->createFailure('JDIH sync (komdigi, 2 documents)', 'PDF gagal disalin');
        $this->createFailure('OtherJob', 'Error antrean lain', 'default');
        $this->createFailure('OtherJob', 'Error pekerjaan lain', 'jdih', 'App\\Jobs\\OtherJob');

        foreach (['admin', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('scraping-failures.index'))
                ->assertOk()
                ->assertSee('Riwayat Scraping Gagal')
                ->assertSee('JDIH sync (komdigi, 2 documents)')
                ->assertSee('PDF gagal disalin')
                ->assertDontSee('Error antrean lain')
                ->assertDontSee('Error pekerjaan lain');
        }
    }

    public function test_regular_user_is_forbidden_and_guest_is_redirected(): void
    {
        $this->get(route('scraping-failures.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('scraping-failures.index'))->assertForbidden();
    }

    public function test_empty_history_is_displayed(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('scraping-failures.index'))
            ->assertOk()
            ->assertSee('Belum ada riwayat sinkronisasi scraping yang gagal.');
    }

    public function test_history_is_paginated_newest_first_and_escapes_errors(): void
    {
        for ($index = 0; $index < 21; $index++) {
            $this->createFailure('Batch '.$index, 'Error '.$index);
        }
        DB::table('failed_jobs')->where('id', 21)->update(['exception' => '<script>alert(1)</script>']);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('scraping-failures.index'))
            ->assertOk()
            ->assertSeeInOrder(['Batch 20', 'Batch 19'])
            ->assertDontSee('Batch 0')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('scraping-failures.index', ['page' => 2]))
            ->assertOk()->assertSee('Batch 0')->assertDontSee('Batch 20');
    }

    public function test_malformed_payload_has_a_safe_fallback(): void
    {
        $this->createFailure('Batch', 'Error');
        DB::table('failed_jobs')->update(['payload' => 'broken SyncJdihRegulations payload']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('scraping-failures.index'))->assertOk()->assertSee('Sinkronisasi JDIH');
    }

    public function test_admin_and_sub_admin_can_retry_the_original_job_once(): void
    {
        $queue = Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->shouldReceive('pushRaw')->twice()->withArgs(function (string $raw, string $queueName, array $options): bool {
            $payload = json_decode($raw, true);
            $job = unserialize($payload['data']['command'], ['allowed_classes' => [SyncJdihRegulations::class]]);

            return $queueName === 'jdih' && $payload['attempts'] === 0
                && $payload['retryUntil'] > now()->timestamp
                && $job->source === 'komdigi' && $job->documentIds === null && $job->fromFolder;
        })->andReturn('queued-id');
        Queue::shouldReceive('connection')->with('redis')->andReturn($queue);
        foreach (['admin', 'sub_admin'] as $role) {
            $this->createFailure('JDIH sync', 'Error');
            $uuid = DB::table('failed_jobs')->value('uuid');

            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post(route('scraping-failures.retry', $uuid))
                ->assertRedirect(route('scraping-failures.index'))->assertSessionHas('success');
            $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
            $this->post(route('scraping-failures.retry', $uuid))->assertSessionHas('error');
        }
    }

    public function test_retry_failure_keeps_the_history(): void
    {
        $this->createFailure('JDIH sync', 'Error');
        $uuid = DB::table('failed_jobs')->value('uuid');
        Queue::shouldReceive('connection')->with('redis')->andThrow(new RuntimeException('Queue unavailable'));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('scraping-failures.retry', $uuid))->assertSessionHas('error');
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid]);
    }

    public function test_retry_rejects_other_jobs_invalid_payload_and_unauthorized_users(): void
    {
        $this->createFailure('Other', 'Error', 'jdih', 'App\\Jobs\\OtherJob');
        $uuid = DB::table('failed_jobs')->value('uuid');
        Queue::shouldReceive('connection')->never();
        $this->post(route('scraping-failures.retry', $uuid))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->post(route('scraping-failures.retry', $uuid))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('scraping-failures.retry', $uuid))->assertSessionHas('error');
        DB::table('failed_jobs')->update(['payload' => 'invalid']);
        $this->post(route('scraping-failures.retry', $uuid))->assertSessionHas('error');
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid]);
    }

    public function test_sector_and_search_filters_work_together_and_keep_pagination(): void
    {
        $sector = Sector::create(['name' => 'Komunikasi Digital']);
        JdihTarget::create(['name' => 'Sumber Komdigi Uji', 'source' => 'komdigi', 'sector_id' => $sector->id]);
        $otherSector = Sector::create(['name' => 'Sektor Lain']);
        for ($index = 0; $index < 21; $index++) {
            $this->createFailure('Batch '.$index, 'Timeout exception');
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('scraping-failures.index', ['q' => 'Digital', 'sector_id' => $sector->id]))
            ->assertOk()->assertSee('Sumber Komdigi Uji')->assertSee('Sektor: Komunikasi Digital')
            ->assertSee('21 proses gagal ditemukan')->assertSee('q=Digital')->assertSee('sector_id='.$sector->id);
        $this->get(route('scraping-failures.index', ['q' => 'batas waktu', 'sector_id' => $sector->id]))
            ->assertOk()->assertSee('21 proses gagal ditemukan');
        $this->get(route('scraping-failures.index', ['sector_id' => $otherSector->id]))
            ->assertOk()->assertSee('0 proses gagal ditemukan');
        $this->get(route('scraping-failures.index', ['q' => 'tidak-ditemukan']))
            ->assertOk()->assertSee('0 proses gagal ditemukan');
        $this->get(route('scraping-failures.index', ['q' => '%']))
            ->assertOk()->assertSee('0 proses gagal ditemukan');
    }

    public function test_counts_use_synced_document_identity_and_do_not_count_other_sources(): void
    {
        $this->createFailure('Batch', 'RuntimeException: JDIH sync failed (source=komdigi, exit=1, failed=1)');
        $failure = DB::table('failed_jobs')->first();
        $payload = json_decode($failure->payload, true);
        $payload['data']['command'] = serialize(new SyncJdihRegulations('komdigi', 0, ['doc-1', 'doc-2']));
        DB::table('failed_jobs')->where('id', $failure->id)->update(['payload' => json_encode($payload)]);
        $regulation = Regulation::create([
            'title' => 'Dokumen Uji', 'regulation_number' => '1', 'year' => 2026,
            'regulation_type_id' => RegulationType::create(['name' => 'Jenis Uji', 'level' => 1])->id,
            'file_path' => 'regulations/test.pdf',
        ]);
        foreach ([['komdigi', 'doc-1'], ['other', 'doc-2']] as [$source, $document]) {
            DB::table('jdih_sync_log')->insert([
                'jdih_source' => $source, 'jdih_document_id' => $document,
                'lawco_regulation_id' => $regulation->id, 'file_path' => $regulation->file_path,
            ]);
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('scraping-failures.index'))->assertOk()
            ->assertSee('Total proses: 2')->assertSee('Sudah masuk saat ini: 1')
            ->assertSee('Gagal saat percobaan: 1')->assertSee('Belum masuk: 1');
    }

    public function test_invalid_filters_are_rejected_and_unknown_counts_are_not_invented(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson(route('scraping-failures.index', ['sector_id' => 999999, 'q' => str_repeat('a', 201)]))
            ->assertUnprocessable()->assertJsonValidationErrors(['sector_id', 'q']);
        $this->createFailure('Unknown Batch', 'Interrupted');
        DB::table('failed_jobs')->update(['payload' => 'broken SyncJdihRegulations']);
        $this->get(route('scraping-failures.index'))->assertOk()
            ->assertSee('Sudah masuk saat ini: Tidak tercatat')->assertSee('Gagal saat percobaan: Tidak tercatat');
    }

    #[DataProvider('humanErrors')]
    public function test_errors_are_explained_in_plain_language(string $error, string $expected): void
    {
        $this->assertStringContainsString($expected, (new ScrapingFailureSummary)->humanError($error));
    }

    /** @return array<string, array{string, string}> */
    public static function humanErrors(): array
    {
        return [
            'missing' => ['[fail:file_hilang] PDF sumber tidak ditemukan', 'diunduh kembali'],
            'connection' => ['Connection refused', 'Koneksi ke database'],
            'timeout' => ['TimeoutExceededException', 'batas waktu'],
            'storage' => ['Permission denied', 'izin folder'],
            'data' => ['SQLSTATE constraint violation', 'Data dokumen'],
            'unknown' => ['Unexpected failure', 'Dokumen yang sudah masuk tetap tersimpan'],
        ];
    }

    private function createFailure(string $name, string $error, string $queue = 'jdih', string $jobClass = SyncJdihRegulations::class): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => $queue,
            'payload' => json_encode(['displayName' => $name, 'data' => ['commandName' => $jobClass, 'command' => serialize($jobClass === SyncJdihRegulations::class ? new SyncJdihRegulations('komdigi', 0, ['doc-1']) : new \stdClass)], 'attempts' => 3, 'retryUntil' => 1]),
            'exception' => $error,
            'failed_at' => '2026-10-07 10:00:00',
        ]);
    }
}
