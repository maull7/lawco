<?php

namespace App\Services;

use App\Jobs\SyncJdihRegulations;
use App\Models\JdihTarget;
use Illuminate\Bus\UniqueLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ManualJdihSync
{
    /** @return array{queued: int, busy: int, failed: int, total: int} */
    public function run(?string $source, int $userId): array
    {
        $sources = JdihTarget::query()->where('is_active', true)
            ->when($source !== null, fn (Builder $query): Builder => $query->where('source', $source))
            ->orderBy('id')->pluck('source')->unique();
        $counts = ['queued' => 0, 'busy' => 0, 'failed' => 0, 'total' => $sources->count()];
        $lock = new UniqueLock(Cache::store());
        foreach ($sources as $targetSource) {
            $job = new SyncJdihRegulations($targetSource, 0, null, true);
            $acquired = false;
            try {
                $acquired = $lock->acquire($job);
                if (! $acquired) {
                    $counts['busy']++;

                    continue;
                }
                Bus::dispatch($job);
                $counts['queued']++;
            } catch (Throwable $exception) {
                if ($acquired) {
                    $lock->release($job);
                }
                report($exception);
                $counts['failed']++;
            }
        }
        Log::channel('single')->info('Sinkronisasi JDIH dipicu manual dari folder scraper.', [
            'user_id' => $userId, 'sources' => $sources->all(), 'from_folder' => true, 'counts' => $counts,
        ]);

        return $counts;
    }
}
