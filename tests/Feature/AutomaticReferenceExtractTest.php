<?php

namespace Tests\Feature;

use App\Models\AiJobStatus;
use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use App\Services\AutomaticReferenceExtractService;
use Illuminate\Bus\BatchRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AutomaticReferenceExtractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['automatic-parse.connection' => 'database', 'ai.openai.api_key' => 'test-key']);
    }

    private function regulations(int $count = 1, array $attributes = []): Collection
    {
        $type = RegulationType::factory()->create();
        $category = RegulationCategory::factory()->create();

        return Regulation::factory()->count($count)->create([
            'regulation_type_id' => $type->id, 'category_id' => $category->id,
            'parse_status' => 'complete', 'parsed_text' => 'Mengingat: Peraturan OJK Nomor 3 Tahun 2015.', 'parsed_at' => now(),
            ...$attributes,
        ]);
    }

    /** @param array<int, array<string, mixed>> $references */
    private function fakeAi(array $references): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode(['referenced_regulations' => $references])]]]])]);
    }

    private function work(): void
    {
        app('queue.worker')->runNextJob('database', 'ai-auto', new WorkerOptions(sleep: 0, maxTries: 0));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_candidates_are_parsed_complete_unextracted_and_in_the_selected_sector(): void
    {
        $sector = Sector::factory()->create();
        $expected = $this->regulations(2, ['sector_id' => $sector->id]);
        $this->regulations(attributes: ['sector_id' => Sector::factory()->create()->id]);
        $this->regulations(attributes: ['sector_id' => $sector->id, 'parse_status' => 'incomplete']);
        $done = $this->regulations(attributes: ['sector_id' => $sector->id])->first();
        $errored = $this->regulations(attributes: ['sector_id' => $sector->id])->first();
        AiJobStatus::begin($done, 'extract')->markDone();
        AiJobStatus::begin($errored, 'extract')->markFailed('LLM down');

        $this->assertSame($expected->modelKeys(), app(AutomaticReferenceExtractService::class)->candidateIds(10, [$sector->id]));
    }

    public function test_job_stores_references_and_links_only_exact_same_year_matches(): void
    {
        $exact = $this->regulations(attributes: ['regulation_number' => '3/POJK.05/2015', 'year' => 2015, 'parse_status' => 'not_parsed'])->first();
        $this->regulations(attributes: ['regulation_number' => '7/POJK.05/2010', 'year' => 2011, 'parse_status' => 'not_parsed']);
        $this->regulations(2, ['title' => 'Dana Pensiun Lembaga Keuangan', 'year' => 2019, 'parse_status' => 'not_parsed']);
        $manual = $this->regulations(attributes: ['parse_status' => 'not_parsed'])->first();
        $regulation = $this->regulations(attributes: ['regulation_number' => '99/POJK.05/2026'])->first();
        $regulation->relatedRegulations()->attach($manual->id);
        $this->fakeAi([
            ['name' => 'Peraturan OJK Nomor 3 Tahun 2015', 'number' => '3/POJK.05/2015', 'year' => 2015, 'relationship' => 'dirujuk'],
            ['name' => 'Peraturan OJK Nomor 7 Tahun 2010', 'number' => '7/POJK.05/2010', 'year' => 2010, 'relationship' => 'dicabut'],
            ['name' => 'Dana Pensiun Lembaga Keuangan', 'number' => null, 'year' => 2019, 'relationship' => 'disebut'],
            ['name' => 'Peraturan ini sendiri', 'number' => '99/POJK.05/2026', 'year' => 2026, 'relationship' => 'disebut'],
        ]);

        $service = app(AutomaticReferenceExtractService::class);
        $batch = $service->start(1);
        $this->work();

        $this->assertSame(4, $regulation->relatedReferences()->count());
        $this->assertEqualsCanonicalizing([$manual->id, $exact->id], $regulation->relatedRegulations()->pluck('regulations.id')->all());
        $this->assertSame('done', $regulation->aiStatus('extract')->status);
        $document = $service->historyDetail($batch->id)['documents'][0];
        $this->assertSame(['complete', 4, 1], [$document['status'], $document['references'], $document['linked']]);
        $summary = $service->summary();
        $this->assertSame([1, 4, 1, false], [$summary['complete'], $summary['references'], $summary['linked'], $summary['active']]);
    }

    public function test_regulation_extracted_manually_after_queueing_is_skipped(): void
    {
        $regulation = $this->regulations()->first();
        Http::fake();
        $service = app(AutomaticReferenceExtractService::class);
        $batch = $service->start(1);
        AiJobStatus::begin($regulation, 'extract')->markDone('manual');

        $this->work();

        Http::assertNothingSent();
        $this->assertSame('skipped', $service->historyDetail($batch->id)['documents'][0]['status']);
        $this->assertSame('manual', $regulation->aiStatus('extract')->message);
    }

    public function test_llm_failure_marks_document_and_ai_status_failed(): void
    {
        $regulation = $this->regulations()->first();
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'bukan json']]]])]);
        $service = app(AutomaticReferenceExtractService::class);
        $batch = $service->start(1);

        $this->work();

        $this->assertSame('failed', $service->historyDetail($batch->id)['documents'][0]['status']);
        $this->assertSame('error', $regulation->aiStatus('extract')->status);
        $this->assertSame([$regulation->id], array_column($service->summary()['failed_documents'], 'id'));
        $this->assertSame([], $service->candidateIds(10));
    }

    public function test_admin_starts_manual_batch_and_sees_it_on_settings(): void
    {
        $this->regulations(3);

        $this->actingAs($this->admin())
            ->post(route('regulations.automatic-extract'), ['limit' => 2])
            ->assertRedirect(route('regulations.automatic-parse.settings'))->assertSessionHas('success');

        $this->assertSame(2, DB::table('jobs')->where('queue', 'ai-auto')->count());
        $this->get(route('regulations.automatic-parse.settings'))->assertOk()->assertSee('Ekstrak sedang berjalan')->assertSee('Mulai Ekstrak Sekarang');
        $batchId = app(AutomaticReferenceExtractService::class)->activeBatch()->id;
        $this->get(route('regulations.automatic-extract.history'))->assertOk()->assertSee('Tombol manual');
        $this->get(route('regulations.automatic-extract.history.show', $batchId))->assertOk()->assertSee('Menunggu');
    }

    public function test_limit_above_one_thousand_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('regulations.automatic-extract'), ['limit' => 1001])
            ->assertSessionHasErrors('limit');
        $this->post(route('regulations.automatic-extract'), ['limit' => 1000])->assertSessionHasNoErrors();
        $this->expectException(ValidationException::class);
        app(AutomaticReferenceExtractService::class)->start(1001);
    }

    public function test_non_admin_cannot_start_or_view_history(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->post(route('regulations.automatic-extract'), ['limit' => 10])->assertForbidden();
        $this->get(route('regulations.automatic-extract.history'))->assertForbidden();
        $this->assertSame(0, DB::table('job_batches')->count());
    }

    public function test_scheduled_command_uses_saved_sectors_daily_limit_and_runs_once_per_day(): void
    {
        $sector = Sector::factory()->create(['is_active' => true]);
        $this->regulations(2, ['sector_id' => $sector->id]);
        $this->regulations(attributes: ['sector_id' => Sector::factory()->create()->id]);

        $this->artisan('regulations:extract-references-automatic')->expectsOutputToContain('dilewati')->assertSuccessful();
        $this->assertSame(0, DB::table('job_batches')->count());

        Setting::updateOrCreate(['key' => 'automatic_parse_sector_ids'], ['value' => json_encode([$sector->id])]);
        $this->artisan('regulations:extract-references-automatic', ['--dry-run' => true])->expectsOutputToContain('2 regulasi')->assertSuccessful();
        $this->assertSame(0, DB::table('jobs')->count());

        $this->artisan('regulations:extract-references-automatic')->assertSuccessful();
        $batch = app(AutomaticReferenceExtractService::class)->activeBatch();
        $this->assertSame(1000, $batch->options['requested_limit']);
        $this->assertSame(2, $batch->totalJobs);
        app(BatchRepository::class)->markAsFinished($batch->id);
        $this->artisan('regulations:extract-references-automatic')->assertSuccessful();
        $this->assertSame(1, DB::table('job_batches')->count());
    }

    public function test_nightly_schedule_runs_at_twenty_two_jakarta_time(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'regulations-nightly-automatic-extract');
        $this->assertNotNull($event);
        $this->assertSame('0 22 * * *', $event->expression);
        $this->assertSame('Asia/Jakarta', $event->timezone);
        $this->assertTrue($event->onOneServer);
        $this->assertSame(1, config('horizon.defaults.ai-auto.maxProcesses'));
    }
}
