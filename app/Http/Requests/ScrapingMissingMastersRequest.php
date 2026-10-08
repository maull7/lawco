<?php

namespace App\Http\Requests;

class ScrapingMissingMastersRequest extends ScrapingFailureIndexRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->user()->hasPermission('manage_types')
            && $this->user()->hasPermission('manage_categories');
    }
}
