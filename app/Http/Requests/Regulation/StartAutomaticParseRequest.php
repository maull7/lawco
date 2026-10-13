<?php

namespace App\Http\Requests\Regulation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartAutomaticParseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'limit' => ['required', 'integer', 'min:1', 'max:'.config('automatic-parse.maximum_limit')],
            'sector_ids' => ['nullable', 'array', 'list'],
            'sector_ids.*' => ['required', 'integer', 'distinct', Rule::exists('sectors', 'id')->where('is_active', true)],
        ];
    }
}
