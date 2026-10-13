<?php

namespace Tests\Feature;

use App\Models\Regulation;
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
        $this->put(route('regulations.automatic-parse.settings.update'), ['sector_id' => $sector->id])
            ->assertRedirect(route('regulations.automatic-parse.settings'))->assertSessionHas('success');
        $this->assertSame((string) $sector->id, Setting::get('automatic_parse_sector_id'));
        $this->get(route('regulations.automatic-parse.settings'))->assertViewHas('selectedSector', fn ($row) => $row->is($sector));
        $this->put(route('regulations.automatic-parse.settings.update'), ['sector_id' => $other->id])->assertSessionHas('success');
        $this->assertSame((string) $other->id, Setting::get('automatic_parse_sector_id'));
        $this->assertSame(1, Setting::where('key', 'automatic_parse_sector_id')->count());
        $this->assertSame(0, DB::table('job_batches')->count());
    }

    public function test_non_admin_roles_cannot_view_or_change_schedule_settings(): void
    {
        $sector = Sector::factory()->create();
        foreach (['user', 'reviewer', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('regulations.automatic-parse.settings'))->assertForbidden();
            $this->put(route('regulations.automatic-parse.settings.update'), ['sector_id' => $sector->id])->assertForbidden();
        }
        $this->assertNull(Setting::get('automatic_parse_sector_id'));
    }

    public function test_invalid_sectors_do_not_overwrite_saved_settings(): void
    {
        $sector = Sector::factory()->create();
        $inactive = Sector::factory()->create(['is_active' => false]);
        app(AutomaticRegulationParseService::class)->saveScheduledSector($sector->id);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([null, '', 'invalid', 999999, $inactive->id] as $id) {
            $this->put(route('regulations.automatic-parse.settings.update'), ['sector_id' => $id])->assertSessionHasErrors('sector_id');
        }
        $this->assertSame((string) $sector->id, Setting::get('automatic_parse_sector_id'));
    }

    public function test_scheduled_command_and_dry_run_only_use_the_saved_sector(): void
    {
        $other = Sector::factory()->create();
        $selected = Sector::factory()->create();
        $this->regulations($other, 3);
        $rows = $this->regulations($selected, 2);
        $service = app(AutomaticRegulationParseService::class);
        $service->saveScheduledSector($selected->id);
        $this->artisan('regulations:parse-automatic', ['--dry-run' => true])
            ->expectsOutput('2 regulasi memenuhi syarat; tidak ada job dikirim.')->assertSuccessful();
        $this->assertSame(0, DB::table('job_batches')->count());
        $this->artisan('regulations:parse-automatic')->assertSuccessful();
        $batch = $service->activeBatch();
        $this->assertSame($rows->modelKeys(), $batch->options['regulation_ids']);
        $this->assertSame($selected->id, $batch->options['sector_id']);
        $this->assertSame('scheduled', $batch->options['source']);
        $this->assertSame(500, $batch->options['requested_limit']);
        app(BatchRepository::class)->markAsFinished($batch->id);
        $service->saveScheduledSector($other->id);
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
        $service->saveScheduledSector($selected->id);
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
        app(AutomaticRegulationParseService::class)->saveScheduledSector($selected->id);
        $this->artisan('regulations:parse-automatic')->assertSuccessful();
        $this->assertSame(0, DB::table('job_batches')->count());
    }

    /** @return Collection<int, Regulation> */
    private function regulations(Sector $sector, int $count): Collection
    {
        return Regulation::factory()->count($count)->create([
            'sector_id' => $sector->id, 'file_path' => 'regulations/existing.pdf', 'parse_status' => 'not_parsed',
        ]);
    }
}
