<?php

namespace Tests\Feature;

use App\Models\ConsultationChatMessage;
use App\Models\ConsultationSession;
use App\Models\Package;
use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Setting;
use App\Models\TokenUsage;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\TokenLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TokenLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolasi test: service AI me-lewati provider dengan api_key kosong.
        // Http::fake() mengintersep semua panggilan, jadi ini hanya nilai dummy.
        config(['ai.openai.api_key' => 'test-key']);
    }

    private function paidUser(): User
    {
        $user = User::factory()->create(['role' => 'user']);
        $package = Package::create(['name' => 'Bisnis', 'price' => '12jt', 'benefits' => []]);
        UserPackage::create(['user_id' => $user->id, 'package_id' => $package->id, 'type' => 'paid', 'status' => 'active']);

        return $user;
    }

    private function makeRegulations(int $count): array
    {
        $type = RegulationType::create(['name' => 'P', 'level' => 1]);
        $cat = RegulationCategory::create(['name' => 'K']);
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $ids[] = Regulation::create([
                'regulation_number' => "R{$i}", 'title' => "T{$i}",
                'regulation_type_id' => $type->id, 'category_id' => $cat->id,
                'year' => 2026, 'file_path' => "reg/f{$i}.pdf",
            ])->id;
        }

        return $ids;
    }

    public function test_token_limit_default_is_100k(): void
    {
        $this->assertSame(100000, (int) Setting::get('daily_token_limit'));
    }

    public function test_today_usage_sum_correct(): void
    {
        $user = $this->paidUser();
        TokenUsage::create(['user_id' => $user->id, 'date' => today()->toDateString(), 'tokens_used' => 300, 'source' => 'test']);
        TokenUsage::create(['user_id' => $user->id, 'date' => today()->toDateString(), 'tokens_used' => 700, 'source' => 'test']);
        TokenUsage::create(['user_id' => $user->id, 'date' => '2026-01-01', 'tokens_used' => 9999, 'source' => 'old']);

        $this->assertSame(1000, TokenUsage::todayUsage($user->id));
    }

    public function test_can_send_blocks_when_exceeded(): void
    {
        $user = $this->paidUser();
        Setting::where('key', 'daily_token_limit')->update(['value' => '200']);

        $tokenLimit = app(TokenLimitService::class);
        $this->assertSame(200, $tokenLimit->dailyLimit());
        $this->assertSame(200, $tokenLimit->remaining($user->id));

        TokenUsage::create(['user_id' => $user->id, 'date' => today()->toDateString(), 'tokens_used' => 200, 'source' => 'test']);
        $this->assertSame(0, $tokenLimit->remaining($user->id));
        $this->assertFalse($tokenLimit->canSend($user->id));
    }

    public function test_chat_respects_token_limit(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'OK']]],
                'usage' => ['total_tokens' => 60],
            ]),
        ]);

        $user = $this->paidUser();
        Setting::where('key', 'daily_token_limit')->update(['value' => '200']);

        $ids = $this->makeRegulations(1);
        $this->actingAs($user)->post(route('consultations.store'), ['regulation_ids' => $ids]);
        $session = ConsultationSession::firstOrFail();

        $this->actingAs($user)->post(route('consultations.chat.ask', $session), ['question' => 'Test?']);

        $this->assertSame(1, ConsultationChatMessage::where('consultation_session_id', $session->id)
            ->where('role', 'assistant')->count());

        $this->assertGreaterThanOrEqual(60, TokenUsage::todayUsage($user->id));
    }

    public function test_exceeded_limit_blocks_chat(): void
    {
        $user = $this->paidUser();
        $ids = $this->makeRegulations(1);
        $this->actingAs($user)->post(route('consultations.store'), ['regulation_ids' => $ids]);
        $session = ConsultationSession::firstOrFail();

        Setting::where('key', 'daily_token_limit')->update(['value' => '2']);
        TokenUsage::create(['user_id' => $user->id, 'date' => today()->toDateString(), 'tokens_used' => 2, 'source' => 'test']);

        $this->actingAs($user)
            ->postJson(route('consultations.chat.ask', $session), ['question' => 'A?'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Batas token harian (2) tercapai. Tersisa 0 token. Coba lagi besok.');
    }
}
