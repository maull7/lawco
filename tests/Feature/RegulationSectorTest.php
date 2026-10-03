<?php

namespace Tests\Feature;

use App\Models\JdihTarget;
use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegulationSectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_uses_category_then_target_and_preserves_existing_sectors(): void
    {
        $categorySector = Sector::create(['name' => 'Perbankan']);
        $targetSector = Sector::create(['name' => 'Komunikasi']);
        $existingSector = Sector::create(['name' => 'Energi']);
        $category = RegulationCategory::create(['name' => 'Kredit', 'sector_id' => $categorySector->id]);
        $categoryWithoutSector = RegulationCategory::create(['name' => 'Kategori lama']);
        $target = JdihTarget::create(['name' => 'Target Test', 'source' => 'sector_test', 'sector_id' => $targetSector->id]);
        $categorized = $this->createRegulation(null, $category);
        $uncategorized = $this->createRegulation();
        $withoutCategorySector = $this->createRegulation(null, $categoryWithoutSector);
        $existing = $this->createRegulation($existingSector);
        $unmapped = $this->createRegulation();
        $deleted = $this->createRegulation(null, $category);
        $deleted->delete();

        foreach ([$categorized, $uncategorized, $withoutCategorySector, $existing] as $regulation) {
            $this->createSyncLog($regulation, $target->source);
        }
        $originalUpdatedAt = $categorized->getRawOriginal('updated_at');
        $migration = require database_path('migrations/2026_10_03_032245_backfill_sector_id_on_regulations.php');
        $migration->up();

        $this->assertSame($categorySector->id, $categorized->fresh()->sector_id);
        $this->assertSame($category->id, $categorized->fresh()->category_id);
        $this->assertSame($originalUpdatedAt, $categorized->fresh()->getRawOriginal('updated_at'));
        $this->assertSame($targetSector->id, $uncategorized->fresh()->sector_id);
        $this->assertNull($uncategorized->fresh()->category_id);
        $this->assertSame($targetSector->id, $withoutCategorySector->fresh()->sector_id);
        $this->assertSame($existingSector->id, $existing->fresh()->sector_id);
        $this->assertNull($unmapped->fresh()->sector_id);
        $this->assertSame($categorySector->id, Regulation::withTrashed()->findOrFail($deleted->id)->sector_id);

        $target->update(['sector_id' => $existingSector->id]);
        $migration->up();
        $this->assertSame($targetSector->id, $uncategorized->fresh()->sector_id);
    }

    public function test_backfill_leaves_sector_null_when_target_has_no_sector(): void
    {
        $regulation = $this->createRegulation();
        JdihTarget::create(['name' => 'Target Tanpa Sektor', 'source' => 'unmapped_test']);
        $this->createSyncLog($regulation, 'unmapped_test');
        $migration = require database_path('migrations/2026_10_03_032245_backfill_sector_id_on_regulations.php');
        $migration->up();

        $this->assertNull($regulation->fresh()->sector_id);
    }

    public function test_create_without_category_saves_selected_sector_and_displays_it(): void
    {
        Storage::fake('public');
        $sector = Sector::create(['name' => 'Komunikasi']);
        $type = RegulationType::create(['name' => 'Peraturan', 'level' => 1]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('regulations.store'), [
            'regulation_number' => 'REG-1',
            'title' => 'Regulasi Tanpa Kategori',
            'regulation_type_id' => $type->id,
            'sector_id' => $sector->id,
            'category_id' => null,
            'year' => 2026,
            'file' => UploadedFile::fake()->create('regulation.pdf', 10, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $regulation = Regulation::query()->sole();
        $this->assertSame($sector->id, $regulation->sector_id);
        $this->assertNull($regulation->category_id);
        $this->assertTrue($regulation->sector->is($sector));
        $this->assertTrue($sector->regulations->contains($regulation));
        $this->get(route('regulations.show', $regulation))->assertOk()->assertSee('Komunikasi');
        $this->get(route('regulations.edit', $regulation))->assertOk()
            ->assertSee(', '.$sector->id.', null)', false);
    }

    public function test_update_can_change_sector_and_remove_category(): void
    {
        $originalSector = Sector::create(['name' => 'Perbankan']);
        $newSector = Sector::create(['name' => 'Komunikasi']);
        $category = RegulationCategory::create(['name' => 'Kredit', 'sector_id' => $originalSector->id]);
        $regulation = $this->createRegulation($originalSector, $category);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('regulations.update', $regulation), [
                'regulation_number' => $regulation->regulation_number,
                'title' => $regulation->title,
                'regulation_type_id' => $regulation->regulation_type_id,
                'sector_id' => $newSector->id,
                'category_id' => null,
                'year' => 2026,
            ])->assertRedirect(route('regulations.show', $regulation))->assertSessionHasNoErrors();

        $this->assertSame($newSector->id, $regulation->fresh()->sector_id);
        $this->assertNull($regulation->fresh()->category_id);
    }

    public function test_create_and_update_reject_category_from_another_sector(): void
    {
        Storage::fake('public');
        $sector = Sector::create(['name' => 'Perbankan']);
        $otherSector = Sector::create(['name' => 'Energi']);
        $category = RegulationCategory::create(['name' => 'Pertambangan', 'sector_id' => $otherSector->id]);
        $regulation = $this->createRegulation($sector);
        $data = [
            'regulation_number' => $regulation->regulation_number,
            'title' => $regulation->title,
            'regulation_type_id' => $regulation->regulation_type_id,
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'year' => 2026,
        ];
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('regulations.store'), [
                ...$data,
                'file' => UploadedFile::fake()->create('regulation.pdf', 10, 'application/pdf'),
            ])->assertSessionHasErrors('category_id');
        $this->put(route('regulations.update', $regulation), $data)->assertSessionHasErrors('category_id');
        $this->assertSame(1, Regulation::count());
        $this->assertSame($sector->id, $regulation->fresh()->sector_id);
        $this->assertNull($regulation->fresh()->category_id);
    }

    public function test_sector_filter_includes_regulations_without_category(): void
    {
        $sector = Sector::create(['name' => 'Komunikasi']);
        $otherSector = Sector::create(['name' => 'Energi']);
        $regulation = $this->createRegulation($sector);
        $this->createRegulation($otherSector);
        $this->createRegulation();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.index', ['sector_id' => $sector->id]))
            ->assertOk()->assertViewHas('regulations', fn ($rows): bool => $rows->total() === 1
                && $rows->first()->is($regulation));
    }

    public function test_regulation_type_sector_filter_includes_regulations_without_category(): void
    {
        $sector = Sector::create(['name' => 'Komunikasi']);
        $regulation = $this->createRegulation($sector);
        RegulationType::create(['name' => 'Jenis Lain', 'level' => 2]);
        foreach (['admin' => 'regulation-types.index', 'user' => 'user.regulation-types.index'] as $role => $route) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route($route, ['sector_id' => $sector->id]))
                ->assertOk()->assertViewHas('types', fn ($types): bool => $types->modelKeys() === [$regulation->regulation_type_id])
                ->assertSee('Komunikasi')->assertDontSee('Jenis Lain');
        }
    }

    public function test_private_sector_is_hidden_even_without_category(): void
    {
        $sector = Sector::create(['name' => 'Sektor Rahasia', 'is_public' => false]);
        $regulation = $this->createRegulation($sector);
        $this->get(route('index-dash'))->assertOk()->assertDontSee($regulation->title);
        $this->actingAs(User::factory()->create())
            ->get(route('regulations.index'))->assertOk()->assertDontSee($regulation->title);
        $this->get(route('regulations.show', $regulation))->assertNotFound();
        $this->get(route('regulations.file-raw', $regulation))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.show', $regulation))->assertOk()->assertSee('Sektor Rahasia');
    }

    public function test_deleting_sector_clears_direct_regulation_sector(): void
    {
        $sector = Sector::create(['name' => 'Komunikasi']);
        $regulation = $this->createRegulation($sector);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('sectors.destroy', $sector))->assertRedirect(route('sectors.index'));

        $this->assertNull($regulation->fresh()->sector_id);
    }

    private function createRegulation(?Sector $sector = null, ?RegulationCategory $category = null): Regulation
    {
        $type = RegulationType::firstOrCreate(['name' => 'Peraturan'], ['level' => 1]);

        return Regulation::create([
            'regulation_number' => fake()->unique()->numerify('REG-####'),
            'title' => fake()->unique()->sentence(),
            'regulation_type_id' => $type->id,
            'sector_id' => $sector?->id,
            'category_id' => $category?->id,
            'year' => 2026,
            'file_path' => 'regulations/test.pdf',
        ]);
    }

    private function createSyncLog(Regulation $regulation, string $source): void
    {
        DB::table('jdih_sync_log')->insert([
            'jdih_source' => $source,
            'jdih_document_id' => (string) $regulation->id,
            'lawco_regulation_id' => $regulation->id,
            'file_path' => $regulation->file_path,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
