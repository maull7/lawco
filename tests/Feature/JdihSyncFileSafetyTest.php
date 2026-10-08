<?php

namespace Tests\Feature;

use App\Jobs\SyncJdihRegulations;
use App\Models\JdihDocumentReview;
use App\Models\JdihTarget;
use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class JdihSyncFileSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('scraper');

        $sector = Sector::create(['name' => 'Komunikasi']);
        JdihTarget::query()->where('source', 'jdih_komdigi')->update(['sector_id' => $sector->id]);

        config()->set('database.connections.jdih', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'scraper_root' => Storage::disk('scraper')->path(''),
            'cut_source_files' => true,
            'default_sector_id' => $sector->id,
            'sector_by_source' => ['jdih_komdigi' => 0],
        ]);
        DB::purge('jdih');

        Schema::connection('jdih')->create('regulations', function (Blueprint $table): void {
            $table->string('source');
            $table->string('document_id');
            $table->string('category');
            $table->string('year');
            $table->string('title');
            $table->string('number');
            $table->string('date');
            $table->string('regulation_type');
            $table->string('checksum');
            $table->string('local_path');
            $table->string('status');
            $table->unsignedBigInteger('bytes');
            $table->timestamp('first_seen_at');
        });
    }

    public function test_partial_destination_is_replaced_before_source_is_removed(): void
    {
        $content = "%PDF-1.4\nComplete regulation PDF\n%%EOF";
        $path = $this->createScraperDocument($content);
        Storage::disk('public')->put($path, 'incomplete');

        $this->assertSame(0, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));

        $this->assertSame($content, Storage::disk('public')->get($path));
        $this->assertFalse(Storage::disk('scraper')->exists('source.pdf'));
        $this->assertDatabaseHas('jdih_sync_log', [
            'jdih_source' => 'jdih_komdigi',
            'jdih_document_id' => 'document-1',
            'file_path' => $path,
        ]);
    }

    public function test_source_is_preserved_if_existing_synced_copy_no_longer_matches(): void
    {
        $content = "%PDF-1.4\nComplete regulation PDF\n%%EOF";
        $path = $this->createScraperDocument($content);
        config()->set('database.connections.jdih.cut_source_files', false);

        $this->assertSame(0, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));
        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));

        Storage::disk('public')->put($path, 'incomplete');
        config()->set('database.connections.jdih.cut_source_files', true);

        $this->assertSame(0, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));
        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));
        $this->assertSame('incomplete', Storage::disk('public')->get($path));
    }

    public function test_queued_job_runs_sync_for_its_source(): void
    {
        $content = "%PDF-1.4\nQueued regulation PDF\n%%EOF";
        $path = $this->createScraperDocument($content);

        $this->runQueuedSync('jdih_komdigi');

        $this->assertSame($content, Storage::disk('public')->get($path));
        $this->assertFalse(Storage::disk('scraper')->exists('source.pdf'));
        $this->assertDatabaseHas('jdih_sync_log', ['jdih_document_id' => 'document-1']);
    }

    public function test_sync_reuses_a_category_from_another_sector_and_keeps_the_regulation_sector(): void
    {
        $target = JdihTarget::query()->where('source', 'jdih_komdigi')->firstOrFail();
        $otherSector = Sector::factory()->create();
        $category = RegulationCategory::factory()->create(['name' => 'Peraturan', 'sector_id' => $otherSector->id]);
        $this->createScraperDocument("%PDF-1.4\nCross sector category\n%%EOF");
        DB::connection('jdih')->table('regulations')->update(['category' => ' peraturan ']);
        $this->assertSame(0, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));
        $regulation = Regulation::query()->sole();
        $this->assertSame($target->sector_id, $regulation->sector_id);
        $this->assertSame($category->id, $regulation->category_id);
        $this->assertDatabaseCount('regulation_categories', 1);
    }

    public function test_duplicate_category_names_require_a_choice_and_manual_choice_can_cross_sectors(): void
    {
        $target = JdihTarget::query()->where('source', 'jdih_komdigi')->firstOrFail();
        $otherSector = Sector::factory()->create();
        $category = RegulationCategory::factory()->create(['name' => 'Peraturan', 'sector_id' => $otherSector->id]);
        RegulationCategory::factory()->create(['name' => 'PERATURAN', 'sector_id' => $target->sector_id]);
        $this->createScraperDocument("%PDF-1.4\nAmbiguous category\n%%EOF");
        DB::connection('jdih')->table('regulations')->update(['category' => ' peraturan ']);
        $this->assertSame(1, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));
        $this->assertStringContainsString('[fail:category_unknown]', Artisan::output());
        $this->assertDatabaseCount('regulations', 0);
        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));
        JdihDocumentReview::factory()->create(['source' => 'jdih_komdigi', 'document_id' => 'document-1', 'category_id' => $category->id]);
        $this->assertSame(0, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));
        $regulation = Regulation::query()->sole();
        $this->assertSame($target->sector_id, $regulation->sector_id);
        $this->assertSame($category->id, $regulation->category_id);
    }

    public function test_a_genuinely_missing_category_still_preserves_the_source_for_review(): void
    {
        $this->createScraperDocument("%PDF-1.4\nMissing category\n%%EOF");
        DB::connection('jdih')->table('regulations')->update(['category' => 'Kategori Tidak Ada']);
        $this->assertSame(1, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));
        $this->assertDatabaseCount('regulations', 0);
        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));
    }

    #[DataProvider('additionalDocumentTypes')]
    public function test_sync_imports_additional_document_types(string $slug, string $title, string $expectedType): void
    {
        $this->createScraperDocument("%PDF-1.4\nAdditional document type\n%%EOF");
        DB::connection('jdih')->table('regulations')->update([
            'regulation_type' => $slug,
            'title' => $title,
        ]);

        $this->assertSame(0, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));
        $this->assertSame($expectedType, Regulation::query()->sole()->type->name);
    }

    public static function additionalDocumentTypes(): array
    {
        return [
            'presidential instruction slug' => ['instruksi_presiden', 'Instruksi Presiden Nomor 1', 'Instruksi Presiden'],
            'presidential instruction title' => ['needs_review', 'Instruksi Presiden Nomor 1', 'Instruksi Presiden'],
            'joint regulation' => ['peraturan_bersama', 'Peraturan Bersama Menteri Nomor 1', 'Peraturan Bersama'],
            'joint decision' => ['keputusan_bersama', 'Keputusan Bersama Menteri Nomor 1', 'Keputusan Bersama'],
            'legal research' => ['penelitian_hukum', 'Kajian atau Penelitian Hukum', 'Kajian atau Penelitian Hukum'],
            'technical guide decision' => ['juklak_juknis', 'Surat Keputusan Nomor 231 tentang Petunjuk Teknis', 'Keputusan'],
            'technical guide' => ['juklak_juknis', 'Petunjuk Teknis Nomor 1', 'Pedoman'],
            'decision title' => ['needs_review', 'Surat Keputusan Nomor 32', 'Keputusan'],
            'senate regulation' => ['needs_review', 'Peraturan Senat/Peraturan Senat Akademik Nomor 61', 'Peraturan Senat'],
            'perppu slug' => ['perppu', 'PERPPU Nomor 1', 'Peraturan Pemerintah Pengganti Undang-Undang'],
            'perppu title' => ['needs_review', 'Peraturan Pemerintah Pengganti Undang-Undang Nomor 1', 'Peraturan Pemerintah Pengganti Undang-Undang'],
            'presidential decision' => ['keputusan_presiden', 'KEPPRES Nomor 1', 'Keputusan Presiden'],
            'secretary regulation' => ['peraturan_sekretaris_jenderal', 'PERSEKJEN Nomor 1', 'Peraturan Sekretaris Jenderal'],
            'secretary decision' => ['keputusan_sekretaris_jenderal', 'KEPSEKJEN Nomor 1', 'Keputusan Sekretaris Jenderal'],
            'inspector regulation' => ['peraturan_inspektur_jenderal', 'PERIRJEN Nomor 1', 'Peraturan Inspektur Jenderal'],
            'inspector decision' => ['keputusan_inspektur_jenderal', 'KEPIRJEN Nomor 1', 'Keputusan Inspektur Jenderal'],
            'director instruction' => ['instruksi_dirjen', 'INSTRUKSI Nomor 1', 'Instruksi Direktur Jenderal'],
            'eselon decision' => ['keputusan_eselon_i', 'KEPUTUSAN Nomor 1', 'Keputusan Eselon I'],
            'agency regulation' => ['peraturan_badan', 'PERBAN Nomor 1', 'Peraturan Badan'],
            'agency decision' => ['keputusan_badan', 'KEPBAN Nomor 1', 'Keputusan Badan'],
            'board regulation' => ['peraturan_direksi', 'PERDIR Nomor 1', 'Peraturan Direksi'],
            'board decision' => ['keputusan_direksi', 'KEPDIR Nomor 1', 'Keputusan Direksi'],
            'archival type' => ['statuten', 'STATUTEN', 'Statuten'],
            'display label' => ['Peraturan Menteri', 'PM Nomor 1', 'Peraturan Menteri'],
            'ministerial decision label' => ['needs_review', 'Surat Keputusan Menteri Nomor 1', 'Keputusan Menteri'],
            'decision referencing law' => ['needs_review', 'Surat Keputusan Nomor 1 tentang Pelaksanaan Undang-Undang Nomor 2', 'Keputusan'],
            'constitution' => ['needs_review', "UNDANG\u{00AD}-UNDANG DASAR NEGARA REPUBLIK INDONESIA", 'Undang-Undang Dasar'],
        ];
    }

    public function test_repeat_sync_does_not_report_negative_pending_when_source_was_cut(): void
    {
        $this->createScraperDocument("%PDF-1.4\nRepeated document\n%%EOF");
        $this->assertSame(0, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));
        $this->assertSame(0, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));

        $output = Artisan::output();
        $this->assertMatchesRegularExpression('/Pending\s*:\s*0/', $output);
        $this->assertMatchesRegularExpression('/Missing PDF\s*:\s*0/', $output);
        $this->assertSame(1, Regulation::query()->count());
    }

    public function test_retry_skips_cut_successes_but_reports_unsynced_missing_files_as_failed(): void
    {
        $this->createScraperDocument("%PDF-1.4\nImported\n%%EOF");
        $this->assertSame(0, Artisan::call('jdih:sync'));
        $this->createScraperDocument("%PDF-1.4\nMissing\n%%EOF", 'missing.pdf', 'document-2');
        Storage::disk('scraper')->delete('missing.pdf');

        $this->assertSame(1, Artisan::call('jdih:sync', [
            '--source' => 'jdih_komdigi', '--document' => ['document-1', 'document-2'], '--only-pending' => true,
        ]));
        $output = Artisan::output();
        $this->assertMatchesRegularExpression('/Already synced\s*:\s*1/', $output);
        $this->assertMatchesRegularExpression('/Missing PDF\s*:\s*1/', $output);
        $this->assertMatchesRegularExpression('/Failed\s*:\s*1/', $output);
        $this->assertMatchesRegularExpression('/Pending\s*:\s*0/', $output);
        $this->assertDatabaseCount('regulations', 1);
        $this->expectException(RuntimeException::class);
        (new SyncJdihRegulations('jdih_komdigi', 0, ['document-1', 'document-2']))->handle();
    }

    public function test_retry_imports_remaining_files_after_successful_sources_have_been_cut(): void
    {
        $this->createScraperDocument("%PDF-1.4\nFirst\n%%EOF");
        $this->createScraperDocument("%PDF-1.4\nSecond\n%%EOF", 'second.pdf', 'document-2');
        $this->assertSame(0, Artisan::call('jdih:sync', ['--limit' => 1]));
        $this->assertFalse(Storage::disk('scraper')->exists('source.pdf'));
        $this->assertTrue(Storage::disk('scraper')->exists('second.pdf'));

        (new SyncJdihRegulations('jdih_komdigi', 0, ['document-1', 'document-2']))->handle();
        $this->assertDatabaseCount('regulations', 2);
        $this->assertDatabaseCount('jdih_sync_log', 2);
        $this->assertFalse(Storage::disk('scraper')->exists('second.pdf'));
        (new SyncJdihRegulations('jdih_komdigi', 0, ['document-1', 'document-2']))->handle();
        $this->assertDatabaseCount('regulations', 2);
    }

    public function test_folder_retry_only_queues_available_unsynced_pdfs_for_the_selected_source(): void
    {
        $this->createScraperDocument("%PDF-1.4\nAlready imported\n%%EOF");
        $this->assertSame(0, Artisan::call('jdih:sync'));
        $this->createScraperDocument("%PDF-1.4\nRemaining\n%%EOF", 'remaining.pdf', 'document-2');
        DB::connection('jdih')->table('regulations')->where('document_id', 'document-2')->update(['status' => 'pending']);
        $this->createScraperDocument("%PDF-1.4\nMissing\n%%EOF", 'missing.pdf', 'document-3');
        Storage::disk('scraper')->delete('missing.pdf');
        $this->createScraperDocument("%PDF-1.4\nOther source\n%%EOF", 'other.pdf', 'document-4');
        DB::connection('jdih')->table('regulations')->where('document_id', 'document-4')->update(['source' => 'other']);
        Storage::disk('scraper')->put('orphan.pdf', '%PDF orphan without metadata');
        Queue::fake();

        (new SyncJdihRegulations('jdih_komdigi', 0, null, true))->handle();
        Queue::assertPushed(SyncJdihRegulations::class, 1);
        $job = Queue::pushed(SyncJdihRegulations::class)->first();
        $this->assertSame(['document-2'], $job->documentIds);
        $job->handle();
        $this->assertDatabaseCount('regulations', 2);
        $this->assertDatabaseHas('jdih_sync_log', ['jdih_document_id' => 'document-2']);
        $this->assertTrue(Storage::disk('scraper')->exists('other.pdf'));
        $this->assertTrue(Storage::disk('scraper')->exists('orphan.pdf'));
        $this->assertFalse(Storage::disk('scraper')->exists('remaining.pdf'));
    }

    public function test_folder_mode_ignores_missing_database_candidates_and_reports_unmatched_files(): void
    {
        $this->createScraperDocument("%PDF-1.4\nMissing\n%%EOF");
        Storage::disk('scraper')->delete('source.pdf');
        Storage::disk('scraper')->put('orphan.pdf', '%PDF orphan');
        $this->assertSame(0, Artisan::call('jdih:sync', ['--from-folder' => true, '--source' => 'jdih_komdigi']));
        $output = Artisan::output();
        $this->assertStringContainsString('[review:metadata_missing]', $output);
        $this->assertMatchesRegularExpression('/Missing PDF\s*:\s*0/', $output);
        $this->assertMatchesRegularExpression('/Pending\s*:\s*0/', $output);
        $this->assertDatabaseCount('regulations', 0);
    }

    public function test_empty_folder_completes_without_reading_jdih_database_or_dispatching_jobs(): void
    {
        config()->set('database.connections.jdih.driver', 'unsupported-empty-folder-test');
        DB::purge('jdih');
        Queue::fake();
        $this->assertSame(0, Artisan::call('jdih:sync', ['--from-folder' => true, '--queue' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('[done:no_files]', $output);
        $this->assertStringContainsString('Folder PDFs : 0', $output);
        (new SyncJdihRegulations('jdih_komdigi', 0, null, true))->handle();
        Queue::assertNothingPushed();
    }

    public function test_folder_retry_fails_if_the_folder_cannot_be_read(): void
    {
        config()->set('database.connections.jdih.scraper_root', '/tmp/lawco-nonexistent-scraper-folder');
        $this->assertSame(1, Artisan::call('jdih:sync', ['--from-folder' => true]));
        $this->assertStringContainsString('Folder scraper tidak tersedia', Artisan::output());
    }

    public function test_limited_sync_moves_past_synced_and_missing_documents(): void
    {
        $this->createScraperDocument("%PDF-1.4\nFirst document\n%%EOF");
        $this->assertSame(0, Artisan::call('jdih:sync'));
        $this->createScraperDocument("%PDF-1.4\nMissing document\n%%EOF", 'missing.pdf', 'document-2');
        Storage::disk('scraper')->delete('missing.pdf');
        $this->createScraperDocument("%PDF-1.4\nNext document\n%%EOF", 'next.pdf', 'document-3');
        $this->createScraperDocument("%PDF-1.4\nLater document\n%%EOF", 'later.pdf', 'document-4');

        $this->assertSame(0, Artisan::call('jdih:sync', ['--limit' => 1, '--only-pending' => true]));
        $this->assertDatabaseHas('jdih_sync_log', ['jdih_document_id' => 'document-3']);
        $this->assertDatabaseMissing('jdih_sync_log', ['jdih_document_id' => 'document-4']);
        $this->assertMatchesRegularExpression('/Pending\s*:\s*1/', Artisan::output());

        $this->assertSame(0, Artisan::call('jdih:sync', ['--limit' => 1, '--only-pending' => true]));
        $this->assertDatabaseHas('jdih_sync_log', ['jdih_document_id' => 'document-4']);
        $this->assertMatchesRegularExpression('/Pending\s*:\s*0/', Artisan::output());
    }

    public function test_pending_queue_sync_preserves_old_source_and_imports_new_document(): void
    {
        $this->createScraperDocument("%PDF-1.4\nOld document\n%%EOF");
        config()->set('database.connections.jdih.cut_source_files', false);
        $this->assertSame(0, Artisan::call('jdih:sync'));
        config()->set('database.connections.jdih.cut_source_files', true);
        $this->createScraperDocument("%PDF-1.4\nNew document\n%%EOF", 'new.pdf', 'document-2');

        $this->runQueuedSync('jdih_komdigi', 1);

        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));
        $this->assertFalse(Storage::disk('scraper')->exists('new.pdf'));
        $this->assertDatabaseCount('jdih_sync_log', 2);
    }

    public function test_unknown_document_type_is_reported_for_review_without_removing_source(): void
    {
        $this->createScraperDocument("%PDF-1.4\nUnknown document\n%%EOF");
        DB::connection('jdih')->table('regulations')->update([
            'regulation_type' => 'needs_review',
            'title' => 'Dokumen Langka Pekerjaan Umum Nomor 10281',
        ]);

        $this->assertSame(1, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));
        $output = Artisan::output();
        $this->assertMatchesRegularExpression('/Needs review\s*:\s*1/', $output);
        $this->assertMatchesRegularExpression('/Failed\s*:\s*1/', $output);
        $this->assertMatchesRegularExpression('/Pending\s*:\s*0/', $output);
        $this->assertSame(0, Regulation::query()->count());
        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('[fail:type_unknown]');
        $this->runQueuedSync('jdih_komdigi');
    }

    public function test_sync_imports_documents_from_multiple_sources_with_the_shared_mapping(): void
    {
        $this->createScraperDocument("%PDF-1.4\nSource one\n%%EOF");
        DB::connection('jdih')->table('regulations')->update([
            'source' => 'jdih_pu',
            'regulation_type' => 'instruksi_presiden',
        ]);
        $this->createScraperDocument("%PDF-1.4\nSource two\n%%EOF", 'second.pdf', 'document-2');
        DB::connection('jdih')->table('regulations')->where('document_id', 'document-2')->update([
            'source' => 'jdih_pkp',
            'regulation_type' => 'keputusan_bersama',
        ]);

        $this->runQueuedSync();

        $this->assertSame(2, Regulation::query()->count());
        $this->assertDatabaseHas('jdih_sync_log', ['jdih_source' => 'jdih_pu', 'jdih_document_id' => 'document-1']);
        $this->assertDatabaseHas('jdih_sync_log', ['jdih_source' => 'jdih_pkp', 'jdih_document_id' => 'document-2']);
    }

    public function test_queued_failure_preserves_every_failed_document_in_the_history_message(): void
    {
        for ($index = 1; $index <= 5; $index++) {
            $this->createScraperDocument("%PDF-1.4\nFailed document {$index}\n%%EOF", "failed-{$index}.pdf", "document-{$index}");
        }
        DB::unprepared("CREATE TRIGGER reject_regulation BEFORE INSERT ON regulations BEGIN SELECT RAISE(ABORT, 'Import rejected'); END");
        try {
            (new SyncJdihRegulations('jdih_komdigi', 0, ['document-1', 'document-2', 'document-3', 'document-4', 'document-5']))->handle();
            $this->fail('The batch must fail.');
        } catch (RuntimeException $exception) {
            for ($index = 1; $index <= 5; $index++) {
                $this->assertStringContainsString('[fail] jdih_komdigi/document-'.$index, $exception->getMessage());
            }
            $this->assertStringContainsString('failed=5', $exception->getMessage());
        }
        $this->assertDatabaseCount('jdih_sync_log', 0);
    }

    public function test_database_import_failure_preserves_source_and_reports_the_actual_error(): void
    {
        $this->createScraperDocument("%PDF-1.4\nFailed import\n%%EOF");
        DB::unprepared("CREATE TRIGGER reject_regulation BEFORE INSERT ON regulations BEGIN SELECT RAISE(ABORT, 'Import rejected'); END");

        $this->assertSame(1, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));
        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));
        $this->assertDatabaseCount('jdih_sync_log', 0);
        $this->assertSame(0, Regulation::query()->count());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Import rejected');
        $this->runQueuedSync('jdih_komdigi');
    }

    public function test_review_document_does_not_prevent_another_source_from_being_imported(): void
    {
        $this->createScraperDocument("%PDF-1.4\nReview source\n%%EOF");
        DB::connection('jdih')->table('regulations')->update([
            'source' => 'jdih_pu',
            'regulation_type' => 'needs_review',
            'title' => 'Dokumen Langka Pekerjaan Umum',
        ]);
        $this->createScraperDocument("%PDF-1.4\nKnown source\n%%EOF", 'second.pdf', 'document-2');

        $this->assertSame(1, Artisan::call('jdih:sync'));
        $this->assertStringContainsString('[fail:type_unknown]', Artisan::output());

        $this->assertSame(1, Regulation::query()->count());
        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));
        $this->assertFalse(Storage::disk('scraper')->exists('second.pdf'));
        $this->assertDatabaseMissing('jdih_sync_log', ['jdih_source' => 'jdih_pu']);
        $this->assertDatabaseHas('jdih_sync_log', ['jdih_source' => 'jdih_komdigi']);
    }

    public function test_planner_splits_pending_documents_into_bounded_jobs(): void
    {
        Queue::fake();
        config()->set('database.connections.jdih.sync_batch_size', 2);
        for ($index = 1; $index <= 5; $index++) {
            $this->createScraperDocument("%PDF-1.4\nDocument {$index}\n%%EOF", "{$index}.pdf", "document-{$index}");
        }

        (new SyncJdihRegulations('jdih_komdigi'))->handle();

        Queue::assertPushed(SyncJdihRegulations::class, 3);
        $jobs = Queue::pushed(SyncJdihRegulations::class);
        $this->assertSame([2, 2, 1], $jobs->map(static fn (SyncJdihRegulations $job): int => count($job->documentIds))->all());
        $this->assertCount(5, $jobs->flatMap(static fn (SyncJdihRegulations $job): array => $job->documentIds)->unique());

        foreach ($jobs as $job) {
            $job->handle();
        }
        $this->assertDatabaseCount('jdih_sync_log', 5);
        $this->assertDatabaseCount('regulations', 5);
        foreach ($jobs as $job) {
            $job->handle();
        }
        $this->assertDatabaseCount('regulations', 5);
        (new SyncJdihRegulations('jdih_komdigi'))->handle();
        Queue::assertPushed(SyncJdihRegulations::class, 3);
    }

    public function test_failed_batch_does_not_prevent_later_batch_from_importing(): void
    {
        Queue::fake();
        config()->set('database.connections.jdih.sync_batch_size', 1);
        $this->createScraperDocument("%PDF-1.4\nRejected\n%%EOF");
        $this->createScraperDocument("%PDF-1.4\nAccepted\n%%EOF", 'second.pdf', 'document-2');
        DB::connection('jdih')->table('regulations')->where('document_id', 'document-1')->update(['number' => 'reject']);
        DB::unprepared("CREATE TRIGGER reject_regulation BEFORE INSERT ON regulations WHEN NEW.regulation_number = 'reject' BEGIN SELECT RAISE(ABORT, 'Import rejected'); END");
        (new SyncJdihRegulations('jdih_komdigi'))->handle();
        $jobs = Queue::pushed(SyncJdihRegulations::class);
        $this->assertCount(2, $jobs);
        try {
            $jobs[0]->handle();
            $this->fail('The rejected batch must report its error.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Import rejected', $exception->getMessage());
        }
        $jobs[1]->handle();

        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));
        $this->assertDatabaseMissing('jdih_sync_log', ['jdih_document_id' => 'document-1']);
        $this->assertDatabaseHas('jdih_sync_log', ['jdih_document_id' => 'document-2']);
    }

    public function test_batch_lock_is_shared_and_unique_ids_identify_document_sets(): void
    {
        $first = new SyncJdihRegulations('jdih_komdigi', 0, ['document-1']);
        $second = new SyncJdihRegulations('jdih_komdigi', 0, ['document-2']);
        $repeat = new SyncJdihRegulations('jdih_komdigi', 0, ['document-1']);
        $this->assertSame($first->uniqueId(), $repeat->uniqueId());
        $this->assertNotSame($first->uniqueId(), $second->uniqueId());
        $this->assertSame($first->middleware()[0]->getLockKey($first), $second->middleware()[0]->getLockKey($second));
        $this->assertSame('redis', $first->connection);
    }

    public function test_busy_sync_lock_delays_another_batch_without_running_it(): void
    {
        $first = new SyncJdihRegulations('jdih_komdigi', 0, ['document-1']);
        $second = new SyncJdihRegulations('jdih_kemenhub', 0, ['document-2']);
        $second->withFakeQueueInteractions();
        $lock = Cache::lock($first->middleware()[0]->getLockKey($first), 1200);
        $this->assertTrue($lock->get());
        try {
            $second->middleware()[0]->handle($second, function (): void {
                $this->fail('Another sync batch must wait while the lock is held.');
            });
            $second->assertReleased(30);
        } finally {
            $lock->release();
        }
    }

    public function test_identical_pdf_in_different_batches_is_not_imported_twice(): void
    {
        config()->set('database.connections.jdih.sync_batch_size', 1);
        $content = "%PDF-1.4\nShared content\n%%EOF";
        $this->createScraperDocument($content);
        $this->createScraperDocument($content, 'second.pdf', 'document-2');

        $this->runQueuedSync('jdih_komdigi');

        Queue::assertPushed(SyncJdihRegulations::class, 2);
        $this->assertDatabaseCount('regulations', 1);
        $this->assertDatabaseCount('jdih_sync_log', 1);
        $this->assertFalse(Storage::disk('scraper')->exists('source.pdf'));
        $this->assertFalse(Storage::disk('scraper')->exists('second.pdf'));
    }

    public function test_empty_batch_cannot_import_all_documents(): void
    {
        $this->createScraperDocument("%PDF-1.4\nUntouched\n%%EOF");
        (new SyncJdihRegulations('jdih_komdigi', 0, []))->handle();
        $this->assertDatabaseCount('regulations', 0);
        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));
    }

    public function test_failed_batch_logs_documents_paths_and_exception_for_debugging(): void
    {
        $path = $this->createScraperDocument("%PDF-1.4\nLog failed import\n%%EOF");
        DB::unprepared("CREATE TRIGGER reject_regulation BEFORE INSERT ON regulations BEGIN SELECT RAISE(ABORT, 'Import rejected'); END");
        $job = new SyncJdihRegulations('jdih_komdigi', 0, ['document-1']);
        $logger = \Mockery::spy(LoggerInterface::class);
        Log::shouldReceive('channel')->with('single')->andReturn($logger);

        try {
            $job->handle();
            $this->fail('The import failure must be reported.');
        } catch (RuntimeException $exception) {
            $job->failed($exception);
        }

        $logger->shouldHaveReceived('error')->with('Gagal menyinkronkan regulasi.', \Mockery::on(
            fn (array $context): bool => $context['source'] === 'jdih_komdigi'
                && $context['document_id'] === 'document-1'
                && $context['source_path'] === Storage::disk('scraper')->path('source.pdf')
                && $context['destination_path'] === $path
                && str_contains($context['exception']->getMessage(), 'Import rejected'),
        ))->once();
        $logger->shouldHaveReceived('error')->with('Queued JDIH sync completed with errors.', \Mockery::on(
            fn (array $context): bool => $context['batch_id'] === $job->uniqueId()
                && $context['document_ids'] === ['document-1']
                && $context['failed_count'] === 1
                && isset($context['duration_seconds'])
                && str_contains($context['failures'][0], 'Import rejected'),
        ))->once();
        $logger->shouldHaveReceived('error')->with('JDIH regulation sync queue job failed.', \Mockery::on(
            fn (array $context): bool => $context['batch_id'] === $job->uniqueId()
                && $context['document_ids'] === ['document-1']
                && $context['exception'] instanceof RuntimeException,
        ))->once();
    }

    private function runQueuedSync(?string $source = null, int $limit = 0): void
    {
        Queue::fake();
        (new SyncJdihRegulations($source, $limit))->handle();
        foreach (Queue::pushed(SyncJdihRegulations::class) as $job) {
            $job->handle();
        }
    }

    public function test_manual_document_type_allows_needs_review_to_be_imported_and_is_idempotent(): void
    {
        $path = $this->createScraperDocument("%PDF-1.4\nManual review document\n%%EOF");
        DB::connection('jdih')->table('regulations')->update(['regulation_type' => 'needs_review', 'title' => 'Dokumen Belum Dikenali']);
        $type = RegulationType::factory()->create(['name' => 'Keputusan Kepala', 'level' => 3]);
        JdihDocumentReview::factory()->create(['source' => 'jdih_komdigi', 'document_id' => 'document-1', 'regulation_type_id' => $type->id]);
        $parameters = ['--source' => 'jdih_komdigi', '--document' => ['document-1']];
        $this->assertSame(0, Artisan::call('jdih:sync', $parameters));
        $this->assertDatabaseHas('regulations', ['title' => 'Dokumen Belum Dikenali', 'regulation_type_id' => $type->id]);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertSame(0, Artisan::call('jdih:sync', $parameters));
        $this->assertSame(1, Regulation::count());
    }

    public function test_manual_type_from_another_source_does_not_resolve_this_document(): void
    {
        $this->createScraperDocument("%PDF-1.4\nUnresolved document\n%%EOF");
        DB::connection('jdih')->table('regulations')->update(['regulation_type' => 'needs_review', 'title' => 'Dokumen Belum Dikenali']);
        JdihDocumentReview::factory()->create(['source' => 'other-source', 'document_id' => 'document-1']);
        $this->assertSame(1, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));
        $this->assertSame(0, Regulation::count());
        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));
    }

    public function test_inactive_manual_type_requires_review_instead_of_creating_a_new_type(): void
    {
        $this->createScraperDocument("%PDF-1.4\nInactive manual type\n%%EOF");
        $type = RegulationType::factory()->create(['is_active' => false]);
        JdihDocumentReview::factory()->create(['source' => 'jdih_komdigi', 'document_id' => 'document-1', 'regulation_type_id' => $type->id]);
        $this->assertSame(1, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));
        $this->assertSame(0, Regulation::count());
        $this->assertSame(1, RegulationType::count());
        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));
    }

    private function createScraperDocument(string $content, string $sourcePath = 'source.pdf', string $documentId = 'document-1'): string
    {
        Storage::disk('scraper')->put($sourcePath, $content);
        $checksum = hash('sha256', $content);

        DB::connection('jdih')->table('regulations')->insert([
            'source' => 'jdih_komdigi',
            'document_id' => $documentId,
            'category' => '',
            'year' => '2026',
            'title' => 'Peraturan Tahun 2026',
            'number' => '1',
            'date' => '2026-01-01',
            'regulation_type' => 'peraturan',
            'checksum' => $checksum,
            'local_path' => $sourcePath,
            'status' => 'downloaded',
            'bytes' => strlen($content),
            'first_seen_at' => now(),
        ]);

        return 'regulations/'.$checksum.'.pdf';
    }
}
