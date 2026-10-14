<?php

namespace Tests\Feature;

use App\Jobs\ParseRegulation;
use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use App\Models\User;
use App\Services\AutomaticRegulationParseService;
use App\Services\RegulationParserService;
use Illuminate\Bus\BatchRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AutomaticRegulationParseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::disk('public')->put('regulations/existing.pdf', 'PDF fixture');
        config(['automatic-parse.connection' => 'database']);
    }

    private function regulations(int $count = 1, array $attributes = []): Collection
    {
        $type = RegulationType::factory()->create();
        $category = RegulationCategory::factory()->create();

        return Regulation::factory()->count($count)->create([
            'regulation_type_id' => $type->id, 'category_id' => $category->id,
            'file_path' => 'regulations/existing.pdf', 'parse_status' => 'not_parsed', ...$attributes,
        ]);
    }

    public function test_admin_starts_a_manual_batch_at_the_requested_limit(): void
    {
        $rows = $this->regulations(3);
        $this->regulations(attributes: ['parse_status' => 'complete']);
        $this->regulations(attributes: ['parse_status' => 'parsing']);
        $this->regulations(attributes: ['file_path' => 'missing.pdf']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('regulations.automatic-parse'), ['limit' => 2])
            ->assertRedirect(route('regulations.automatic-parse.settings'))->assertSessionHas('success');
        $batch = app(AutomaticRegulationParseService::class)->activeBatch();
        $this->assertSame(2, $batch->totalJobs);
        $this->assertSame($rows->take(2)->modelKeys(), $batch->options['regulation_ids']);
        $this->assertSame(2, DB::table('jobs')->where('queue', 'parsing-auto')->count());
        $this->assertSame(0, DB::table('jobs')->where('queue', 'parsing')->count());
        $this->get(route('regulations.automatic-parse.settings'))->assertOk()->assertSee('Batch sedang berjalan')->assertSee('Mulai Parse Sekarang');
        $this->get(route('regulations.index'))->assertOk()->assertDontSee('Mulai Parse Sekarang');
    }

    public function test_manual_batch_only_parses_the_selected_sector_and_snapshots_sector_names(): void
    {
        $other = Sector::factory()->create(['name' => 'Energi']);
        $selected = Sector::factory()->create(['name' => 'Keuangan']);
        $this->regulations(2, ['sector_id' => $other->id]);
        $rows = $this->regulations(2, ['sector_id' => $selected->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('regulations.automatic-parse'), ['limit' => 10, 'sector_ids' => [$selected->id]])
            ->assertSessionHas('success');
        $service = app(AutomaticRegulationParseService::class);
        $batch = $service->activeBatch();
        $this->assertSame($rows->modelKeys(), $batch->options['regulation_ids']);
        $this->assertSame($selected->id, $batch->options['sector_id']);
        $selected->update(['name' => 'Nama baru']);
        $history = $service->historyDetail($batch->id);
        $this->assertSame('Keuangan', $history['batch']['sector']);
        $this->assertSame('Keuangan', $history['documents'][0]['sector']);
        $this->get(route('regulations.automatic-parse.history.show', $batch->id))
            ->assertOk()->assertSee('Keuangan')->assertSee('Sektor');
    }

    public function test_invalid_or_inactive_sector_does_not_start_a_batch(): void
    {
        $inactive = Sector::factory()->create(['is_active' => false]);
        $this->regulations();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([$inactive->id, 999999, 'invalid'] as $sectorId) {
            $this->post(route('regulations.automatic-parse'), ['limit' => 10, 'sector_ids' => [$sectorId]])
                ->assertSessionHasErrors('sector_ids.0');
        }
        $this->assertSame(0, DB::table('job_batches')->count());
    }

    public function test_selected_sector_without_candidates_does_not_fall_back_to_other_sectors(): void
    {
        $this->regulations();
        $selected = Sector::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('regulations.automatic-parse'), ['limit' => 10, 'sector_ids' => [$selected->id]])
            ->assertSessionHas('info');
        $this->assertSame(0, DB::table('job_batches')->count());
    }

    #[DataProvider('invalidLimits')]
    public function test_manual_limit_is_validated(mixed $limit): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('regulations.automatic-parse'), ['limit' => $limit])
            ->assertSessionHasErrors('limit');
        $this->assertSame(0, DB::table('job_batches')->count());
    }

    public static function invalidLimits(): array
    {
        return [[0], [3001], [-1], ['invalid'], [null], [2.5]];
    }

    public function test_manual_limit_accepts_three_thousand(): void
    {
        $this->regulations();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('regulations.automatic-parse'), ['limit' => 3000])->assertSessionHas('success');
        $this->assertSame(3000, app(AutomaticRegulationParseService::class)->activeBatch()->options['requested_limit']);
    }

    public function test_three_thousand_documents_create_six_disjoint_batches_of_five_hundred(): void
    {
        $first = Sector::factory()->create(['name' => 'Keuangan']);
        $second = Sector::factory()->create(['name' => 'Energi']);
        $excluded = Sector::factory()->create();
        $this->regulations(1, ['sector_id' => $excluded->id]);
        $firstRows = $this->regulations(1500, ['sector_id' => $first->id]);
        $secondRows = $this->regulations(1501, ['sector_id' => $second->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('regulations.automatic-parse'), ['limit' => 3000, 'sector_ids' => [$first->id, $second->id]])
            ->assertSessionHas('success', 'Parse otomatis dimulai untuk 3000 regulasi dalam 6 batch (maksimal 500 dokumen per batch).');
        $service = app(AutomaticRegulationParseService::class);
        $batches = $service->history()->getCollection()->sortBy('batch_index');
        $this->assertCount(6, $batches);
        $allIds = [];
        foreach ($batches as $batch) {
            $this->assertSame(500, $batch['total']);
            $this->assertSame(6, $batch['batch_count']);
            $this->assertSame('Energi, Keuangan', $batch['sector']);
            $allIds = array_merge($allIds, array_column($service->historyDetail($batch['id'])['documents'], 'id'));
        }
        $this->assertCount(3000, array_unique($allIds));
        $this->assertEqualsCanonicalizing(array_merge($firstRows->modelKeys(), $secondRows->take(1500)->modelKeys()), $allIds);
        $this->assertSame(3000, DB::table('jobs')->count());
        $this->assertSame(3000, $service->summary()['total']);
        $this->assertSame(3000, $service->summary()['pending']);
        $this->assertSame(6, $service->summary()['batches']);
        $this->get(route('regulations.automatic-parse.settings'))->assertOk()->assertSee('Jumlah batch: 6');
    }

    public function test_partial_batch_summary_and_overlap_protection_include_all_batches(): void
    {
        $rows = $this->regulations(501);
        $service = app(AutomaticRegulationParseService::class);
        $firstBatch = $service->start(501);
        $batches = $service->history()->getCollection()->sortBy('batch_index')->values();
        $this->assertSame([500, 1], $batches->pluck('total')->all());
        $this->assertSame($rows->take(500)->modelKeys(), $firstBatch->options['regulation_ids']);
        $lastBatch = Bus::findBatch($batches[1]['id']);
        $service->recordDocumentResult($lastBatch->id, $rows->last()->id, 'failed', 'PDF rusak.');
        DB::table('job_batches')->where('id', $lastBatch->id)->update(['pending_jobs' => 0, 'failed_jobs' => 1, 'finished_at' => now()->timestamp]);
        $summary = $service->summary();
        $this->assertSame(501, $summary['total']);
        $this->assertSame(500, $summary['pending']);
        $this->assertSame(1, $summary['failed']);
        $this->assertTrue($summary['active']);
        $this->expectException(ValidationException::class);
        $service->start(1);
    }

    public function test_manual_multiple_sector_selection_rejects_duplicates_and_non_arrays(): void
    {
        $sector = Sector::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('regulations.automatic-parse'), ['limit' => 3000, 'sector_ids' => [$sector->id, $sector->id]])
            ->assertSessionHasErrors('sector_ids.0');
        $this->post(route('regulations.automatic-parse'), ['limit' => 3000, 'sector_ids' => $sector->id])
            ->assertSessionHasErrors('sector_ids');
        $this->assertSame(0, DB::table('job_batches')->count());
    }

    public function test_non_admin_roles_cannot_start_batches(): void
    {
        foreach (['user', 'reviewer', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post(route('regulations.automatic-parse'), ['limit' => 10])->assertForbidden();
        }
        $this->assertSame(0, DB::table('job_batches')->count());
    }

    public function test_scheduled_batch_is_limited_to_five_hundred_and_cannot_repeat_on_the_same_date(): void
    {
        $this->regulations(501);
        $service = app(AutomaticRegulationParseService::class);
        $batch = $service->start(500, 'scheduled');
        $this->assertSame(500, $batch->totalJobs);
        $this->assertCount(500, $batch->options['regulation_ids']);
        app(BatchRepository::class)->markAsFinished($batch->id);
        $this->assertNull($service->start(500, 'scheduled'));
        $this->assertSame(1, DB::table('job_batches')->count());
    }

    public function test_an_active_batch_blocks_manual_and_scheduled_batches(): void
    {
        $this->regulations(2);
        app(AutomaticRegulationParseService::class)->start(1);
        $this->artisan('regulations:parse-automatic')->assertSuccessful();
        $this->assertSame(1, DB::table('job_batches')->count());
        $this->expectException(ValidationException::class);
        app(AutomaticRegulationParseService::class)->start(1);
    }

    public function test_no_eligible_documents_creates_no_jobs(): void
    {
        $this->regulations(attributes: ['parse_status' => 'complete']);
        $this->assertNull(app(AutomaticRegulationParseService::class)->start(10));
        $this->assertSame(0, DB::table('job_batches')->count());
        $this->artisan('regulations:parse-automatic', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_nightly_schedule_uses_twenty_oclock_jakarta_time(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'regulations-nightly-automatic-parse');
        $this->assertNotNull($event);
        $this->assertSame('0 20 * * *', $event->expression);
        $this->assertSame('Asia/Jakarta', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
        $this->assertSame(1, config('horizon.defaults.parsing-auto.maxProcesses'));
    }

    public function test_worker_resumes_a_long_document_and_finishes_the_batch(): void
    {
        $regulation = $this->regulations()->first();
        $this->fakeParser(6);
        $service = app(AutomaticRegulationParseService::class);
        $batch = $service->start(1);
        $this->work();
        $this->assertSame('parsing', $regulation->fresh()->parse_status);
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(1, $service->summary()['pending']);
        $this->work();
        $regulation->refresh();
        $this->assertSame('complete', $regulation->parse_status);
        $this->assertSame(['1-5', '6-6'], $regulation->parse_stats['processed_chunks']);
        $this->assertSame(6, $regulation->parse_stats['parsed_pages']);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertTrue(Bus::findBatch($batch->id)->finished());
        $this->assertFalse($service->summary()['active']);
        $this->assertSame(100, $service->summary()['percent']);
    }

    public function test_one_failed_document_does_not_stop_the_next_document(): void
    {
        $rows = $this->regulations(2);
        $this->fakeParser(1, $rows->first()->id);
        $service = app(AutomaticRegulationParseService::class);
        $batch = $service->start(2);
        $this->work();
        $this->assertSame('failed', $rows->first()->fresh()->parse_status);
        $this->assertFalse(Bus::findBatch($batch->id)->cancelled());
        $this->work();
        $this->assertSame('complete', $rows->last()->fresh()->parse_status);
        $this->assertSame(1, $service->summary()['failed']);
        $this->assertFalse($service->summary()['active']);
        $failed = $service->summary()['failed_documents'];
        $this->assertSame([$rows->first()->id], array_column($failed, 'id'));
        $this->assertSame('PDF rusak.', $failed[0]['error']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.automatic-parse.settings'))->assertOk()
            ->assertSee('Parse Manual')->assertSee('PDF rusak.')
            ->assertSee(route('regulations.parse', $rows->first()->id))
            ->assertDontSee(route('regulations.parse', $rows->last()->id));

        $rows->first()->update(['parse_status' => 'complete']);
        $this->assertSame([], $service->summary()['failed_documents']);
        $this->assertSame(1, $service->summary()['failed']);
        $this->get(route('regulations.automatic-parse.settings'))->assertOk()->assertDontSee('Parse Manual');
    }

    public function test_manual_parse_cannot_overwrite_a_running_automatic_parse(): void
    {
        $regulation = $this->regulations()->first();
        $this->fakeParser(6);
        app(AutomaticRegulationParseService::class)->start(1);
        $this->work();
        $stats = $regulation->fresh()->parse_stats;
        Bus::fake([ParseRegulation::class]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('regulations.parse', $regulation))->assertSessionHas('info');
        Bus::assertNotDispatched(ParseRegulation::class);
        $this->assertSame($stats, $regulation->fresh()->parse_stats);
    }

    public function test_cancelled_regulation_is_not_restarted_by_the_released_job(): void
    {
        $regulation = $this->regulations()->first();
        $this->fakeParser(6);
        app(AutomaticRegulationParseService::class)->start(1);
        $this->work();
        Cache::put('parse_cancel:regulation:'.$regulation->id, true);
        $this->work();
        $this->assertSame('not_parsed', $regulation->fresh()->parse_status);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_history_shows_immutable_success_results_after_document_changes(): void
    {
        $regulation = $this->regulations()->first();
        $originalTitle = $regulation->title;
        $this->fakeParser(1);
        $service = app(AutomaticRegulationParseService::class);
        $batch = $service->start(1);
        $this->work();
        $history = $service->historyDetail($batch->id);
        $this->assertSame('Berhasil', $history['batch']['status']);
        $this->assertSame('complete', $history['documents'][0]['status']);
        $this->assertSame(100, $history['documents'][0]['percent']);
        $this->assertNotNull($history['documents'][0]['finished_at']);
        $this->assertNotNull($history['documents'][0]['started_at']);
        $regulation->refresh()->update(['title' => 'Judul baru', 'parse_status' => 'failed']);
        $service->recordDocumentResult($batch->id, $regulation->id, 'failed', 'Later error');
        $this->assertSame($originalTitle, $service->historyDetail($batch->id)['documents'][0]['title']);
        $this->assertSame('complete', $service->historyDetail($batch->id)['documents'][0]['status']);
        $this->assertSame(1, $service->summary()['complete']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.automatic-parse.history'))->assertOk()->assertSee('Berhasil');
        $this->get(route('regulations.automatic-parse.history.show', $batch->id))
            ->assertOk()->assertSee($originalTitle)->assertSee('100%')->assertDontSee('Judul baru');
    }

    public function test_summary_shows_parsed_and_remaining_counts_per_sector(): void
    {
        $energy = Sector::factory()->create(['name' => 'Energi']);
        $finance = Sector::factory()->create(['name' => 'Keuangan']);
        $this->regulations(3, ['sector_id' => $energy->id]);
        $this->regulations(1, ['sector_id' => $finance->id]);
        $this->regulations(1, ['sector_id' => $finance->id, 'parse_status' => 'complete']);
        $this->fakeParser(1);
        $service = app(AutomaticRegulationParseService::class);
        $service->start(2, sectorIds: [$energy->id, $finance->id]);
        $this->work();
        $this->work();

        $summary = $service->summary();
        $sectors = collect($summary['sectors'])->keyBy('name');
        $this->assertSame(['processed' => 2, 'complete' => 2, 'incomplete' => 0, 'failed' => 0, 'sector_total' => 3, 'sector_parsed' => 2, 'remaining' => 1, 'coverage_percent' => 66], collect($sectors['Energi'])->except('name')->all());
        $this->assertSame(['processed' => 0, 'sector_total' => 2, 'sector_parsed' => 1, 'remaining' => 1, 'coverage_percent' => 50], collect($sectors['Keuangan'])->only(['processed', 'sector_total', 'sector_parsed', 'remaining', 'coverage_percent'])->all());
        $this->assertSame(['sector_total' => 5, 'sector_parsed' => 3, 'remaining' => 2, 'percent' => 60, 'covers_all' => false], $summary['coverage']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.automatic-parse.settings'))->assertOk()
            ->assertSee('Cakupan sektor terpilih: 60%')->assertSee('belum mencakup semua')->assertSee('Sisa 2 regulasi');

        $service->start(10, sectorIds: [$energy->id, $finance->id]);
        $this->work();
        $this->work();
        $this->assertTrue($service->summary()['coverage']['covers_all']);
        $this->get(route('regulations.automatic-parse.settings'))->assertSee('sudah mencakup semua regulasi');
    }

    public function test_failure_reason_is_saved_and_a_mixed_batch_has_notes(): void
    {
        $rows = $this->regulations(2);
        $this->fakeParser(1, $rows->first()->id);
        $service = app(AutomaticRegulationParseService::class);
        $batch = $service->start(2);
        $this->work();
        $this->work();
        $history = $service->historyDetail($batch->id);
        $this->assertSame('Selesai dengan catatan', $history['batch']['status']);
        $results = collect($history['documents'])->keyBy('id');
        $this->assertSame('failed', $results[$rows->first()->id]['status']);
        $this->assertSame('PDF rusak.', $results[$rows->first()->id]['error']);
        $this->assertSame('complete', $results[$rows->last()->id]['status']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.automatic-parse.history.show', $batch->id))
            ->assertOk()->assertSee('PDF rusak.')->assertSee('Gagal');
    }

    public function test_incomplete_result_is_not_recorded_as_success(): void
    {
        $this->regulations();
        $this->fakeParser(2, emptyPage: 2);
        $service = app(AutomaticRegulationParseService::class);
        $batch = $service->start(1);
        $this->work();
        $history = $service->historyDetail($batch->id);
        $this->assertSame(0, $history['batch']['complete']);
        $this->assertSame(1, $history['batch']['incomplete']);
        $this->assertSame('incomplete', $history['documents'][0]['status']);
        $this->assertSame(50, $history['documents'][0]['percent']);
        $this->assertSame('Selesai dengan catatan', $history['batch']['status']);
    }

    public function test_removed_document_is_skipped_and_its_identity_is_preserved(): void
    {
        $regulation = $this->regulations()->first();
        $service = app(AutomaticRegulationParseService::class);
        $batch = $service->start(1);
        $title = $regulation->title;
        $regulation->delete();
        $this->work();
        $result = $service->historyDetail($batch->id)['documents'][0];
        $this->assertSame('skipped', $result['status']);
        $this->assertSame($title, $result['title']);
        $this->assertSame('Regulasi sudah dihapus.', $result['error']);
    }

    public function test_cancelled_batch_records_cancelled_document_and_releases_its_status(): void
    {
        $regulation = $this->regulations()->first();
        $this->fakeParser(6);
        $service = app(AutomaticRegulationParseService::class);
        $batch = $service->start(1);
        $this->work();
        Bus::findBatch($batch->id)->cancel();
        $this->work();
        $result = $service->historyDetail($batch->id);
        $this->assertSame('Dibatalkan', $result['batch']['status']);
        $this->assertSame('cancelled', $result['documents'][0]['status']);
        $this->assertSame('not_parsed', $regulation->fresh()->parse_status);
    }

    public function test_history_requires_admin_and_cannot_show_unrelated_batch_ids(): void
    {
        foreach (['user', 'reviewer', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('regulations.automatic-parse.history'))->assertForbidden();
            $this->get(route('regulations.automatic-parse.history.show', 'unknown'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.automatic-parse.history.show', 'unknown'))->assertNotFound();
        DB::table('job_batches')->insert([
            'id' => 'unrelated', 'name' => 'Unrelated batch', 'total_jobs' => 1, 'pending_jobs' => 0,
            'failed_jobs' => 0, 'failed_job_ids' => '[]', 'options' => serialize([]), 'created_at' => now()->timestamp,
        ]);
        $this->get(route('regulations.automatic-parse.history.show', 'unrelated'))->assertNotFound();
        $this->get(route('regulations.automatic-parse.history'))->assertOk()->assertSee('Belum ada riwayat');
    }

    public function test_history_paginates_and_legacy_results_are_not_fabricated(): void
    {
        for ($index = 0; $index < 16; $index++) {
            DB::table('job_batches')->insert([
                'id' => 'history-'.$index, 'name' => 'lawco-regulation-auto-parse:scheduled:'.$index,
                'total_jobs' => 1, 'pending_jobs' => 0, 'failed_jobs' => 0, 'failed_job_ids' => '[]',
                'options' => serialize(['source' => 'scheduled']), 'created_at' => now()->timestamp + $index,
                'finished_at' => now()->timestamp + $index,
            ]);
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.automatic-parse.history'))->assertOk()
            ->assertViewHas('history', fn ($rows) => $rows->total() === 16 && $rows->count() === 15)
            ->assertSee('belum dicatat pada batch lama');
        $this->get(route('regulations.automatic-parse.history', ['page' => 2]))
            ->assertOk()->assertViewHas('history', fn ($rows) => $rows->count() === 1);
    }

    private function work(): void
    {
        app('queue.worker')->runNextJob('database', 'parsing-auto', new WorkerOptions(sleep: 0, maxTries: 0));
    }

    private function fakeParser(int $totalPages, ?int $failId = null, ?int $emptyPage = null): void
    {
        $parser = Mockery::mock(RegulationParserService::class);
        $parser->shouldReceive('getPageCount')->andReturn($totalPages);
        $parser->shouldReceive('detectPdfType')->andReturn('text');
        $parser->shouldReceive('parseRegulationChunk')->andReturnUsing(function (Regulation $regulation, int $start, int $end) use ($failId, $emptyPage): array {
            if ($regulation->id === $failId) {
                throw new RuntimeException('PDF rusak.');
            }
            $pages = array_map(fn (int $page): array => ['page' => $page, 'text' => $page === $emptyPage ? '' : 'Konten halaman '.$page, 'char_count' => $page === $emptyPage ? 0 : 16, 'extraction_method' => 'text'], range($start, $end));

            return ['success' => true, 'stats' => ['pages' => $pages], 'text' => implode("\n", array_column($pages, 'text'))];
        });
        $this->app->instance(RegulationParserService::class, $parser);
    }
}
