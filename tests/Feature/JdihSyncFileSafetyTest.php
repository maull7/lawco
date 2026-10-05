<?php

namespace Tests\Feature;

use App\Jobs\SyncJdihRegulations;
use App\Models\JdihTarget;
use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\Sector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
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

        (new SyncJdihRegulations('jdih_komdigi'))->handle();

        $this->assertSame($content, Storage::disk('public')->get($path));
        $this->assertFalse(Storage::disk('scraper')->exists('source.pdf'));
        $this->assertDatabaseHas('jdih_sync_log', ['jdih_document_id' => 'document-1']);
    }

    public function test_sync_preserves_target_sector_when_category_is_missing_or_belongs_to_another_sector(): void
    {
        $target = JdihTarget::query()->where('source', 'jdih_komdigi')->firstOrFail();
        $otherSector = Sector::create(['name' => 'Energi']);
        RegulationCategory::create(['name' => 'Peraturan', 'sector_id' => $otherSector->id]);
        $this->createScraperDocument("%PDF-1.4\nSector regulation\n%%EOF");
        DB::connection('jdih')->table('regulations')->update(['category' => 'Peraturan']);

        $this->assertSame(0, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));

        $regulation = Regulation::query()->sole();
        $this->assertSame($target->sector_id, $regulation->sector_id);
        $this->assertNull($regulation->category_id);
        $this->assertTrue($regulation->sector->is($target->sector));
    }

    public function test_sync_matches_category_only_within_target_sector(): void
    {
        $target = JdihTarget::query()->where('source', 'jdih_komdigi')->firstOrFail();
        $otherSector = Sector::create(['name' => 'Energi']);
        RegulationCategory::create(['name' => 'Peraturan', 'sector_id' => $otherSector->id]);
        $category = RegulationCategory::create(['name' => 'Peraturan', 'sector_id' => $target->sector_id]);
        $this->createScraperDocument("%PDF-1.4\nMatching category\n%%EOF");
        DB::connection('jdih')->table('regulations')->update(['category' => ' peraturan ']);

        $this->assertSame(0, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));

        $regulation = Regulation::query()->sole();
        $this->assertSame($target->sector_id, $regulation->sector_id);
        $this->assertSame($category->id, $regulation->category_id);
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

        $this->assertMatchesRegularExpression('/Pending\s*:\s*0/', Artisan::output());
        $this->assertSame(1, Regulation::query()->count());
    }

    public function test_unknown_document_type_is_reported_for_review_without_removing_source(): void
    {
        $this->createScraperDocument("%PDF-1.4\nUnknown document\n%%EOF");
        DB::connection('jdih')->table('regulations')->update([
            'regulation_type' => 'needs_review',
            'title' => 'Dokumen Langka Pekerjaan Umum Nomor 10281',
        ]);

        $this->assertSame(0, Artisan::call('jdih:sync', ['--source' => 'jdih_komdigi']));
        $output = Artisan::output();
        $this->assertMatchesRegularExpression('/Needs review\s*:\s*1/', $output);
        $this->assertMatchesRegularExpression('/Failed\s*:\s*0/', $output);
        $this->assertMatchesRegularExpression('/Pending\s*:\s*0/', $output);
        $this->assertSame(0, Regulation::query()->count());
        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));

        (new SyncJdihRegulations('jdih_komdigi'))->handle();
        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));
        $this->assertDatabaseCount('jdih_sync_log', 0);
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

        (new SyncJdihRegulations)->handle();

        $this->assertSame(2, Regulation::query()->count());
        $this->assertDatabaseHas('jdih_sync_log', ['jdih_source' => 'jdih_pu', 'jdih_document_id' => 'document-1']);
        $this->assertDatabaseHas('jdih_sync_log', ['jdih_source' => 'jdih_pkp', 'jdih_document_id' => 'document-2']);
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
        (new SyncJdihRegulations('jdih_komdigi'))->handle();
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

        (new SyncJdihRegulations)->handle();

        $this->assertSame(1, Regulation::query()->count());
        $this->assertTrue(Storage::disk('scraper')->exists('source.pdf'));
        $this->assertFalse(Storage::disk('scraper')->exists('second.pdf'));
        $this->assertDatabaseMissing('jdih_sync_log', ['jdih_source' => 'jdih_pu']);
        $this->assertDatabaseHas('jdih_sync_log', ['jdih_source' => 'jdih_komdigi']);
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
