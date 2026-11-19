<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Helpers\DocumentStorage;
use App\Models\ReviewDocument;
use App\Repositories\ReviewDocumentRepository;
use Illuminate\Http\UploadedFile;

class ReviewDocumentService
{
    public function __construct(
        private readonly ReviewDocumentRepository $reviewDocumentRepository
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $regulationIds
     */
    public function createReviewDocument(array $data, array $regulationIds, UploadedFile $file, int $userId): ReviewDocument
    {
        $data['user_id'] = $userId;
        $data['file_path'] = $this->uploadFile($file);

        $document = $this->reviewDocumentRepository->create($data);
        $document->regulations()->attach($regulationIds);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $regulationIds
     */
    public function updateReviewDocument(ReviewDocument $document, array $data, array $regulationIds, ?UploadedFile $file = null): ReviewDocument
    {
        if ($file) {
            $this->deleteFile($document->file_path);
            $data['file_path'] = $this->uploadFile($file);
        }

        $document = $this->reviewDocumentRepository->update($document, $data);
        $document->regulations()->sync($regulationIds);

        return $document->fresh(['user', 'regulations.type', 'regulations.category']);
    }

    public function deleteReviewDocument(ReviewDocument $document): bool
    {
        $this->deleteFile($document->file_path);

        return $this->reviewDocumentRepository->delete($document);
    }

    public function submitForReview(ReviewDocument $document): ReviewDocument
    {
        return $this->reviewDocumentRepository->updateStatus(
            $document,
            ReviewStatus::Submitted->value,
            now()
        );
    }

    public function updateStatus(ReviewDocument $document, ReviewStatus $status): ReviewDocument
    {
        return $this->reviewDocumentRepository->updateStatus($document, $status->value);
    }

    private function uploadFile(UploadedFile $file): string
    {
        return $file->store('review-documents', DocumentStorage::diskName());
    }

    private function deleteFile(?string $path): void
    {
        if ($path && DocumentStorage::disk()->exists($path)) {
            DocumentStorage::disk()->delete($path);
        }
    }
}
