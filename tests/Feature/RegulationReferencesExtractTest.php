<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegulationReferencesExtractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolasi test: service AI me-lewati provider dengan api_key kosong.
        // Http::fake() mengintersep semua panggilan, jadi ini hanya nilai dummy.
        config(['ai.openai.api_key' => 'test-key']);
    }

    public function test_extract_stores_and_renders_related_and_revoked_tables(): void
    {
        $this->fakeAi([
            ['name' => 'Peraturan OJK Nomor 3 Tahun 2015', 'number' => '3/POJK.05/2015', 'year' => 2015, 'relationship' => 'dirujuk'],
            ['name' => 'Peraturan OJK Nomor 29 Tahun 2018', 'number' => '29/POJK.05/2018', 'year' => 2018, 'relationship' => 'dicabut'],
        ]);

        $regulation = $this->makeRegulation('Investasi dana pensiun diatur dalam peraturan ini.');

        $this->actingAs($this->admin())
            ->post(route('regulations.extract-references', $regulation))
            ->assertRedirect(route('regulations.show', $regulation))
            ->assertSessionHas('success');

        $this->assertSame(2, $regulation->relatedReferences()->count());
        $this->assertSame(1, $regulation->relatedReferences()->where('relationship', 'dicabut')->count());

        $this->get(route('regulations.show', $regulation))
            ->assertOk()
            ->assertSee('Peraturan dicabut dan dinyatakan tidak berlaku')
            ->assertSee('Peraturan OJK Nomor 3 Tahun 2015')
            ->assertSee('Peraturan OJK Nomor 29 Tahun 2018');
    }

    public function test_re_extract_replaces_not_duplicates(): void
    {
        $this->fakeAi([
            ['name' => 'POJK X', 'number' => '1', 'year' => 2020, 'relationship' => 'dirujuk'],
        ]);

        $regulation = $this->makeRegulation('Beberapa konten.');

        $this->actingAs($this->admin())->post(route('regulations.extract-references', $regulation));
        $this->actingAs($this->admin())->post(route('regulations.extract-references', $regulation));

        $this->assertSame(1, $regulation->relatedReferences()->count());
    }

    public function test_unparsed_regulation_returns_error_without_rows(): void
    {
        $regulation = $this->makeRegulation(null);

        $this->actingAs($this->admin())
            ->post(route('regulations.extract-references', $regulation))
            ->assertRedirect(route('regulations.show', $regulation))
            ->assertSessionHas('error');

        $this->assertSame(0, $regulation->relatedReferences()->count());
    }

    public function test_extract_fills_dates_when_null(): void
    {
        $this->fakeAi([
            ['name' => 'POJK X', 'number' => '1', 'year' => 2020, 'relationship' => 'dirujuk'],
        ], ['tanggal_tetapkan' => '2026-06-23', 'tanggal_diundangkan' => '2026-06-25']);

        $regulation = $this->makeRegulation('Konten dengan footer tanggal.');

        $this->actingAs($this->admin())
            ->post(route('regulations.extract-references', $regulation))
            ->assertSessionHas('success');

        $regulation->refresh();

        $this->assertSame('2026-06-23', $regulation->tanggal_tetapkan?->format('Y-m-d'));
        $this->assertSame('2026-06-25', $regulation->tanggal_diundangkan?->format('Y-m-d'));
    }

    public function test_extract_does_not_overwrite_existing_dates(): void
    {
        $this->fakeAi([
            ['name' => 'POJK X', 'number' => '1', 'year' => 2020, 'relationship' => 'dirujuk'],
        ], ['tanggal_tetapkan' => '2026-06-23', 'tanggal_diundangkan' => '2026-06-25']);

        $regulation = $this->makeRegulation('Konten dengan footer tanggal.');
        $regulation->update(['tanggal_tetapkan' => '2020-01-01', 'tanggal_diundangkan' => '2020-02-02']);

        $this->actingAs($this->admin())
            ->post(route('regulations.extract-references', $regulation));

        $regulation->refresh();

        $this->assertSame('2020-01-01', $regulation->tanggal_tetapkan?->format('Y-m-d'));
        $this->assertSame('2020-02-02', $regulation->tanggal_diundangkan?->format('Y-m-d'));
    }

    private function fakeAi(array $references, ?array $dates = null): void
    {
        $dates ??= ['tanggal_tetapkan' => null, 'tanggal_diundangkan' => null];

        Http::fake([
            '*' => Http::sequence([
                $this->aiResponse(['referenced_regulations' => $references]),
                $this->aiResponse($dates),
            ]),
        ]);
    }

    private function aiResponse(array $data)
    {
        return Http::response([
            'choices' => [
                ['message' => ['content' => json_encode($data)]],
            ],
        ], 200);
    }

    private function makeRegulation(?string $parsedText): Regulation
    {
        $type = RegulationType::create(['name' => 'POJK', 'level' => 1]);
        $category = RegulationCategory::create(['name' => 'Kontrak Investasi Kolektif']);

        return Regulation::create([
            'regulation_number' => 'POJK/01/2026',
            'title' => 'Regulasi Test',
            'regulation_type_id' => $type->id,
            'category_id' => $category->id,
            'year' => 2026,
            'file_path' => 'regulations/fixture.pdf',
            'parsed_text' => $parsedText,
            'parsed_at' => $parsedText ? now() : null,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
