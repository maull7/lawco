<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScrapingFailureIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isSubAdmin();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['masters', 'needs_review'])],
            'q' => ['nullable', 'string', 'max:200'],
            'review_page' => ['nullable', 'integer', 'min:1'],
            'sector_id' => ['nullable', 'integer', Rule::exists('sectors', 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.string' => 'Pencarian harus berupa teks.',
            'q.max' => 'Pencarian maksimal 200 karakter.',
            'sector_id.integer' => 'Pilih sektor yang tersedia.',
            'sector_id.exists' => 'Sektor yang dipilih tidak tersedia.',
        ];
    }
}
