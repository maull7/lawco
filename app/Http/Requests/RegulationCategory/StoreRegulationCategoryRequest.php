<?php

namespace App\Http\Requests\RegulationCategory;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegulationCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('manage_categories');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sector_id' => ['nullable', 'exists:sectors,id'],
            'sub_categories' => ['nullable', 'array'],
            'sub_categories.*' => ['string', 'max:255'],
        ];
    }
}
