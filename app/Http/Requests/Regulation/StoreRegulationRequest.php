<?php

namespace App\Http\Requests\Regulation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegulationRequest extends FormRequest
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
            'title' => ['required', 'string'],
            'regulation_type_id' => ['required', 'exists:regulation_types,id'],
            'sector_id' => ['required', 'exists:sectors,id'],
            'category_id' => [
                'nullable',
                Rule::exists('regulation_categories', 'id')
                    ->where(fn($query) => $query->where('sector_id', $this->input('sector_id'))),
            ],
            'year' => ['required', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'effective_date' => ['nullable', 'date'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'sub_categories' => ['nullable', 'array'],
            'sub_categories.*' => ['exists:sub_categories,id'],
            'related_regulations' => ['nullable', 'array'],
            'related_regulations.*' => ['exists:regulations,id'],
            'documents' => ['nullable', 'array'],
            'documents.*.name' => ['required', 'string', 'max:255'],
            'documents.*.document_type' => ['required', 'string', 'max:255'],
            'documents.*.file' => ['required', 'file', 'mimes:pdf,docx,doc,xlsx,xls,pptx,ppt', 'max:20480'],
            'tanggal_tetapkan' => ['nullable', 'date'],
            'tanggal_diundangkan' => ['nullable', 'date'],
        ];
    }
}
