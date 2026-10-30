<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'sub_admin', 'reviewer', 'user'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in([
                'upload_regulations',
                'manage_categories',
                'manage_types',
                'manage_sub_categories',
                'manage_prompts',
                'access_legal_decisions',
            ])],
        ];
    }
}
