<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Putusan yang PDF-nya gagal terunduh: tidak punya r2_path di metadata (dicek dari tabel, tanpa request ke R2).
     */
    #[Scope]
    protected function pdfMissing(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('metadata')->orWhereNull('metadata->r2_path')->orWhere('metadata->r2_path', ''));
    }

    /**
     * Putusan yang PDF-nya tercatat tersimpan di R2.
     */
    #[Scope]
    protected function pdfAvailable(Builder $query): void
    {
        $query->whereNotNull('metadata->r2_path')->where('metadata->r2_path', '!=', '');
    }

    /**
     * Apakah PDF putusan ini tercatat gagal terunduh (r2_path kosong).
     */
    public function isPdfMissing(): bool
    {
        return empty($this->metadata['r2_path']);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
