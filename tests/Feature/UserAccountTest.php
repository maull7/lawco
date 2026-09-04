<?php

namespace Tests\Feature;

use App\Enums\ReviewStatus;
use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\ReviewDocument;
use App\Models\Sector;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class UserAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_is_logged_in(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'End User',
            'email' => 'user@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertRedirect(route('verification.notice'));

        $this->get(route('verification.notice'))->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
            'role' => 'user',
            'email_verified_at' => null,
        ]);

        $user = User::where('email', 'user@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotEquals('secret-password', $user->password);
        $this->assertTrue(password_verify('secret-password', $user->password));

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registered_user_redirected_to_verification_until_verified(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'End User',
            'email' => 'user@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'user@example.com')->firstOrFail();
        $this->get(route('verification.notice'))->assertOk();

        $this->post(route('verification.send'));

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verification_link_fulfills_and_unlocks_profile_flow(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'End User',
            'email' => 'user@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $user = User::where('email', 'user@example.com')->firstOrFail();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
        );

        $this->get($url)->assertRedirect(route('profile.edit'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->post(route('profile.update'), [
            'institution' => 'PT Contoh Investasi',
            'position' => 'Compliance Officer',
            'province' => config('provinces')[0],
            'phone' => '081234567890',
        ]);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_verification_link_works_from_other_tab_when_not_logged_in(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => null,
        ]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
        );

        $this->assertGuest();

        $this->get($url)->assertRedirect(route('profile.edit'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_email_is_pushed_to_queue(): void
    {
        Queue::fake();

        $this->post('/register', [
            'name' => 'End User',
            'email' => 'user@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        Queue::assertPushed(SendQueuedNotifications::class);
    }

    public function test_unverified_user_can_request_resend_link_from_login(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => null,
        ]);

        $this->assertGuest();

        $this->post(route('verification.send'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Belum menerima email aktivasi?')
            ->assertSee($user->email, false);
    }

    public function test_resend_link_for_unknown_email_returns_generic_message(): void
    {
        Notification::fake();

        $this->post(route('verification.send'), ['email' => 'tidak-ada@example.com'])
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_register_validates_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post('/register', [
            'name' => 'End User',
            'email' => 'taken@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_register_validates_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'name' => 'End User',
            'email' => 'user@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_unverified_user_cannot_login(): void
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => null,
            'password' => $password,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertSessionHasErrors('email')->assertSessionHas('unverified');

        $this->assertGuest();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Belum menerima email aktivasi?')
            ->assertSee($user->email, false);
    }

    public function test_verified_user_can_login(): void
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'role' => 'user',
            'password' => $password,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_can_login_without_verified_email(): void
    {
        $password = 'secret-password';
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => $password,
            'email_verified_at' => null,
        ]);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => $password,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_sub_admin_can_login_and_access_without_verified_email(): void
    {
        $password = 'secret-password';
        $subAdmin = User::factory()->create([
            'role' => 'sub_admin',
            'password' => $password,
            'email_verified_at' => null,
            'permissions' => ['upload_regulations'],
        ]);

        $this->post('/login', [
            'email' => $subAdmin->email,
            'password' => $password,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($subAdmin);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_user_document_index_only_shows_own_documents(): void
    {
        $other = User::factory()->create(['role' => 'user']);
        $owner = User::factory()->create(['role' => 'user']);

        ReviewDocument::create(['user_id' => $other->id, 'title' => 'Dokumen Lain', 'file_path' => 'review-documents/fixture.pdf']);
        ReviewDocument::create(['user_id' => $owner->id, 'title' => 'Dokumen Saya', 'file_path' => 'review-documents/fixture.pdf']);

        $response = $this->actingAs($owner)->get(route('review-documents.index'));

        $response->assertOk();
        $response->assertSee('Dokumen Saya');
        $response->assertDontSee('Dokumen Lain');
    }

    public function test_user_cannot_view_other_users_document(): void
    {
        $other = User::factory()->create(['role' => 'user']);
        $owner = User::factory()->create(['role' => 'user']);

        $document = ReviewDocument::create([
            'user_id' => $other->id,
            'title' => 'Dokumen Rahasia',
            'file_path' => 'review-documents/fixture.pdf',
            'status' => ReviewStatus::Submitted->value,
        ]);

        $this->actingAs($owner)->get(route('review-documents.show', $document))->assertForbidden();
        $this->actingAs($owner)->get(route('review-documents.view-file', $document))->assertForbidden();
        $this->actingAs($owner)->get(route('review-documents.viewer', $document))->assertForbidden();
    }

    public function test_user_can_open_own_document_viewer(): void
    {
        $owner = User::factory()->create(['role' => 'user']);

        $document = ReviewDocument::create([
            'user_id' => $owner->id,
            'title' => 'Dokumen Saya',
            'file_path' => 'review-documents/fixture.pdf',
            'status' => ReviewStatus::Draft->value,
        ]);

        $this->actingAs($owner)->get(route('review-documents.viewer', $document))->assertOk();
    }

    public function test_user_cannot_create_regulation(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->post('/regulations', [
            'regulation_number' => 'PP-99',
            'title' => 'Hack Regulasi',
            'regulation_type_id' => 1,
            'year' => 2026,
        ])->assertForbidden();

        $this->assertDatabaseMissing('regulations', ['title' => 'Hack Regulasi']);
    }

    public function test_admin_can_create_regulation_without_category_or_sub_category(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $type = RegulationType::create(['name' => 'Peraturan', 'level' => 1]);
        $sector = Sector::create(['name' => 'Umum']);

        $response = $this->actingAs($admin)->post(route('regulations.store'), [
            'regulation_number' => 'PP-99',
            'title' => 'Regulasi Tanpa Kategori',
            'regulation_type_id' => $type->id,
            'sector_id' => $sector->id,
            'year' => 2026,
            'file' => UploadedFile::fake()->create('regulation.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('regulations', [
            'title' => 'Regulasi Tanpa Kategori',
            'category_id' => null,
        ]);
    }

    public function test_user_can_read_regulation_list(): void
    {
        Regulation::create([
            'regulation_number' => 'PP-1',
            'title' => 'Regulasi Terbuka',
            'year' => 2026,
            'file_path' => 'regulations/fixture.pdf',
            'regulation_type_id' => RegulationType::create(['name' => 'Peraturan', 'level' => 1])->id,
            'category_id' => RegulationCategory::create(['name' => 'Kategori Umum'])->id,
        ]);

        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('regulations.index'))->assertOk()
            ->assertSee('Regulasi Terbuka');
    }
}
