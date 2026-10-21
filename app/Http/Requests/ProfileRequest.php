<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'institution' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', Rule::in(config('provinces'))],
            'phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'institution.required' => 'Institusi wajib diisi.',
            'position.required' => 'Jabatan wajib diisi.',
            'province.required' => 'Asal provinsi wajib dipilih.',
            'province.in' => 'Provinsi tidak valid.',
            'phone.required' => 'No. telepon wajib diisi.',
            'phone.regex' => 'Format nomor telepon tidak valid (contoh: 081234567890).',
        ];
    }
}
