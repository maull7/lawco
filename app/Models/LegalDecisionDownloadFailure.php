<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalDecisionDownloadFailure extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_url',
        'url_hash',
        'decision_number',
        'error_message',
        'retry_count',
        'last_failed_at',
    ];

    protected function casts(): array
    {
        return [
            'retry_count' => 'integer',
            'last_failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Catat atau perbarui kegagalan download berdasarkan source_url.
     */
    public static function recordFailure(string $url, ?string $errorMessage = null, ?string $decisionNumber = null, ?int $userId = null): self
    {
        $hash = md5($url);

        $record = static::query()->where('url_hash', $hash)->first();

        if ($record) {
            $record->update([
                'user_id' => $userId ?? $record->user_id,
                'decision_number' => $decisionNumber ?: $record->decision_number,
                'error_message' => $errorMessage ?: $record->error_message,
                'retry_count' => $record->retry_count + 1,
                'last_failed_at' => now(),
            ]);

            return $record;
        }

        return static::create([
            'user_id' => $userId,
            'source_url' => $url,
            'url_hash' => $hash,
            'decision_number' => $decisionNumber,
            'error_message' => $errorMessage,
            'retry_count' => 1,
            'last_failed_at' => now(),
        ]);
    }

    /**
     * Hapus jejak kegagalan saat berhasil diunduh.
     */
    public static function clearFailure(string $url): void
    {
        static::query()->where('url_hash', md5($url))->delete();
    }
}
