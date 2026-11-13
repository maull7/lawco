<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IntegrationRegisterTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/integrations/register';

    private const SECRET = 'rahasia-project-utama-minimal-32-karakter';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.integrations.keys' => ['project-utama' => self::SECRET]]);
        Notification::fake();
    }

    public function test_rejects_missing_or_invalid_token(): void
    {
        $payload = ['name' => 'John Doe', 'email' => 'john@example.com', 'password_hash' => Hash::make('password123')];

        $this->postJson(self::URL, $payload)->assertStatus(401)->assertJson(['success' => false]);
        $this->withToken('salah')->postJson(self::URL, $payload)->assertStatus(401)->assertJson(['success' => false]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_rejects_invalid_data(): void
    {
        $this->withToken(self::SECRET)
            ->postJson(self::URL, ['name' => '', 'email' => 'bukan-email', 'password_hash' => 'password123'])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'Validasi gagal'])
            ->assertJsonValidationErrors(['name', 'email', 'password_hash']);
    }

    public function test_registers_new_user_without_logging_password(): void
    {
        Log::spy();

        $this->withToken(self::SECRET)
            ->postJson(self::URL, ['name' => 'John Doe', 'email' => 'John@Example.com', 'password_hash' => Hash::make('password123')])
            ->assertStatus(201)
            ->assertExactJson([
                'success' => true,
                'message' => 'Akun berhasil didaftarkan',
                'data' => ['name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'registered'],
            ]);

        $user = User::where('email', 'john@example.com')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertTrue($user->hasVerifiedEmail());
        Notification::assertNothingSent();
        Log::shouldHaveReceived('info')->withArgs(
            fn ($message, $context) => $message === 'integration.register'
                && $context['client'] === 'project-utama'
                && ! str_contains(json_encode($context), '$2y$')
        )->once();
    }

    public function test_rejects_bcrypt_cost_higher_than_local_config(): void
    {
        $this->withToken(self::SECRET)
            ->postJson(self::URL, ['name' => 'John Doe', 'email' => 'john@example.com', 'password_hash' => '$2y$31$'.str_repeat('a', 53)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password_hash']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_email_is_not_duplicated_or_overwritten(): void
    {
        $existing = User::factory()->create(['email' => 'john@example.com', 'name' => 'Lama', 'password' => 'passwordlama']);

        $this->withToken(self::SECRET)
            ->postJson(self::URL, ['name' => 'John Doe', 'email' => 'john@example.com', 'password_hash' => Hash::make('password123')])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Akun sudah terdaftar', 'data' => ['status' => 'already_registered', 'name' => 'Lama']]);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($existing->email_verified_at?->toJSON(), $existing->fresh()->email_verified_at?->toJSON());
        $this->assertTrue(Hash::check('passwordlama', $existing->fresh()->password));
        Notification::assertNothingSent();
    }
}
