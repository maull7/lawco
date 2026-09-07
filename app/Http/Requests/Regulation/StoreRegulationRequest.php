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
                    ->where(fn ($query) => $query->where('sector_id', $this->input('sector_id'))),
            ],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
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

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'regulation_number.required' => 'Nomor regulasi wajib diisi.',
            'title.required' => 'Judul regulasi wajib diisi.',
            'regulation_type_id.required' => 'Jenis regulasi wajib dipilih.',
            'sector_id.required' => 'Sektor regulasi wajib dipilih.',
            'category_id.exists' => 'Kategori tidak sesuai dengan sektor yang dipilih.',
            'file.required' => 'File regulasi wajib diunggah.',
            'file.file' => 'File regulasi tidak berhasil dibaca oleh server. Silakan pilih ulang file.',
            'file.mimes' => 'File regulasi harus berformat PDF.',
            'file.max' => 'Ukuran file regulasi terlalu besar. Maksimal 20 MB.',
            'file.uploaded' => 'Upload file regulasi gagal. Periksa batas upload server dan coba lagi.',
            'documents.*.name.required' => 'Nama dokumen tambahan wajib diisi.',
            'documents.*.document_type.required' => 'Jenis dokumen tambahan wajib diisi.',
            'documents.*.file.required' => 'File dokumen tambahan wajib dipilih.',
            'documents.*.file.file' => 'File dokumen tambahan tidak berhasil dibaca oleh server.',
            'documents.*.file.mimes' => 'Format dokumen tambahan tidak didukung.',
            'documents.*.file.max' => 'Ukuran dokumen tambahan maksimal 20 MB.',
            'documents.*.file.uploaded' => 'Upload dokumen tambahan gagal. Periksa batas upload server.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'regulation_number' => 'nomor regulasi',
            'regulation_type_id' => 'jenis regulasi',
            'sector_id' => 'sektor',
            'effective_date' => 'tanggal berlaku',
            'tanggal_tetapkan' => 'tanggal ditetapkan',
            'tanggal_diundangkan' => 'tanggal diundangkan',
            'file' => 'file regulasi',
        ];
    }
}
