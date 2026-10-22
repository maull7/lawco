<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.turnstile.site_key' => 'site-test', 'services.turnstile.secret_key' => 'secret-test']);
        Http::preventStrayRequests();
    }

    public function test_login_page_renders_widget(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('data-sitekey="site-test"', false);
    }

    public function test_login_without_token_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password123'])
            ->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_login_with_failed_token_is_rejected(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['success' => false])]);
        $user = User::factory()->create(['password' => 'password123']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password123', 'cf-turnstile-response' => 'bad'])
            ->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
    }

    public function test_login_with_valid_token_passes(): void
    {
        Http::fake([self::VERIFY_URL => Http::response(['success' => true])]);
        $user = User::factory()->create(['password' => 'password123']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password123', 'cf-turnstile-response' => 'good'])
            ->assertSessionDoesntHaveErrors();
        $this->assertAuthenticatedAs($user);
        Http::assertSent(fn (Request $request) => $request['secret'] === 'secret-test' && $request['response'] === 'good');
    }

    public function test_register_without_token_is_rejected(): void
    {
        $this->post(route('register'), [
            'name' => 'Uji', 'email' => 'uji@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseMissing('users', ['email' => 'uji@example.com']);
    }

    public function test_cloudflare_outage_rejects_instead_of_crashing(): void
    {
        Http::fake([self::VERIFY_URL => Http::failedConnection()]);
        $user = User::factory()->create(['password' => 'password123']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password123', 'cf-turnstile-response' => 'x'])
            ->assertSessionHasErrors('cf-turnstile-response');
    }
}
