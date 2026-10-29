<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadedLegalDecision extends Model
{
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
