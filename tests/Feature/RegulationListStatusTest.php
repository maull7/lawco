<?php

namespace Tests\Feature;

use App\Models\AiJobStatus;
use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegulationListStatusTest extends TestCase
{
    use RefreshDatabase;

    private function regulation(): Regulation
    {
        return Regulation::create([
            'regulation_number' => 'POJK 1/2026',
            'title' => 'Peraturan Pengujian',
            'category_id' => RegulationCategory::create(['name' => 'Perbankan'])->id,
            'regulation_type_id' => RegulationType::create(['name' => 'POJK', 'level' => 1])->id,
            'year' => 2026,
            'file_path' => 'regulations/test.pdf',
        ]);
    }

    /** @return array<string, array{?string, bool, string}> */
    public static function extractionCases(): array
    {
        return [
            'never extracted' => [null, false, 'Belum Diekstrak'],
            'completed without references' => ['done', false, 'Sudah Diekstrak'],
            'legacy references' => [null, true, 'Sudah Diekstrak'],
            'processing with previous references' => ['processing', true, 'Sedang Diproses'],
            'failed with previous references' => ['error', true, 'Gagal'],
        ];
    }

    #[DataProvider('extractionCases')]
    public function test_admin_sees_extraction_status(?string $status, bool $hasReferences, string $label): void
    {
        $regulation = $this->regulation();
        if ($hasReferences) {
            $regulation->relatedReferences()->create(['name' => 'Peraturan terkait', 'relationship' => 'terkait']);
        }
        if ($status !== null) {
            AiJobStatus::begin($regulation, 'extract')->update(['status' => $status]);
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.index'))
            ->assertOk()
            ->assertSee('Status Ekstrak Peraturan Terkait')
            ->assertSee('Short Review')
            ->assertSee($label)
            ->assertSee('Belum Ada');
    }

    public function test_short_review_status_only_counts_short_review_results(): void
    {
        $regulation = $this->regulation();
        $result = $regulation->aiResults()->create([
            'type' => 'review', 'prompt_title' => 'Analisa', 'prompt_text' => 'Prompt', 'result' => 'Hasil',
        ]);
        AiJobStatus::begin($regulation, 'regulation-ai')->markDone();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.index'))->assertOk()->assertSee('Belum Ada')->assertDontSee('Sudah Ada');

        $result->update(['prompt_title' => 'Short Review']);
        $this->get(route('regulations.index'))->assertOk()->assertSee('Sudah Ada')->assertDontSee('Belum Ada');
    }

    public function test_non_admin_does_not_see_status_columns(): void
    {
        $this->regulation();
        foreach (['user', 'reviewer', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('regulations.index'))
                ->assertOk()
                ->assertDontSee('Status Ekstrak Peraturan Terkait')
                ->assertDontSee('Short Review');
        }
    }
}
