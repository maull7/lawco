<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class AiTokenLog extends Model
{
    protected $fillable = ['user_id', 'source', 'provider', 'model', 'tokens'];

    /** @return BelongsTo<User, AiTokenLog> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Catat pemakaian token satu panggilan AI; tidak boleh menggagalkan panggilan AI.
     *
     * @param  array{provider: string, model: string, total_tokens: int}  $result
     */
    public static function recordResult(array $result): void
    {
        try {
            static::create([
                'user_id' => auth()->id(),
                'source' => app()->runningInConsole()
                    ? 'console/queue'
                    : (request()->route()?->getName() ?? request()->path()),
                'provider' => $result['provider'],
                'model' => $result['model'],
                'tokens' => max(0, (int) $result['total_tokens']),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
