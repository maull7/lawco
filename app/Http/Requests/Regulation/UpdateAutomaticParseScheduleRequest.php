<?php

namespace App\Http\Requests\Regulation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAutomaticParseScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'sector_ids' => ['required', 'array', 'list', 'min:1'],
            'sector_ids.*' => ['required', 'integer', 'distinct', Rule::exists('sectors', 'id')->where('is_active', true)],
        ];
    }
}
