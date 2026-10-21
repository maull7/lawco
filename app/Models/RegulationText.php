<?php

namespace App\Models;

use Database\Factories\RegulationTextFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['regulation_id', 'parsed_text'])]
class RegulationText extends Model
{
    /** @use HasFactory<RegulationTextFactory> */
    use HasFactory;

    /** @return BelongsTo<Regulation, RegulationText> */
    public function regulation(): BelongsTo
    {
        return $this->belongsTo(Regulation::class);
    }
}
