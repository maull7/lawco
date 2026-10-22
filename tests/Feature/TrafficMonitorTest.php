<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TrafficMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_traffic_monitor(): void
    {
        Config::set('services.looker_studio.traffic_report_url', 'https://datastudio.google.com/embed/reporting/test');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('traffic-monitor.index'))
            ->assertOk()
            ->assertSee('Monitor Traffic Website')
            ->assertSee('https://datastudio.google.com/embed/reporting/test', false);
    }

    public function test_non_admin_cannot_view_the_traffic_monitor(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('traffic-monitor.index'))
            ->assertForbidden();
    }

    public function test_sub_admin_cannot_view_the_traffic_monitor(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'sub_admin']))
            ->get(route('traffic-monitor.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('traffic-monitor.index'))
            ->assertRedirect(route('login'));
    }

    public function test_traffic_menu_is_only_shown_to_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Monitor Traffic');

        foreach (['sub_admin', 'user'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee('Monitor Traffic');
        }
    }
}
