<?php

namespace App\Http\Requests\JdihDocumentReview;

use App\Http\Requests\ScrapingFailureIndexRequest;

class IndexJdihDocumentReviewRequest extends ScrapingFailureIndexRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }
}
