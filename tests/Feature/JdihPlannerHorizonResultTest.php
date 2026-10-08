<?php

namespace Tests\Feature;

use App\Jobs\SyncJdihRegulations;
use App\Listeners\ShowJdihPlannerResultInHorizon;
use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Events\JobDeleted;
use Mockery;
use Tests\TestCase;

class JdihPlannerHorizonResultTest extends TestCase
{
    public function test_completed_planner_shows_result_in_horizon_and_clears_temporary_result(): void
    {
        $id = 'planner-result-test';
        $result = 'JDIH sync (jdih_pkp, planner) — Tidak ada PDF sumber.';
        Cache::put('jdih-planner-result:'.$id, $result, 60);
        $job = Mockery::mock(RedisJob::class);
        $job->shouldReceive('hasFailed')->once()->andReturn(false);
        $connection = Mockery::mock();
        $connection->shouldReceive('hset')->once()->with($id, 'name', $result);
        Redis::shouldReceive('connection')->once()->with('horizon')->andReturn($connection);
        $event = new JobDeleted($job, json_encode(['id' => $id, 'data' => ['commandName' => SyncJdihRegulations::class]]));

        (new ShowJdihPlannerResultInHorizon)->handle($event);

        $this->assertNull(Cache::get('jdih-planner-result:'.$id));
        $this->assertTrue(Event::hasListeners(JobDeleted::class));
    }

    public function test_other_jobs_do_not_change_horizon_names(): void
    {
        Redis::shouldReceive('connection')->never();
        $event = new JobDeleted(Mockery::mock(RedisJob::class), json_encode(['id' => 'other-job', 'data' => ['commandName' => 'OtherJob']]));

        (new ShowJdihPlannerResultInHorizon)->handle($event);

        $this->assertTrue(true);
    }
}
