<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class DownloadedLegalDecision extends Model
{
    public const MISSING_PDF_IDS_CACHE_KEY = 'legal-decisions:missing-pdf-ids';

    protected static function booted(): void
    {
        $forgetMissingPdfIds = fn () => Cache::forget(self::MISSING_PDF_IDS_CACHE_KEY);

        static::saved($forgetMissingPdfIds);
        static::deleted($forgetMissingPdfIds);
    }

    protected $fillable = [
        'user_id',
        'decision_number',
        'title',
        'court_name',
        'classification',
        'year',
        'tgl_register',
        'tgl_putus',
        'tgl_upload',
        'summary',
        'source_url',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'download_type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
