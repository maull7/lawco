<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['regulation_number', 'title', 'regulation_type_id', 'category_id', 'year', 'effective_date', 'file_path', 'parsed_at', 'parse_status', 'parsed_text', 'parse_stats', 'parse_progress', 'parse_error', 'tanggal_tetapkan', 'tanggal_diundangkan', 'created_by'])]
class Regulation extends Model
{
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<User, Regulation> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<RegulationType, Regulation> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(RegulationType::class, 'regulation_type_id');
    }

    /** @return BelongsTo<RegulationCategory, Regulation> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(RegulationCategory::class, 'category_id');
    }

    /** @return BelongsToMany<SubCategory> */
    public function subCategories(): BelongsToMany
    {
        return $this->belongsToMany(SubCategory::class, 'regulation_sub_category');
    }

    /** @return BelongsToMany<Regulation> */
    public function relatedRegulations(): BelongsToMany
    {
        return $this->belongsToMany(
            Regulation::class,
            'regulation_related',
            'regulation_id',
            'related_regulation_id',
        );
    }

    /** @return HasMany<RegulationDocument> */
    public function documents(): HasMany
    {
        return $this->hasMany(RegulationDocument::class);
    }

    /** @return HasMany<RegulationRelatedReference, Regulation> */
    public function relatedReferences(): HasMany
    {
        return $this->hasMany(RegulationRelatedReference::class);
    }

    /** @return HasMany<RegulationAiResult, Regulation> */
    public function aiResults(): HasMany
    {
        return $this->hasMany(RegulationAiResult::class);
    }

    public function aiStatus(string $action): ?AiJobStatus
    {
        return AiJobStatus::where('model_type', $this->getMorphClass())
            ->where('model_id', $this->getKey())
            ->where('action', $action)
            ->first();
    }

    public function isAiProcessing(string $action): bool
    {
        return ($this->aiStatus($action)?->status ?? null) === 'processing';
    }

    public function isParsed(): bool
    {
        return $this->parsed_at !== null;
    }

    public function documentsParseProgress(): array
    {
        $total = $this->documents->count();
        $parsed = $this->documents->filter(fn ($d) => $d->isParsed())->count();

        return [
            'total' => $total,
            'parsed' => $parsed,
            'pending' => $total - $parsed,
            'percentage' => $total > 0 ? round(($parsed / $total) * 100) : 0,
        ];
    }

    public function searchOccurrenceCount(string $keyword): int
    {
        $exact = str_starts_with($keyword, '"') && str_ends_with($keyword, '"');
        $term = $keyword;

        if ($exact) {
            $term = trim(mb_substr($keyword, 1, -1));
        }

        $count = 0;

        foreach ([$this->parsed_text, ...$this->documents->pluck('parsed_text')] as $text) {
            if (! $text) {
                continue;
            }

            $count += $this->countInText($text, $term, $exact);
        }

        return $count;
    }

    private function countInText(string $text, string $term, bool $exact): int
    {
        if (! $exact) {
            return mb_substr_count(mb_strtolower($text), mb_strtolower($term));
        }

        $escaped = preg_quote(preg_replace('/\s+/u', ' ', trim($term)), '/');

        return preg_match_all("/\b{$escaped}\b/iu", $text) ?: 0;
    }

    public function parseStatusLabel(): string
    {
        return match ($this->parse_status) {
            'complete' => 'Complete',
            'incomplete' => 'InComplete',
            'parsing' => 'Parsing',
            'failed' => 'Failed',
            default => 'Not Parsed',
        };
    }

    public function parseStatusBadgeColor(): string
    {
        return match ($this->parse_status) {
            'complete' => 'emerald',
            'incomplete' => 'amber',
            'parsing' => 'blue',
            'failed' => 'rose',
            default => 'gray',
        };
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'effective_date' => 'date',
            'tanggal_tetapkan' => 'date',
            'tanggal_diundangkan' => 'date',
            'parsed_at' => 'datetime',
            'parse_stats' => 'array',
            'parse_progress' => 'integer',
        ];
    }
}
