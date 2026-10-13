<?php

namespace App\Http\Requests\Regulation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRegulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'search_content' => ['nullable', 'string'],
            'year' => ['nullable', 'integer'],
            'type_id' => ['nullable', 'integer'],
            'category_keyword' => ['nullable', 'string', 'max:255'],
            'sector_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string'],
            'parse_status' => ['nullable', 'string', Rule::in(['not_parsed', 'parsing', 'complete', 'incomplete', 'failed'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('start_date'), ['after_or_equal:start_date'])],
        ];
    }
}
