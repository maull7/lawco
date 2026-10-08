<?php

namespace App\Http\Requests\JdihDocumentReview;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJdihDocumentReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:64'],
            'document_id' => ['required', 'string', 'max:255'],
            'type_mode' => ['required', Rule::in(['existing', 'new'])],
            'regulation_type_id' => ['exclude_unless:type_mode,existing', 'required', 'integer', Rule::exists('regulation_types', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'new_type_name' => ['exclude_unless:type_mode,new', 'required', 'string', 'max:255'],
            'level' => ['exclude_unless:type_mode,new', 'nullable', 'integer', 'min:1', 'max:5'],
            'category_id' => ['nullable', 'integer', Rule::exists('regulation_categories', 'id')->whereNull('deleted_at')],
            'action' => ['required', Rule::in(['save', 'import'])],
        ];
    }
}
