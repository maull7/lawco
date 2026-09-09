<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'description', 'is_active', 'is_public'])]
class Sector extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['is_public' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_public' => 'boolean'];
    }

    /** @return HasMany<RegulationCategory> */
    public function categories(): HasMany
    {
        return $this->hasMany(RegulationCategory::class, 'sector_id');
    }
}
