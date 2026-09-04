<?php

namespace App\Http\Requests\Regulation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('upload_regulations');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'regulation_number' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'regulation_type_id' => ['required', 'exists:regulation_types,id'],
            'category_id' => ['nullable', 'exists:regulation_categories,id'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'effective_date' => ['nullable', 'date'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'sub_categories' => ['nullable', 'array'],
            'sub_categories.*' => ['exists:sub_categories,id'],
            'related_regulations' => ['nullable', 'array'],
            'related_regulations.*' => ['exists:regulations,id'],
            'tanggal_tetapkan' => ['nullable', 'date'],
            'tanggal_diundangkan' => ['nullable', 'date'],
        ];
    }
}
