<?php

namespace App\Http\Requests\JdihTarget;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJdihTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_categories') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'source' => [
                'required', 'string', 'max:64', 'alpha_dash:ascii',
                Rule::unique('jdih_targets', 'source')->ignore($this->route('jdih_target')),
            ],
            'target_url' => ['required', 'url:http,https', 'max:2048'],
            'sector_id' => ['required', Rule::exists('sectors', 'id')->whereNull('deleted_at')],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
