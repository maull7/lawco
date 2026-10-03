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

    private function createScraperDocument(string $content): string
    {
        Storage::disk('scraper')->put('source.pdf', $content);
        $checksum = hash('sha256', $content);

        DB::connection('jdih')->table('regulations')->insert([
            'source' => 'jdih_komdigi',
            'document_id' => 'document-1',
            'category' => '',
            'year' => '2026',
            'title' => 'Peraturan Tahun 2026',
            'number' => '1',
            'date' => '2026-01-01',
            'regulation_type' => 'peraturan',
            'checksum' => $checksum,
            'local_path' => 'source.pdf',
            'status' => 'downloaded',
            'bytes' => strlen($content),
            'first_seen_at' => now(),
        ]);

        return 'regulations/'.$checksum.'.pdf';
    }
}
