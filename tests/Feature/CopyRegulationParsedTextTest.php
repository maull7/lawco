<?php

namespace Tests\Feature;

use App\Jobs\CopyRegulationParsedTextChunk;
use App\Models\Regulation;
use App\Models\RegulationText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CopyRegulationParsedTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_copies_parsed_text_and_keeps_source_column(): void
    {
        $parsed = Regulation::factory()->create(['parsed_text' => 'isi pasal satu']);
        $trashed = Regulation::factory()->create(['parsed_text' => 'isi terhapus']);
        $trashed->delete();
        $empty = Regulation::factory()->create(['parsed_text' => null]);

        $this->artisan('regulations:copy-parsed-text', ['--check' => true])->assertFailed();
        $this->artisan('regulations:copy-parsed-text', ['--chunk' => 1])->assertSuccessful();
        $this->artisan('regulations:copy-parsed-text', ['--check' => true])->assertSuccessful();

        $this->assertSame('isi pasal satu', $parsed->text->parsed_text);
        $this->assertSame('isi terhapus', RegulationText::where('regulation_id', $trashed->id)->value('parsed_text'));
        $this->assertNull($empty->text);
        $this->assertSame('isi pasal satu', $parsed->fresh()->parsed_text);
    }

    public function test_dispatches_one_job_per_chunk_on_queue(): void
    {
        Queue::fake();
        Regulation::factory()->count(3)->create(['parsed_text' => 'isi']);
        Regulation::factory()->create(['parsed_text' => null]);

        $this->artisan('regulations:copy-parsed-text', ['--chunk' => 2])->assertSuccessful();

        Queue::assertPushed(CopyRegulationParsedTextChunk::class, 2);
        $this->assertSame(0, RegulationText::count());
    }

    public function test_rerun_updates_changed_text_without_duplicates(): void
    {
        $regulation = Regulation::factory()->create(['parsed_text' => 'versi lama']);
        $this->artisan('regulations:copy-parsed-text')->assertSuccessful();

        $regulation->update(['parsed_text' => 'versi baru']);
        $this->artisan('regulations:copy-parsed-text')->assertSuccessful();

        $this->assertSame(1, RegulationText::count());
        $this->assertSame('versi baru', $regulation->text()->value('parsed_text'));
    }
}
