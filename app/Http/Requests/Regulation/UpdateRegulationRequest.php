<?php

namespace App\Http\Requests\Regulation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'title' => ['required', 'string'],
            'regulation_type_id' => ['required', 'exists:regulation_types,id'],
            'sector_id' => ['required', 'exists:sectors,id'],
            'category_id' => [
                'nullable',
                Rule::exists('regulation_categories', 'id')
                    ->where(fn($query) => $query->where('sector_id', $this->input('sector_id'))),
            ],
            'year' => ['required', 'integer', 'max:' . (date('Y') + 1)],
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

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sector_id.required' => 'Sektor wajib dipilih terlebih dahulu.',
            'category_id.exists' => 'Kategori tidak sesuai dengan sektor yang dipilih.',
            'file.file' => 'File regulasi tidak berhasil dibaca oleh server. Silakan pilih ulang file.',
            'file.mimes' => 'File regulasi harus berformat PDF.',
            'file.max' => 'Ukuran file regulasi terlalu besar. Maksimal 20 MB.',
            'file.uploaded' => 'Upload file regulasi gagal. Ukuran file melebihi batas server atau koneksi terputus. Maksimal 20 MB.',
        ];
    }
}
