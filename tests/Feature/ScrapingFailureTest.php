<?php

namespace Tests\Feature;

use App\Jobs\SyncJdihRegulations;
use App\Models\JdihTarget;
use App\Models\Regulation;
use App\Models\RegulationType;
use App\Models\Sector;
use App\Models\User;
use App\Services\ScrapingFailureSummary;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ScrapingFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('scraper');
        config()->set('database.connections.jdih', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'scraper_root' => Storage::disk('scraper')->path(''),
        ]);
        DB::purge('jdih');
        Schema::connection('jdih')->create('regulations', function (Blueprint $table): void {
            $table->string('source');
            $table->string('document_id');
            $table->string('title');
            $table->string('local_path')->nullable();
            $table->string('status')->default('downloaded');
        });
    }

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
            'folder' => ['Folder scraper tidak tersedia atau tidak dapat dibaca.', 'izin akses worker'],
            'metadata' => ['[review:metadata_missing]', 'Lengkapi metadata'],
            'type' => ['[review:type_unknown]', 'Jenis dokumen belum dikenali'],
            'schema' => ['no such table: regulations', 'Struktur database JDIH'],
            'missing' => ['[fail:file_hilang] PDF sumber tidak ditemukan', 'diunduh kembali'],
            'connection' => ['Connection refused', 'Koneksi ke database'],
            'timeout' => ['TimeoutExceededException', 'batas waktu'],
            'storage' => ['Permission denied', 'izin folder'],
            'data' => ['SQLSTATE constraint violation', 'Data dokumen'],
            'unknown' => ['Unexpected failure', 'Dokumen yang sudah masuk tetap tersimpan'],
        ];
    }

    public function test_history_shows_jdih_source_total_and_identifies_failed_files(): void
    {
        $this->createFailure('Batch', 'JDIH sync failed (source=komdigi, exit=1, failed=1): [fail:file_hilang] komdigi/doc-1 : PDF sumber tidak ditemukan | Failed : 1');
        DB::connection('jdih')->table('regulations')->insert([
            ['source' => 'komdigi', 'document_id' => 'doc-1', 'title' => 'Regulasi Gagal Uji', 'local_path' => 'missing.pdf', 'status' => 'downloaded'],
            ['source' => 'komdigi', 'document_id' => 'doc-2', 'title' => 'Regulasi Pending', 'local_path' => 'pending.pdf', 'status' => 'pending'],
            ['source' => 'other', 'document_id' => 'doc-1', 'title' => 'Sumber Lain', 'local_path' => 'other.pdf', 'status' => 'failed'],
        ]);
        foreach (['admin', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('scraping-failures.index'))->assertOk()
                ->assertSee('Total database JDIH (sumber ini): 2')
                ->assertSee('Regulasi Gagal Uji')->assertSee('missing.pdf')->assertSee('Gagal tercatat')
                ->assertSee('File tidak ada di folder sumber')->assertDontSee('Sumber Lain');
        }
    }

    public function test_unprocessed_files_are_distinguished_from_recorded_failures_and_escaped(): void
    {
        $this->createFailure('Batch', '[fail] komdigi/doc-1 : Gagal menyalin PDF | Failed : 1');
        $failure = DB::table('failed_jobs')->first();
        $payload = json_decode($failure->payload, true);
        $payload['data']['command'] = serialize(new SyncJdihRegulations('komdigi', 0, ['doc-1', 'doc-2']));
        DB::table('failed_jobs')->where('id', $failure->id)->update(['payload' => json_encode($payload)]);
        DB::connection('jdih')->table('regulations')->insert([
            ['source' => 'komdigi', 'document_id' => 'doc-1', 'title' => '<script>bad()</script>', 'local_path' => 'exists.pdf'],
            ['source' => 'komdigi', 'document_id' => 'doc-2', 'title' => 'Belum Diproses', 'local_path' => 'second.pdf'],
        ]);
        Storage::disk('scraper')->put('exists.pdf', '%PDF');
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('scraping-failures.index'))->assertOk()
            ->assertSee('File tersedia di folder sumber')->assertSee('Gagal tercatat')
            ->assertSee('Belum masuk; kegagalan per file tidak tercatat')
            ->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false);
    }

    public function test_all_source_history_counts_all_jdih_regulations(): void
    {
        $this->createFailure('Planner', 'Error');
        $failure = DB::table('failed_jobs')->first();
        $payload = json_decode($failure->payload, true);
        $payload['data']['command'] = serialize(new SyncJdihRegulations);
        DB::table('failed_jobs')->update(['payload' => json_encode($payload)]);
        DB::connection('jdih')->table('regulations')->insert([
            ['source' => 'komdigi', 'document_id' => 'doc-1', 'title' => 'One'],
            ['source' => 'other', 'document_id' => 'doc-2', 'title' => 'Two'],
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('scraping-failures.index'))->assertOk()->assertSee('Total database JDIH (semua sumber): 2')
            ->assertSee('Daftar file gagal tidak tersimpan');
    }

    public function test_jdih_failure_does_not_prevent_viewing_history_and_document_ids(): void
    {
        $this->createFailure('Batch', '[fail] komdigi/doc-1 : Gagal menyalin PDF');
        Schema::connection('jdih')->drop('regulations');
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('scraping-failures.index'))->assertOk()
            ->assertSee('Database JDIH belum dapat diakses')->assertSee('Total database JDIH (sumber ini): Tidak tersedia')
            ->assertSee('komdigi / doc-1')->assertSee('Nama file tidak tersedia');
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
