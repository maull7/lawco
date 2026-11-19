<?php

namespace Tests\Feature;

use App\Models\AiTokenLog;
use App\Models\User;
use App\Services\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TokenMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_call_is_recorded_with_tokens_and_user(): void
    {
        config(['ai.openai.api_key' => 'k', 'ai.openai.base_url' => 'https://ai.test/v1', 'ai.openai.model' => 'gpt-test']);
        Http::fake(['ai.test/*' => Http::response([
            'model' => 'gpt-test',
            'choices' => [['message' => ['content' => 'halo']]],
            'usage' => ['total_tokens' => 123],
        ])]);
        $user = User::factory()->create();

        $this->actingAs($user);
        app(AiClient::class)->complete(['messages' => [['role' => 'user', 'content' => 'hi']]]);

        $this->assertDatabaseHas('ai_token_logs', ['user_id' => $user->id, 'provider' => 'openai', 'tokens' => 123]);
    }

    public function test_admin_sees_recorded_tokens_and_non_admin_is_blocked(): void
    {
        AiTokenLog::create(['source' => 'ai.preview', 'provider' => 'openai', 'model' => 'gpt-test', 'tokens' => 4567]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('token-monitor.index'))
            ->assertOk()->assertSee('4.567')->assertSee('Ai Preview');

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('token-monitor.index'))->assertForbidden();
    }
}
