<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['source', 'document_id', 'regulation_type_id', 'reviewed_by'])]
class JdihDocumentReview extends Model
{
    use HasFactory;

    /** @return BelongsTo<RegulationType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(RegulationType::class, 'regulation_type_id');
    }
}
