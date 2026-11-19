<?php

namespace Tests\Feature;

use App\Models\AiTokenLog;
use App\Models\User;
use App\Services\AiClient;
use App\Services\AiRuntimeControl;
use App\Services\HermesClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
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

    public function test_admin_can_halt_and_reactivate_ai(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('token-monitor.index'))->assertSee('Status AI: Aktif')->assertSee('Halt');
        $this->post(route('token-monitor.halt'))->assertRedirect(route('token-monitor.index'));
        $this->assertDatabaseHas('settings', ['key' => 'ai_halted', 'value' => '1']);
        $this->get(route('token-monitor.index'))->assertSee('Status AI: Nonaktif')->assertSee('Aktifkan AI');
        $this->post(route('token-monitor.activate'))->assertRedirect(route('token-monitor.index'));
        $this->assertFalse(app(AiRuntimeControl::class)->isHalted());
        $this->get(route('token-monitor.index'))->assertSee('Status AI: Aktif');
    }

    public function test_non_admin_cannot_change_ai_status(): void
    {
        foreach (['user', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->post(route('token-monitor.halt'))->assertForbidden();
            $this->post(route('token-monitor.activate'))->assertForbidden();
        }
        $this->assertFalse(app(AiRuntimeControl::class)->isHalted());
    }

    public function test_guests_cannot_change_ai_status(): void
    {
        $this->post(route('token-monitor.halt'))->assertRedirect(route('login'));
        $this->post(route('token-monitor.activate'))->assertRedirect(route('login'));
        $this->assertFalse(app(AiRuntimeControl::class)->isHalted());
    }

    public function test_halt_blocks_all_ai_clients_and_activation_restores_requests(): void
    {
        config(['ai.openai.api_key' => 'k', 'ai.openai.base_url' => 'https://ai.test/v1']);
        Http::fake(['ai.test/*' => Http::response([
            'choices' => [['message' => ['content' => 'halo']]],
            'usage' => ['total_tokens' => 12],
        ])]);
        $control = app(AiRuntimeControl::class);
        $control->setHalted(true);
        $payload = ['messages' => [['role' => 'user', 'content' => 'hi']]];

        foreach ([app(AiClient::class), app(HermesClient::class)] as $client) {
            try {
                $client->complete($payload);
                $this->fail('AI must be blocked while halted.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('AI sedang dinonaktifkan', $exception->getMessage());
            }
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_token_logs', 0);

        $control->setHalted(false);
        $this->assertSame('halo', app(AiClient::class)->complete($payload)['content']);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('ai_token_logs', 1);
    }
}
