<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalDecision extends Model
{
    protected $fillable = [
        'decision_number',
        'title',
        'court_name',
        'classification',
        'verdict',
        'decision_date',
        'year',
        'summary',
        'file_url',
        'file_path',
        'source_url',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'decision_date' => 'date',
            'year' => 'integer',
        ];
    }
}
