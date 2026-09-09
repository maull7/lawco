<?php

namespace App\Http\Controllers;

use App\Enums\ReviewStatus;
use App\Http\Requests\ReviewDocument\StoreReviewDocumentRequest;
use App\Http\Requests\ReviewDocument\UpdateReviewDocumentRequest;
use App\Models\ReviewDocument;
use App\Models\UserActivityLog;
use App\Repositories\RegulationCategoryRepository;
use App\Repositories\ReviewDocumentRepository;
use App\Services\ReviewDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewDocumentController extends Controller
{
    public function __construct(
        private readonly ReviewDocumentRepository $reviewDocumentRepository,
        private readonly RegulationCategoryRepository $categoryRepository,
        private readonly ReviewDocumentService $reviewDocumentService
    ) {}

    public function index(Request $request): View
    {
        abort_if($request->user()->isSubAdmin(), 403);
        $this->authorize('viewAny', ReviewDocument::class);

        $filters = $request->only(['status', 'search']);

        if (! $request->user()->isAdmin() && ! $request->user()->isSubAdmin() && ! $request->user()->isReviewer()) {
            $filters['user_id'] = $request->user()->id;
        }

        $documents = $this->reviewDocumentRepository->search($filters);
        $statuses = ReviewStatus::cases();
        $aiTypes = [
            'analisa' => 'Analisa',
            'review' => 'Review',
            'rekomendasi' => 'Rekomendasi',
            'validitas' => 'Validitas',
        ];

        return view('review-documents.index', compact('documents', 'statuses', 'aiTypes'));
    }

    public function create(): View
    {
        abort_if(auth()->user()->isSubAdmin(), 403);
        $this->authorize('create', ReviewDocument::class);

        $categories = $this->categoryRepository->allWithRegulations();
        $categories->load(['sector', 'subCategories', 'regulations.subCategories']);

        return view('review-documents.create', compact('categories'));
    }

    public function store(StoreReviewDocumentRequest $request): RedirectResponse
    {
        abort_if($request->user()->isSubAdmin(), 403);
        $this->authorize('create', ReviewDocument::class);

        $this->reviewDocumentService->createReviewDocument(
            $request->safe()->only(['title', 'description']),
            $request->validated('regulation_ids'),
            $request->file('file'),
            $request->user()->id
        );

        UserActivityLog::log('created', ReviewDocument::class, null, "Mengunggah dokumen review {$request->input('title')}");

        return redirect()->route('review-documents.index')
            ->with('success', 'Document uploaded successfully.');
    }

    public function show(ReviewDocument $reviewDocument): View
    {
        abort_if(auth()->user()->isSubAdmin(), 403);
        $this->authorize('view', $reviewDocument);

        $document = $this->reviewDocumentRepository->findById($reviewDocument->id);

        return view('review-documents.show', compact('document'));
    }

    public function edit(ReviewDocument $reviewDocument): View
    {
        abort_if(auth()->user()->isSubAdmin(), 403);
        $this->authorize('update', $reviewDocument);

        $categories = $this->categoryRepository->allWithRegulations();

        return view('review-documents.edit', compact('reviewDocument', 'categories'));
    }

    public function update(UpdateReviewDocumentRequest $request, ReviewDocument $reviewDocument): RedirectResponse
    {
        abort_if($request->user()->isSubAdmin(), 403);
        $this->authorize('update', $reviewDocument);

        $this->reviewDocumentService->updateReviewDocument(
            $reviewDocument,
            $request->safe()->only(['title', 'description']),
            $request->validated('regulation_ids'),
            $request->file('file')
        );

        UserActivityLog::log('updated', ReviewDocument::class, $reviewDocument->id, "Memperbarui dokumen review {$reviewDocument->title}");

        return redirect()->route('review-documents.show', $reviewDocument)
            ->with('success', 'Document updated successfully.');
    }

    public function destroy(ReviewDocument $reviewDocument): RedirectResponse
    {
        abort_if(request()->user()->isSubAdmin(), 403);
        $this->authorize('delete', $reviewDocument);

        $title = $reviewDocument->title;
        $this->reviewDocumentService->deleteReviewDocument($reviewDocument);

        UserActivityLog::log('deleted', ReviewDocument::class, null, "Menghapus dokumen review {$title}");

        return redirect()->route('review-documents.index')
            ->with('success', 'Document deleted successfully.');
    }

    public function submit(ReviewDocument $reviewDocument): RedirectResponse
    {
        abort_if(request()->user()->isSubAdmin(), 403);
        $this->authorize('submit', $reviewDocument);

        $this->reviewDocumentService->submitForReview($reviewDocument);

        UserActivityLog::log('submitted', ReviewDocument::class, $reviewDocument->id, "Mengirim dokumen review {$reviewDocument->title} untuk ditinjau");

        return redirect()->route('review-documents.show', $reviewDocument)
            ->with('success', 'Document submitted for review.');
    }

    public function viewFile(ReviewDocument $reviewDocument): StreamedResponse
    {
        abort_if(auth()->user()->isSubAdmin(), 403);
        $this->authorize('view', $reviewDocument);

        return Storage::disk('public')->response($reviewDocument->file_path, null, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function viewer(ReviewDocument $reviewDocument): View
    {
        abort_if(auth()->user()->isSubAdmin(), 403);
        $this->authorize('view', $reviewDocument);

        return view('review-documents.viewer', compact('reviewDocument'));
    }
}
