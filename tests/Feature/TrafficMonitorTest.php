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
}
