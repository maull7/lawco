<?php

namespace App\Http\Requests\RegulationType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRegulationTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_types') ?? false;
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
