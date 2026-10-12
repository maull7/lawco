<?php

namespace App\Http\Requests\LegalDecision;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexLegalDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:250'],
            'type_id' => ['nullable', 'integer', Rule::exists('regulation_types', 'id')->whereNull('deleted_at')],
            'category_id' => ['nullable', 'integer', Rule::exists('regulation_categories', 'id')->whereNull('deleted_at')],
            'year' => ['nullable', 'integer', 'between:1,9999'],
        ];
    }
}
