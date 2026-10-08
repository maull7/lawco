<?php

namespace Tests\Feature;

use App\Jobs\SyncJdihRegulations;
use App\Models\JdihTarget;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class ManualJdihSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        JdihTarget::query()->update(['is_active' => false]);
    }

    public function test_admin_and_sub_admin_can_queue_all_active_sources(): void
    {
        $first = JdihTarget::factory()->create();
        $second = JdihTarget::factory()->create();
        JdihTarget::factory()->create(['is_active' => false]);
        Queue::fake();
        foreach (['admin', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post(route('scraping-failures.run-sync'))->assertRedirect(route('scraping-failures.index'))
                ->assertSessionHas($role === 'admin' ? 'success' : 'info');
        }
        Queue::assertPushed(SyncJdihRegulations::class, 2);
        foreach ([$first, $second] as $target) {
            Queue::assertPushed(SyncJdihRegulations::class, fn (SyncJdihRegulations $job): bool => $job->source === $target->source && $job->limit === 0 && $job->documentIds === null
                && $job->fromFolder && $job->connection === 'redis' && $job->queue === 'jdih');
        }
    }

    public function test_selected_source_is_queued_without_other_sources(): void
    {
        $target = JdihTarget::factory()->create();
        JdihTarget::factory()->create();
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'sub_admin']))
            ->post(route('scraping-failures.run-sync'), ['source' => $target->source])
            ->assertSessionHas('success');
        Queue::assertPushed(SyncJdihRegulations::class, 1);
        Queue::assertPushed(SyncJdihRegulations::class, fn (SyncJdihRegulations $job): bool => $job->source === $target->source);
    }

    public function test_guests_and_regular_users_cannot_trigger_sync(): void
    {
        Queue::fake();
        $this->post(route('scraping-failures.run-sync'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->post(route('scraping-failures.run-sync'))->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_unknown_inactive_and_invalid_sources_are_rejected(): void
    {
        $inactive = JdihTarget::factory()->create(['is_active' => false]);
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([$inactive->source, 'not-found', ['invalid']] as $source) {
            $this->postJson(route('scraping-failures.run-sync'), ['source' => $source])
                ->assertUnprocessable()->assertJsonValidationErrors('source');
        }
        Queue::assertNothingPushed();
    }

    public function test_no_active_sources_returns_a_clear_message_without_dispatching(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('scraping-failures.run-sync'))
            ->assertSessionHas('error', 'Belum ada sumber JDIH aktif untuk disinkronkan.');
        Queue::assertNothingPushed();
    }

    public function test_scheduled_and_manual_requests_share_the_unique_job_lock(): void
    {
        $target = JdihTarget::factory()->create();
        Queue::fake();
        $event = collect($this->app->make(Schedule::class)->events())->firstWhere('description', 'jdih-sync-active-targets');
        $event->run($this->app);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('scraping-failures.run-sync'), ['source' => $target->source])->assertSessionHas('info');
        Queue::assertPushed(SyncJdihRegulations::class, 1);
    }

    public function test_manual_dispatch_also_prevents_duplicate_scheduled_dispatch(): void
    {
        JdihTarget::factory()->create();
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('scraping-failures.run-sync'))->assertSessionHas('success');
        $event = collect($this->app->make(Schedule::class)->events())->firstWhere('description', 'jdih-sync-active-targets');
        $event->run($this->app);
        Queue::assertPushed(SyncJdihRegulations::class, 1);
    }

    public function test_queue_failure_releases_lock_so_the_source_can_be_submitted_again(): void
    {
        $target = JdihTarget::factory()->create();
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('scraping-failures.run-sync'), ['source' => $target->source])
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, '1 sumber gagal dimasukkan'));
        Bus::shouldReceive('dispatch')->once()->andReturn('queued-id');
        $this->post(route('scraping-failures.run-sync'), ['source' => $target->source])->assertSessionHas('success');
    }

    public function test_partial_failure_keeps_successful_dispatches_and_reports_counts(): void
    {
        $first = JdihTarget::factory()->create();
        $second = JdihTarget::factory()->create();
        Bus::shouldReceive('dispatch')->once()->withArgs(fn (SyncJdihRegulations $job): bool => $job->source === $first->source)->andReturn('queued-id');
        Bus::shouldReceive('dispatch')->once()->withArgs(fn (SyncJdihRegulations $job): bool => $job->source === $second->source)->andThrow(new RuntimeException('Queue unavailable'));
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('scraping-failures.run-sync'))->assertSessionHas('error', fn (string $message): bool => str_contains($message, '1 sumber dimasukkan') && str_contains($message, '1 sumber gagal dimasukkan'));
    }
}
