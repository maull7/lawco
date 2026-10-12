<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationChatMessage;
use App\Models\RegulationDocument;
use App\Models\RegulationType;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegulationChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_ask_and_reply_is_saved(): void
    {
        $this->mock(AiService::class, function ($mock): void {
            $mock->shouldReceive('askRegulation')
                ->once()
                ->andReturn(['content' => 'Halo! Berdasarkan regulasi tersebut, ketentuan pasal terkait mengatur kewajiban keterbukaan informasi.', 'total_tokens' => 100]);
        });

        $user = User::factory()->create();
        $regulation = $this->makeRegulation();

        $this->actingAs($user)->post(route('regulations.chat.ask', $regulation), [
            'question' => 'Apa ketentuan keterbukaan informasi di regulasi ini?',
        ])->assertRedirectToRoute('regulations.show', ['regulation' => $regulation, 'tab' => 'vesa']);

        $messages = RegulationChatMessage::where('regulation_id', $regulation->id)->get();

        $this->assertCount(2, $messages);
        $this->assertSame(['assistant', 'user'], $messages->pluck('role')->sort()->values()->all());
        $this->assertTrue($messages->contains('content', 'Apa ketentuan keterbukaan informasi di regulasi ini?'));
        $this->assertTrue($messages->contains(fn ($m) => str_contains($m->content, 'Kak Vesa') === false && str_contains($m->content, 'keterbukaan informasi')));
    }

    public function test_ask_returns_json_reply(): void
    {
        $this->mock(AiService::class, function ($mock): void {
            $mock->shouldReceive('askRegulation')
                ->once()
                ->andReturn(['content' => 'Balasan Kak Vesa via JSON.', 'total_tokens' => 50]);
        });

        $user = User::factory()->create();
        $regulation = $this->makeRegulation();

        $this->actingAs($user)->postJson(route('regulations.chat.ask', $regulation), [
            'question' => 'Jelaskan poin penting?',
        ])->assertOk()
            ->assertJson(['reply' => 'Balasan Kak Vesa via JSON.']);

        $this->assertSame(2, RegulationChatMessage::where('regulation_id', $regulation->id)->count());
    }

    public function test_chat_history_is_isolated_per_account(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $regulation = $this->makeRegulation();

        RegulationChatMessage::create([
            'user_id' => $userA->id,
            'regulation_id' => $regulation->id,
            'role' => 'user',
            'content' => 'Pertanyaan rahasia milik akun A',
        ]);
        RegulationChatMessage::create([
            'user_id' => $userA->id,
            'regulation_id' => $regulation->id,
            'role' => 'assistant',
            'content' => 'Jawaban untuk akun A',
        ]);

        $this->actingAs($userB)->get(route('regulations.show', $regulation))
            ->assertOk()
            ->assertDontSee('Pertanyaan rahasia milik akun A');

        $this->actingAs($userA)->get(route('regulations.show', $regulation))
            ->assertOk()
            ->assertSee('Pertanyaan rahasia milik akun A');
    }

    public function test_user_profile_passed_as_memory(): void
    {
        $user = User::factory()->create();
        $regulation = $this->makeRegulation();

        $this->mock(AiService::class, function ($mock) use ($user): void {
            $mock->shouldReceive('askRegulation')
                ->once()
                ->withArgs(fn ($reg, $question, $history, $passedUser) => $passedUser?->id === $user->id)
                ->andReturn(['content' => 'Balasan dengan memori.', 'total_tokens' => 100]);
        });

        $this->actingAs($user)->postJson(route('regulations.chat.ask', $regulation), [
            'question' => 'Siapa saya?',
        ])->assertOk();
    }

    public function test_vesa_context_contains_regulation_and_document_texts(): void
    {
        $regulation = $this->makeRegulation();
        $regulation->update(['parsed_text' => 'Konten utama regulasi yang harus dibaca.']);

        RegulationDocument::create([
            'regulation_id' => $regulation->id,
            'name' => 'Lampiran A',
            'document_type' => 'lampiran',
            'file_path' => 'regulation-documents/a.pdf',
            'parsed_text' => 'Isi dokumen lampiran untuk dianalisis.',
            'parsed_at' => now(),
        ]);

        $messages = (app(AiService::class))->buildRegulationMessages($regulation, 'Apa isi lampirannya?');

        $context = collect($messages)->pluck('content')->implode("\n");

        $this->assertStringContainsString('Konten utama regulasi', $context);
        $this->assertStringContainsString('[Lampiran A]', $context);
        $this->assertStringContainsString('Isi dokumen lampiran', $context);
        $this->assertStringContainsString('<document_context>', $context);
        $this->assertStringContainsString('[{pertanyaan pengguna}]', $context);
        $this->assertStringContainsString('Apa isi lampirannya?', $context);
    }

    public function test_vesa_context_falls_back_when_unparsed(): void
    {
        $regulation = $this->makeRegulation();

        $context = collect((app(AiService::class))->buildRegulationMessages($regulation, 'Halo'))
            ->pluck('content')
            ->implode("\n");

        $this->assertStringContainsString('(Teks regulasi belum diparse.)', $context);
    }

    public function test_question_is_required(): void
    {
        $user = User::factory()->create();
        $regulation = $this->makeRegulation();

        $this->actingAs($user)->post(route('regulations.chat.ask', $regulation), [
            'question' => '',
        ])->assertSessionHasErrors('question');

        $this->assertSame(0, RegulationChatMessage::count());
    }

    public function test_unauthenticated_user_cannot_ask(): void
    {
        $regulation = $this->makeRegulation();

        $this->post(route('regulations.chat.ask', $regulation), [
            'question' => 'Halo?',
        ])->assertRedirect(route('login'));
    }

    private function makeRegulation(): Regulation
    {
        $type = RegulationType::create(['name' => 'POJK', 'level' => 1]);
        $category = RegulationCategory::create(['name' => 'Pasar Modal']);

        return Regulation::create([
            'regulation_number' => 'POJK/10/2026',
            'title' => 'Regulasi Chat Test',
            'regulation_type_id' => $type->id,
            'category_id' => $category->id,
            'year' => 2026,
            'file_path' => 'regulations/fixture.pdf',
        ]);
    }
}
