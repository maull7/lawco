<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScrapingReviewDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isSubAdmin();
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:255'],
            'document_id' => ['required', 'string', 'max:255'],
        ];
    }
}
