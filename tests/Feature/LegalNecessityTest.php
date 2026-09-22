<?php

namespace Tests\Feature;

use App\Models\LegalNecessity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalNecessityTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_check_form_submission_persists_legal_necessity(): void
    {
        $response = $this->post(route('legal-necessities.store'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+628123456789',
            'legal_activities' => 'reksa_dana',
            'status_company' => 'tertutup',
            'value_trx' => 'low',
            'target_output' => 'review',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('legal_necessities', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+628123456789',
            'legal_activities' => 'reksa_dana',
            'status_company' => 'tertutup',
            'value_trx' => 'low',
            'target_output' => 'review',
        ]);

        $this->assertSame(1, LegalNecessity::count());
    }

    public function test_quick_check_form_rejects_missing_fields(): void
    {
        $response = $this->post(route('legal-necessities.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'phone']);

        $this->assertSame(0, LegalNecessity::count());
    }

    public function test_consultation_form_submission_persists_legal_necessity(): void
    {
        $response = $this->post(route('legal-necessities.store'), [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '+628987654321',
            'legal_activities' => 'Reksa Dana',
            'message' => 'Butuh pendampingan emisi obligasi.',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('legal_necessities', [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '+628987654321',
            'legal_activities' => 'Reksa Dana',
            'message' => 'Butuh pendampingan emisi obligasi.',
        ]);
    }

    public function test_landing_page_shows_consultation_form(): void
    {
        $this->get(route('index-dash'))
            ->assertOk()
            ->assertSee('Konsultasi Hukum')
            ->assertSee('Permasalahan Hukum');
    }

    public function test_admin_can_view_legal_necessity_requests(): void
    {
        LegalNecessity::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+628123456789',
            'legal_activities' => 'reksa_dana',
            'message' => 'Butuh review prospektus.',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('legal-necessities.index'))
            ->assertOk()
            ->assertSee('John Doe')
            ->assertSee('Butuh review prospektus.');
    }

    public function test_regular_user_cannot_view_legal_necessity_requests(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('legal-necessities.index'))
            ->assertForbidden();
    }
}
