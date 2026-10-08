<?php

namespace Tests\Feature;

use App\Jobs\SyncJdihRegulations;
use App\Models\JdihDocumentReview;
use App\Models\JdihTarget;
use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use App\Models\User;
use App\Services\ScrapingFailureSummary;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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
            $table->string('regulation_type')->nullable();
            $table->string('category')->default('');
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

    public function test_unmapped_types_and_review_documents_are_visible_without_failed_jobs(): void
    {
        DB::connection('jdih')->table('regulations')->insert([
            ['source' => 'komdigi', 'document_id' => 'manual-1', 'title' => 'Surat Menteri Pengujian', 'regulation_type' => 'surat_menteri', 'local_path' => 'manual.pdf'],
            ['source' => 'komdigi', 'document_id' => 'manual-2', 'title' => 'Dokumen Tidak Dikenali', 'regulation_type' => 'needs_review', 'local_path' => 'review.pdf'],
            ['source' => 'komdigi', 'document_id' => 'recognized', 'title' => 'Peraturan Menteri Pengujian', 'regulation_type' => 'peraturan_menteri', 'local_path' => 'known.pdf'],
        ]);
        foreach (['manual.pdf', 'review.pdf', 'known.pdf'] as $path) {
            Storage::disk('scraper')->put($path, '%PDF');
        }
        foreach (['admin', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('scraping-failures.index'))->assertOk()
                ->assertSee('1 dokumen tersedia di folder sumber')
                ->assertSee('surat_menteri')->assertSee('Surat Menteri')
                ->assertSee('Tambahkan jenis Surat Menteri')
                ->assertSee('manual.pdf')->assertDontSee('review.pdf')->assertDontSee('known.pdf');
            $this->get(route('scraping-failures.index', ['tab' => 'needs_review']))->assertOk()
                ->assertSee('1 dokumen tersedia di folder sumber')->assertSee('review.pdf')
                ->assertSee('jenis berdasarkan isi PDF')->assertDontSee('manual.pdf')->assertDontSee('known.pdf');
        }
    }

    public function test_review_list_respects_sector_search_and_pagination(): void
    {
        $sector = Sector::create(['name' => 'Sektor Manual']);
        JdihTarget::create(['name' => 'Sumber Manual', 'source' => 'manual_source', 'sector_id' => $sector->id]);
        RegulationType::create(['name' => 'Surat Menteri', 'level' => 4]);
        for ($index = 1; $index <= 21; $index++) {
            DB::connection('jdih')->table('regulations')->insert([
                'source' => 'manual_source', 'document_id' => sprintf('%02d', $index),
                'title' => 'Surat Menteri Uji '.$index, 'regulation_type' => 'surat_menteri', 'local_path' => 'manual-'.$index.'.pdf',
            ]);
            Storage::disk('scraper')->put('manual-'.$index.'.pdf', '%PDF');
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('scraping-failures.index', ['sector_id' => $sector->id]))->assertOk()
            ->assertSee('21 dokumen tersedia di folder sumber')->assertSee('Jenis ini sudah ada di Lawco')
            ->assertDontSee('manual-21.pdf');
        $this->get(route('scraping-failures.index', ['review_page' => 2, 'sector_id' => $sector->id]))
            ->assertOk()->assertSee('manual-21.pdf')->assertDontSee('manual-1.pdf');
        $this->get(route('scraping-failures.index', ['q' => 'tidak-ada']))->assertOk()
            ->assertSee('0 dokumen tersedia di folder sumber');
    }

    public function test_missing_category_is_listed_with_the_correct_sector_and_manual_action(): void
    {
        $sector = Sector::create(['name' => 'Sektor Tujuan']);
        JdihTarget::create(['name' => 'Sumber Kategori', 'source' => 'category_source', 'sector_id' => $sector->id]);
        DB::connection('jdih')->table('regulations')->insert([
            'source' => 'category_source', 'document_id' => 'cat-1', 'title' => 'Peraturan Menteri Uji',
            'regulation_type' => 'peraturan_menteri', 'category' => 'Kategori Kurang', 'local_path' => 'category.pdf',
        ]);
        Storage::disk('scraper')->put('category.pdf', '%PDF');
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('scraping-failures.index'))->assertOk()->assertSee('Kategori kurang: Kategori Kurang')
            ->assertSee('Pilih kategori Kategori Kurang di menu pemeriksaan');
        RegulationCategory::create(['name' => 'Kategori Kurang', 'sector_id' => $sector->id]);
        $this->get(route('scraping-failures.index'))->assertOk()->assertDontSee('category.pdf');
    }

    public function test_review_tab_shows_source_links_and_pdf_for_admin_and_sub_admin(): void
    {
        $sector = Sector::factory()->create(['name' => 'Sektor Penyelidikan']);
        JdihTarget::create(['name' => 'Sumber Penyelidikan', 'source' => 'investigate', 'sector_id' => $sector->id, 'target_url' => 'https://jdih.example.test/documents?a=1&b=2']);
        DB::connection('jdih')->table('regulations')->insert([
            'source' => 'investigate', 'document_id' => 'unknown-1', 'title' => 'Dokumen Penyelidikan',
            'regulation_type' => 'needs_review', 'local_path' => 'unknown.pdf',
        ]);
        Storage::disk('scraper')->put('unknown.pdf', '%PDF-1.4');
        foreach (['admin', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('scraping-failures.index', ['tab' => 'needs_review']))->assertOk()
                ->assertSee('Sumber Penyelidikan')->assertSee('Sektor Penyelidikan')
                ->assertSee('Kode sumber: investigate')->assertSee('Website Sumber JDIH')->assertSee('Lihat PDF')
                ->assertSee('https://jdih.example.test/documents?a=1&amp;b=2', false)
                ->assertDontSee('amp;amp;', false)->assertDontSee('Tambahkan Semua Kategori dan Jenis yang Belum Ada');
            $this->get(route('scraping-failures.document', ['source' => 'investigate', 'document_id' => 'unknown-1']))
                ->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_review_file_rejects_guests_users_invalid_identity_and_paths_outside_scraper(): void
    {
        $url = route('scraping-failures.document', ['source' => 'test', 'document_id' => 'one']);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'user']))->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get($url)->assertNotFound();
        $this->getJson(route('scraping-failures.document'))->assertUnprocessable()->assertJsonValidationErrors(['source', 'document_id']);
        DB::connection('jdih')->table('regulations')->insert([
            'source' => 'test', 'document_id' => 'one', 'title' => 'Invalid Path', 'local_path' => base_path('composer.json'),
        ]);
        $this->get($url)->assertNotFound();
        Storage::fake('local');
        Storage::disk('local')->put('outside.pdf', '%PDF-1.4');
        DB::connection('jdih')->table('regulations')->update(['local_path' => Storage::disk('local')->path('outside.pdf')]);
        $this->get($url)->assertNotFound();
        DB::connection('jdih')->table('regulations')->update(['local_path' => 'missing.pdf']);
        $this->get($url)->assertNotFound();
        Storage::disk('scraper')->put('text.txt', 'Text');
        DB::connection('jdih')->table('regulations')->update(['local_path' => 'text.txt']);
        $this->get($url)->assertNotFound();
    }

    public function test_bulk_creation_includes_all_pages_and_preserves_existing_types_and_sector_categories(): void
    {
        $sector = Sector::factory()->create();
        $other = Sector::factory()->create();
        JdihTarget::create(['name' => 'Bulk Source', 'source' => 'bulk', 'sector_id' => $sector->id]);
        JdihTarget::create(['name' => 'Other Source', 'source' => 'other', 'sector_id' => $other->id]);
        RegulationType::factory()->create(['name' => 'SURAT MENTERI', 'level' => 2]);
        RegulationCategory::factory()->create(['name' => 'Kategori 1', 'sector_id' => $other->id]);
        for ($index = 1; $index <= 21; $index++) {
            DB::connection('jdih')->table('regulations')->insert([
                'source' => 'bulk', 'document_id' => 'bulk-'.$index, 'title' => 'Dokumen Massal '.$index,
                'regulation_type' => $index === 1 ? 'surat_menteri' : 'perjanjian_kerjasama',
                'category' => 'Kategori '.$index, 'local_path' => 'bulk.pdf',
            ]);
        }
        DB::connection('jdih')->table('regulations')->insert([
            ['source' => 'other', 'document_id' => 'other-1', 'title' => 'Dokumen Lain', 'regulation_type' => 'jenis_lain', 'category' => 'Tidak Dibuat', 'local_path' => 'bulk.pdf'],
            ['source' => 'bulk', 'document_id' => 'unknown', 'title' => 'Belum Diketahui', 'regulation_type' => 'needs_review', 'category' => '', 'local_path' => 'bulk.pdf'],
            ['source' => 'bulk', 'document_id' => 'blank', 'title' => 'Belum Diketahui', 'regulation_type' => null, 'category' => '', 'local_path' => 'bulk.pdf'],
        ]);
        Storage::disk('scraper')->put('bulk.pdf', '%PDF');
        foreach (['admin', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'permissions' => ['manage_types', 'manage_categories']]))
                ->post(route('scraping-failures.create-masters'), ['sector_id' => $sector->id, 'q' => 'Dokumen Massal'])
                ->assertRedirect(route('scraping-failures.index', ['q' => 'Dokumen Massal', 'sector_id' => $sector->id]))->assertSessionHas('success');
        }
        $this->assertDatabaseHas('regulation_types', ['name' => 'SURAT MENTERI', 'level' => 2]);
        $this->assertDatabaseHas('regulation_types', ['name' => 'Perjanjian Kerja Sama', 'level' => 4]);
        $this->assertSame(2, RegulationType::count());
        $this->assertSame(20, RegulationCategory::where('sector_id', $sector->id)->count());
        $this->assertDatabaseHas('regulation_categories', ['name' => 'Kategori 21', 'sector_id' => $sector->id]);
        $this->assertDatabaseMissing('regulation_categories', ['name' => 'Tidak Dibuat']);
        $this->post(route('scraping-failures.create-masters'), ['sector_id' => $sector->id])->assertSessionHas('success');
        $this->assertSame(2, RegulationType::count());
    }

    public function test_bulk_creation_requires_management_permissions_and_valid_filters(): void
    {
        $url = route('scraping-failures.create-masters');
        $this->post($url)->assertRedirect(route('login'));
        foreach (['user', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->post($url)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson($url, ['sector_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('sector_id');
        $this->getJson(route('scraping-failures.index', ['tab' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('tab');
    }

    public function test_bulk_creation_handles_unavailable_jdih_and_missing_sector_without_partial_writes(): void
    {
        DB::connection('jdih')->table('regulations')->insert([
            'source' => 'unmapped', 'document_id' => 'one', 'title' => 'Surat Menteri Uji',
            'regulation_type' => 'surat_menteri', 'category' => 'Kategori Tanpa Sektor', 'local_path' => 'one.pdf',
        ]);
        config()->set('database.connections.jdih.default_sector_id', 999999);
        Storage::disk('scraper')->put('one.pdf', '%PDF');
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('scraping-failures.create-masters'))->assertSessionHas('success');
        $this->assertDatabaseHas('regulation_types', ['name' => 'Surat Menteri', 'level' => 4]);
        $this->assertDatabaseMissing('regulation_categories', ['name' => 'Kategori Tanpa Sektor']);
        Schema::connection('jdih')->drop('regulations');
        $this->post(route('scraping-failures.create-masters'))->assertSessionHas('error');
        $this->assertSame(1, RegulationType::count());
    }

    public function test_review_tab_preserves_filters_and_pagination_for_blank_and_needs_review_types(): void
    {
        $sector = Sector::factory()->create();
        JdihTarget::create(['name' => 'Review Pagination', 'source' => 'review', 'sector_id' => $sector->id]);
        Storage::disk('scraper')->put('review.pdf', '%PDF');
        for ($index = 1; $index <= 21; $index++) {
            DB::connection('jdih')->table('regulations')->insert([
                'source' => 'review', 'document_id' => sprintf('%02d', $index), 'title' => 'Penyelidikan '.$index,
                'regulation_type' => $index === 1 ? '' : 'needs_review', 'local_path' => 'review.pdf',
            ]);
        }
        $this->actingAs(User::factory()->create(['role' => 'sub_admin']))
            ->get(route('scraping-failures.index', ['tab' => 'needs_review', 'sector_id' => $sector->id, 'q' => 'Penyelidikan']))
            ->assertOk()->assertSee('21 dokumen tersedia di folder sumber')->assertSee('Penyelidikan 1')
            ->assertDontSee('Penyelidikan 21')->assertSee('tab=needs_review')->assertSee('q=Penyelidikan');
        $this->get(route('scraping-failures.index', ['tab' => 'needs_review', 'review_page' => 2]))
            ->assertOk()->assertSee('Penyelidikan 21')->assertDontSee('Penyelidikan 1');
    }

    public function test_manual_sync_form_only_lists_active_sources_and_is_disabled_without_them(): void
    {
        JdihTarget::query()->update(['is_active' => false]);
        $target = JdihTarget::factory()->create(['name' => 'Sumber Aktif Manual']);
        JdihTarget::factory()->create(['name' => 'Sumber Nonaktif Manual', 'is_active' => false]);
        $this->actingAs(User::factory()->create(['role' => 'sub_admin']))
            ->get(route('scraping-failures.index'))->assertOk()->assertSee('Jalankan Sinkronisasi Sekarang')
            ->assertSee('Sumber Aktif Manual')->assertDontSee('Sumber Nonaktif Manual')->assertSee('Semua sumber aktif (1)');
        $target->update(['is_active' => false]);
        $this->get(route('scraping-failures.index'))->assertOk()->assertSee('Semua sumber aktif (0)')
            ->assertSee('Belum ada sumber JDIH aktif. Aktifkan sumber di menu Target JDIH terlebih dahulu.');
    }

    public function test_review_query_does_not_scan_known_types_with_categories(): void
    {
        $rows = [];
        for ($index = 0; $index < 1001; $index++) {
            $rows[] = [
                'source' => 'large-source', 'document_id' => 'known-'.$index,
                'title' => 'Peraturan Menteri '.$index, 'regulation_type' => 'peraturan_menteri',
                'category' => 'Kategori Belum Ada', 'local_path' => 'known.pdf',
            ];
        }
        $rows[] = [
            'source' => 'large-source', 'document_id' => 'unknown', 'title' => 'Dokumen Penyelidikan',
            'regulation_type' => 'needs_review', 'category' => '', 'local_path' => 'review.pdf',
        ];
        DB::connection('jdih')->table('regulations')->insert($rows);
        Storage::disk('scraper')->put('known.pdf', '%PDF');
        Storage::disk('scraper')->put('review.pdf', '%PDF');
        DB::connection('jdih')->enableQueryLog();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('scraping-failures.index', ['tab' => 'needs_review']))->assertOk()
            ->assertSee('1 dokumen tersedia di folder sumber')->assertSee('review.pdf')->assertDontSee('known.pdf');
        $queries = DB::connection('jdih')->getQueryLog();
        DB::connection('jdih')->disableQueryLog();
        $this->assertCount(1, $queries, 'Tab pemeriksaan tidak boleh membaca chunk tambahan untuk jenis lain.');
        $this->assertStringNotContainsString('category !=', $queries[0]['query']);
    }

    public function test_review_query_preserves_blank_case_insensitive_types_and_excludes_resolved_synced_and_missing_files(): void
    {
        $rows = [];
        foreach ([null, '', '  ', '  NeEdS_ReViEw  '] as $index => $slug) {
            $rows[] = ['source' => 'review', 'document_id' => 'unknown-'.$index, 'title' => 'Dokumen Penyelidikan '.$index,
                'regulation_type' => $slug, 'local_path' => 'review.pdf'];
        }
        $rows[] = ['source' => 'review', 'document_id' => 'resolved', 'title' => 'Peraturan Menteri Uji',
            'regulation_type' => 'needs_review', 'local_path' => 'resolved.pdf'];
        $rows[] = ['source' => 'review', 'document_id' => 'missing', 'title' => 'PDF Tidak Ada',
            'regulation_type' => 'needs_review', 'local_path' => 'missing.pdf'];
        $rows[] = ['source' => 'review', 'document_id' => 'synced', 'title' => 'Dokumen Sudah Diimpor',
            'regulation_type' => 'needs_review', 'local_path' => 'synced.pdf'];
        DB::connection('jdih')->table('regulations')->insert($rows);
        foreach (['review.pdf', 'resolved.pdf', 'synced.pdf'] as $path) {
            Storage::disk('scraper')->put($path, '%PDF');
        }
        $type = RegulationType::factory()->create();
        $regulation = Regulation::create([
            'title' => 'Dokumen Sudah Diimpor', 'regulation_number' => '1', 'year' => 2026,
            'regulation_type_id' => $type->id, 'file_path' => 'regulations/synced.pdf',
        ]);
        DB::table('jdih_sync_log')->insert([
            'jdih_source' => 'review', 'jdih_document_id' => 'synced',
            'lawco_regulation_id' => $regulation->id, 'file_path' => $regulation->file_path,
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('scraping-failures.index', ['tab' => 'needs_review']))->assertOk()
            ->assertSee('5 dokumen tersedia di folder sumber')->assertSee('Dokumen Penyelidikan 0')
            ->assertSee('Dokumen Penyelidikan 3')->assertSee('resolved.pdf')->assertDontSee('missing.pdf')
            ->assertDontSee('synced.pdf');
    }

    public function test_document_review_menu_and_actions_are_admin_only(): void
    {
        $this->get(route('jdih-document-reviews.index'))->assertRedirect(route('login'));
        $this->post(route('jdih-document-reviews.store'))->assertRedirect(route('login'));
        foreach (['user', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'permissions' => ['manage_types', 'manage_categories']]))
                ->get(route('jdih-document-reviews.index'))->assertForbidden();
            $this->post(route('jdih-document-reviews.store'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('jdih-document-reviews.index'))->assertOk()->assertSee('Pemeriksaan Jenis JDIH');
    }

    public function test_admin_can_choose_type_and_import_only_the_selected_document(): void
    {
        DB::connection('jdih')->table('regulations')->insert([
            'source' => 'test-review', 'document_id' => 'doc-1', 'title' => 'Dokumen Uji',
            'regulation_type' => 'needs_review', 'local_path' => 'review.pdf',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $type = RegulationType::factory()->create(['name' => 'Keputusan Kepala']);
        Queue::fake();
        $data = ['source' => 'test-review', 'document_id' => 'doc-1', 'type_mode' => 'existing', 'regulation_type_id' => $type->id, 'action' => 'import'];
        $this->actingAs($admin)->post(route('jdih-document-reviews.store'), $data)
            ->assertRedirect(route('jdih-document-reviews.index'))->assertSessionHas('success');
        $this->post(route('jdih-document-reviews.store'), $data)->assertSessionHas('success');
        $this->assertDatabaseHas('jdih_document_reviews', ['source' => 'test-review', 'document_id' => 'doc-1', 'regulation_type_id' => $type->id, 'reviewed_by' => $admin->id]);
        $this->assertSame(1, JdihDocumentReview::count());
        Queue::assertPushed(SyncJdihRegulations::class, 1);
        Queue::assertPushed(SyncJdihRegulations::class, fn (SyncJdihRegulations $job): bool => $job->source === 'test-review' && $job->documentIds === ['doc-1'] && $job->fromFolder);
    }

    public function test_admin_can_create_type_with_default_level_without_duplicate_names(): void
    {
        DB::connection('jdih')->table('regulations')->insert([
            'source' => 'test-review', 'document_id' => 'doc-1', 'title' => 'Dokumen Uji', 'regulation_type' => 'needs_review',
        ]);
        Queue::fake();
        $data = ['source' => 'test-review', 'document_id' => 'doc-1', 'type_mode' => 'new', 'new_type_name' => ' Keputusan Kepala ', 'action' => 'save'];
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('jdih-document-reviews.store'), $data)->assertSessionHas('success');
        $data['new_type_name'] = 'keputusan kepala';
        $data['level'] = 2;
        $this->post(route('jdih-document-reviews.store'), $data)->assertSessionHas('success');
        $this->assertDatabaseHas('regulation_types', ['name' => 'Keputusan Kepala', 'level' => 4]);
        $this->assertSame(1, RegulationType::count());
        $this->assertSame(1, JdihDocumentReview::count());
        Queue::assertNothingPushed();
    }

    public function test_review_rejects_invalid_document_inactive_type_and_invalid_level(): void
    {
        $type = RegulationType::factory()->create();
        Queue::fake();
        $data = ['source' => 'test', 'document_id' => 'missing', 'type_mode' => 'existing', 'regulation_type_id' => $type->id, 'action' => 'import'];
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('jdih-document-reviews.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('document_id');
        DB::connection('jdih')->table('regulations')->insert(['source' => 'test', 'document_id' => 'missing', 'title' => 'Uji', 'regulation_type' => 'needs_review']);
        $type->update(['is_active' => false]);
        $this->postJson(route('jdih-document-reviews.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('regulation_type_id');
        $data['type_mode'] = 'new';
        $data['new_type_name'] = 'Jenis Baru';
        $data['level'] = 6;
        $this->postJson(route('jdih-document-reviews.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('level');
        $this->assertSame(0, JdihDocumentReview::count());
        Queue::assertNothingPushed();
    }

    public function test_review_queue_failure_keeps_the_saved_choice(): void
    {
        DB::connection('jdih')->table('regulations')->insert(['source' => 'test', 'document_id' => 'one', 'title' => 'Uji', 'regulation_type' => 'needs_review']);
        $type = RegulationType::factory()->create();
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('jdih-document-reviews.store'), ['source' => 'test', 'document_id' => 'one', 'type_mode' => 'existing', 'regulation_type_id' => $type->id, 'action' => 'import'])
            ->assertSessionHas('error');
        $this->assertDatabaseHas('jdih_document_reviews', ['source' => 'test', 'document_id' => 'one', 'regulation_type_id' => $type->id]);
    }

    public function test_document_review_menu_keeps_its_own_pagination_and_displays_saved_types(): void
    {
        $type = RegulationType::factory()->create(['name' => 'Jenis Pilihan Admin']);
        Storage::disk('scraper')->put('review.pdf', '%PDF');
        for ($index = 1; $index <= 21; $index++) {
            DB::connection('jdih')->table('regulations')->insert(['source' => 'review', 'document_id' => sprintf('%02d', $index), 'title' => 'Penyelidikan '.$index, 'regulation_type' => 'needs_review', 'local_path' => 'review.pdf']);
        }
        JdihDocumentReview::factory()->create(['source' => 'review', 'document_id' => '01', 'regulation_type_id' => $type->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('jdih-document-reviews.index', ['q' => 'Penyelidikan']))->assertOk()
            ->assertSee('Pilihan tersimpan: Jenis Pilihan Admin')->assertSee('Simpan Pilihan')->assertSee('Tambahkan jenis baru')
            ->assertSee('jdih-document-reviews?q=Penyelidikan')->assertDontSee('Penyelidikan 21');
        $this->get(route('jdih-document-reviews.index', ['review_page' => 2]))->assertOk()->assertSee('Penyelidikan 21')->assertDontSee('Penyelidikan 1');
    }

    public function test_admin_can_choose_any_category_and_invalid_categories_are_rejected(): void
    {
        DB::connection('jdih')->table('regulations')->insert(['source' => 'test-review', 'document_id' => 'one', 'title' => 'Dokumen Uji', 'regulation_type' => 'needs_review', 'category' => 'Peraturan Daerah', 'local_path' => 'review.pdf']);
        $type = RegulationType::factory()->create();
        $category = RegulationCategory::factory()->create(['name' => 'Peraturan Daerah']);
        Storage::disk('scraper')->put('review.pdf', '%PDF');
        $data = ['source' => 'test-review', 'document_id' => 'one', 'type_mode' => 'existing', 'regulation_type_id' => $type->id, 'category_id' => $category->id, 'action' => 'save'];
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('jdih-document-reviews.store'), $data)->assertSessionHas('success');
        $this->assertDatabaseHas('jdih_document_reviews', ['source' => 'test-review', 'document_id' => 'one', 'category_id' => $category->id]);
        $this->get(route('jdih-document-reviews.index'))->assertOk()->assertSee('Kategori (lintas sektor)')->assertSee('Peraturan Daerah');
        $data['category_id'] = 999999;
        $this->postJson(route('jdih-document-reviews.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('category_id');
    }

    public function test_review_listing_accepts_duplicate_category_names_from_different_sectors(): void
    {
        $category = RegulationCategory::factory()->create(['name' => 'Kategori Global']);
        DB::connection('jdih')->table('regulations')->insert(['source' => 'unmapped', 'document_id' => 'one', 'title' => 'Peraturan Menteri Uji', 'regulation_type' => 'peraturan_menteri', 'category' => 'kategori global', 'local_path' => 'global.pdf']);
        Storage::disk('scraper')->put('global.pdf', '%PDF');
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('scraping-failures.index'))->assertOk()->assertDontSee('global.pdf');
        RegulationCategory::factory()->create(['name' => 'KATEGORI GLOBAL']);
        $this->get(route('jdih-document-reviews.index'))->assertOk()->assertDontSee('global.pdf');
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
