<?php

namespace Tests\Feature;

use App\Jobs\SyncJdihRegulations;
use App\Models\JdihTarget;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JdihScheduledSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_schedule_runs_at_one_am_jakarta_in_production(): void
    {
        $event = $this->syncEvent();
        $this->assertSame('0 1 * * *', $event->expression);
        $this->assertSame('Asia/Jakarta', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(['production'], $event->environments);
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setTime(1, 0));
        $this->assertTrue($event->isDue($this->app));
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setTime(2, 0));
        $this->assertFalse($event->isDue($this->app));
        $this->app->detectEnvironment(fn (): string => 'testing');
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setTime(1, 0));
        $this->assertFalse($event->isDue($this->app));
    }

    public function test_schedule_dispatches_folder_planners_for_active_sources_only(): void
    {
        JdihTarget::query()->update(['is_active' => false]);
        JdihTarget::create(['name' => 'Active Source', 'source' => 'active_source', 'is_active' => true]);
        Queue::fake();
        $this->syncEvent()->run($this->app);
        Queue::assertPushed(SyncJdihRegulations::class, 1);
        Queue::assertPushed(SyncJdihRegulations::class, fn (SyncJdihRegulations $job): bool => $job->source === 'active_source' && $job->fromFolder && $job->documentIds === null);
    }

    public function test_schedule_with_no_active_targets_does_not_dispatch_jobs(): void
    {
        JdihTarget::query()->update(['is_active' => false]);
        Queue::fake();
        $this->syncEvent()->run($this->app);
        Queue::assertNothingPushed();
    }

    private function syncEvent(): CallbackEvent
    {
        return collect($this->app->make(Schedule::class)->events())
            ->first(fn (object $event): bool => $event->description === 'jdih-sync-active-targets');
    }
}
