<?php

namespace App\Http\Requests\RegulationCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRegulationCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_categories') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'sector_id' => ['nullable', 'integer', Rule::exists('sectors', 'id')->whereNull('deleted_at')],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
