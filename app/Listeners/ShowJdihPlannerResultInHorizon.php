<?php

namespace App\Listeners;

use App\Jobs\SyncJdihRegulations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Events\JobDeleted;

class ShowJdihPlannerResultInHorizon
{
    public function handle(JobDeleted $event): void
    {
        if (($event->payload->decoded['data']['commandName'] ?? null) !== SyncJdihRegulations::class || $event->job->hasFailed()) {
            return;
        }

        $result = Cache::pull('jdih-planner-result:'.$event->payload->id());
        if (is_string($result)) {
            Redis::connection('horizon')->hset($event->payload->id(), 'name', $result);
        }
    }
}
