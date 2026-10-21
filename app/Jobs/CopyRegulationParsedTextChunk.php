<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class CopyRegulationParsedTextChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue;

    public $queue = 'default';

    public $timeout = 300;

    public $tries = 3;

    /** @param array<int, int> $regulationIds */
    public function __construct(public array $regulationIds) {}

    public function handle(): void
    {
        $now = now();

        $rows = DB::table('regulations')
            ->whereIn('id', $this->regulationIds)
            ->whereNotNull('parsed_text')
            ->get(['id', 'parsed_text'])
            ->map(fn (object $regulation) => [
                'regulation_id' => $regulation->id,
                'parsed_text' => $regulation->parsed_text,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('regulation_texts')->upsert($rows, ['regulation_id'], ['parsed_text', 'updated_at']);
        }
    }
}
