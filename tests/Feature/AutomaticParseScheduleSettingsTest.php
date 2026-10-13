<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use App\Services\AutomaticRegulationParseService;
use Illuminate\Bus\BatchRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AutomaticParseScheduleSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::disk('public')->put('regulations/existing.pdf', 'PDF fixture');
        config(['automatic-parse.connection' => 'database']);
    }

    public function test_admin_can_save_and_change_the_sector_for_the_daily_schedule(): void
    {
        $sector = Sector::factory()->create(['name' => 'Keuangan']);
        $other = Sector::factory()->create(['name' => 'Energi']);
        $inactive = Sector::factory()->create(['name' => 'Tidak aktif', 'is_active' => false]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.automatic-parse.settings'))->assertOk()
            ->assertSee('20.00 WIB')->assertSee('Keuangan')->assertDontSee('Tidak aktif');
        $this->put(route('regulations.automatic-parse.settings.update'), ['sector_ids' => [$sector->id]])
            ->assertRedirect(route('regulations.automatic-parse.settings'))->assertSessionHas('success');
        $this->assertSame(json_encode([$sector->id]), Setting::get('automatic_parse_sector_ids'));
        $this->get(route('regulations.automatic-parse.settings'))->assertViewHas('selectedSectors', fn ($rows) => $rows->modelKeys() === [$sector->id]);
        $this->put(route('regulations.automatic-parse.settings.update'), ['sector_ids' => [$other->id]])->assertSessionHas('success');
        $this->assertSame(json_encode([$other->id]), Setting::get('automatic_parse_sector_ids'));
        $this->assertSame(1, Setting::where('key', 'automatic_parse_sector_ids')->count());
        $this->assertSame(0, DB::table('job_batches')->count());
    }

    public function test_non_admin_roles_cannot_view_or_change_schedule_settings(): void
    {
        $sector = Sector::factory()->create();
        foreach (['user', 'reviewer', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('regulations.automatic-parse.settings'))->assertForbidden();
            $this->put(route('regulations.automatic-parse.settings.update'), ['sector_ids' => [$sector->id]])->assertForbidden();
        }
        $this->assertNull(Setting::get('automatic_parse_sector_ids'));
    }

    public function test_invalid_sectors_do_not_overwrite_saved_settings(): void
    {
        $sector = Sector::factory()->create();
        $inactive = Sector::factory()->create(['is_active' => false]);
        app(AutomaticRegulationParseService::class)->saveScheduledSectors([$sector->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([null, '', 'invalid', 999999, $inactive->id] as $id) {
            $this->put(route('regulations.automatic-parse.settings.update'), ['sector_ids' => [$id]])->assertSessionHasErrors('sector_ids.0');
        }
        $this->assertSame(json_encode([$sector->id]), Setting::get('automatic_parse_sector_ids'));
    }

    public function test_scheduled_command_and_dry_run_only_use_the_saved_sector(): void
    {
        $other = Sector::factory()->create();
        $selected = Sector::factory()->create();
        $this->regulations($other, 3);
        $rows = $this->regulations($selected, 2);
        $service = app(AutomaticRegulationParseService::class);
        $service->saveScheduledSectors([$selected->id]);
        $this->artisan('regulations:parse-automatic', ['--dry-run' => true])
            ->expectsOutput('2 regulasi memenuhi syarat; tidak ada job dikirim.')->assertSuccessful();
        $this->assertSame(0, DB::table('job_batches')->count());
        $this->artisan('regulations:parse-automatic')->assertSuccessful();
        $batch = $service->activeBatch();
        $this->assertSame($rows->modelKeys(), $batch->options['regulation_ids']);
        $this->assertSame($selected->id, $batch->options['sector_id']);
        $this->assertSame('scheduled', $batch->options['source']);
        $this->assertSame(3000, $batch->options['requested_limit']);
        app(BatchRepository::class)->markAsFinished($batch->id);
        $service->saveScheduledSectors([$other->id]);
        $this->artisan('regulations:parse-automatic')->assertSuccessful();
        $this->assertSame(1, DB::table('job_batches')->count());
    }

    public function test_missing_inactive_or_deleted_selected_sector_never_falls_back_to_all_sectors(): void
    {
        $other = Sector::factory()->create();
        $this->regulations($other, 1);
        $service = app(AutomaticRegulationParseService::class);
        $this->artisan('regulations:parse-automatic')->assertSuccessful();
        $selected = Sector::factory()->create();
        $service->saveScheduledSectors([$selected->id]);
        $selected->update(['is_active' => false]);
        $this->artisan('regulations:parse-automatic')->assertSuccessful();
        $selected->delete();
        $this->artisan('regulations:parse-automatic', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('job_batches')->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_empty_selected_sector_does_not_create_jobs_for_other_sectors(): void
    {
        $other = Sector::factory()->create();
        $this->regulations($other, 1);
        $selected = Sector::factory()->create();
        app(AutomaticRegulationParseService::class)->saveScheduledSectors([$selected->id]);
        $this->artisan('regulations:parse-automatic')->assertSuccessful();
        $this->assertSame(0, DB::table('job_batches')->count());
    }

    public function test_multiple_saved_sectors_are_used_by_the_nightly_command(): void
    {
        $first = Sector::factory()->create();
        $second = Sector::factory()->create();
        $other = Sector::factory()->create();
        $firstRows = $this->regulations($first, 2);
        $secondRows = $this->regulations($second, 2);
        $this->regulations($other, 2);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('regulations.automatic-parse.settings.update'), ['sector_ids' => [$first->id, $second->id]])
            ->assertSessionHas('success');
        $service = app(AutomaticRegulationParseService::class);
        $this->assertSame([$first->id, $second->id], $service->scheduledSectorIds());
        $this->get(route('regulations.automatic-parse.settings'))->assertOk()
            ->assertViewHas('selectedSectors', fn ($rows) => $rows->count() === 2);
        $this->artisan('regulations:parse-automatic')->assertSuccessful();
        $this->assertSame(array_merge($firstRows->modelKeys(), $secondRows->modelKeys()), $service->activeBatch()->options['regulation_ids']);
    }

    public function test_legacy_single_sector_setting_remains_readable_until_replaced(): void
    {
        $sector = Sector::factory()->create();
        $other = Sector::factory()->create();
        Setting::create(['key' => 'automatic_parse_sector_id', 'value' => (string) $sector->id]);
        $service = app(AutomaticRegulationParseService::class);
        $this->assertSame([$sector->id], $service->scheduledSectorIds());
        $service->saveScheduledSectors([$sector->id, $other->id]);
        $this->assertSame([$sector->id, $other->id], $service->scheduledSectorIds());
    }

    public function test_schedule_requires_at_least_one_distinct_sector_and_rejects_scalar_input(): void
    {
        $sector = Sector::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([null, [], 'invalid', $sector->id] as $ids) {
            $this->put(route('regulations.automatic-parse.settings.update'), ['sector_ids' => $ids])
                ->assertSessionHasErrors('sector_ids');
        }
        $this->put(route('regulations.automatic-parse.settings.update'), ['sector_ids' => [$sector->id, $sector->id]])
            ->assertSessionHasErrors('sector_ids.0');
        $this->assertNull(Setting::get('automatic_parse_sector_ids'));
    }

    public function test_nightly_run_is_capped_at_three_thousand_and_does_not_repeat_any_batch_on_the_same_day(): void
    {
        $sector = Sector::factory()->create();
        $type = RegulationType::factory()->create();
        $category = RegulationCategory::factory()->create();
        Regulation::factory()->count(3001)->create([
            'sector_id' => $sector->id, 'regulation_type_id' => $type->id, 'category_id' => $category->id,
            'file_path' => 'regulations/existing.pdf', 'parse_status' => 'not_parsed',
        ]);
        $service = app(AutomaticRegulationParseService::class);
        $service->saveScheduledSectors([$sector->id]);
        $this->artisan('regulations:parse-automatic')->expectsOutput('3000 regulasi dijadwalkan dalam 6 batch parse otomatis.')->assertSuccessful();
        $this->assertSame(6, DB::table('job_batches')->count());
        $this->assertSame(3000, DB::table('jobs')->count());
        foreach ($service->history() as $batch) {
            $this->assertSame(500, $batch['total']);
            app(BatchRepository::class)->markAsFinished($batch['id']);
        }
        $this->artisan('regulations:parse-automatic')->assertSuccessful();
        $this->assertSame(6, DB::table('job_batches')->count());
        $this->assertSame(3000, DB::table('jobs')->count());
    }

    /** @return Collection<int, Regulation> */
    private function regulations(Sector $sector, int $count): Collection
    {
        return Regulation::factory()->count($count)->create([
            'sector_id' => $sector->id, 'file_path' => 'regulations/existing.pdf', 'parse_status' => 'not_parsed',
        ]);
    }
}
