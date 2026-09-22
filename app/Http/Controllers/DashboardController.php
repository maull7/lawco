<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Models\RegulationRelatedReference;
use App\Models\Review;
use App\Models\ReviewDocument;
use App\Models\Sector;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AiService $aiService) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $documentsQuery = ReviewDocument::query();
        $reviewsQuery = Review::query();

        if (! $user->isAdmin() && ! $user->isSubAdmin() && ! $user->isReviewer()) {
            $documentsQuery->where('user_id', $user->id);
            $reviewsQuery->whereHas('reviewDocument', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($user->isReviewer()) {
            $reviewsQuery->where('reviewer_id', $user->id);
        }

        $stats = [
            'total_documents' => $documentsQuery->count(),
            'pending_documents' => (clone $documentsQuery)->where('status', 'submitted')->count(),
            'approved_documents' => (clone $documentsQuery)->where('status', 'approved')->count(),
            'total_reviews' => $reviewsQuery->count(),
        ];

        $recentDocuments = $documentsQuery->with('user')->latest()->take(5)->get();

        $latestRegulations = Regulation::with(['type', 'category'])
            ->latest()
            ->take(5)
            ->get();

        $regulationRelated = RegulationRelatedReference::with('regulation')
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard.index', compact('stats', 'recentDocuments', 'latestRegulations', 'regulationRelated'));
    }

    public function compliance(Request $request): View
    {
        $user = $request->user();

        $documentsQuery = ReviewDocument::query();
        $reviewsQuery = Review::query();

        if (! $user->isAdmin() && ! $user->isSubAdmin() && ! $user->isReviewer()) {
            $documentsQuery->where('user_id', $user->id);
            $reviewsQuery->whereHas('reviewDocument', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($user->isReviewer()) {
            $reviewsQuery->where('reviewer_id', $user->id);
        }

        $stats = [
            'total_documents' => $documentsQuery->count(),
            'pending_documents' => (clone $documentsQuery)->where('status', 'submitted')->count(),
            'approved_documents' => (clone $documentsQuery)->where('status', 'approved')->count(),
            'total_reviews' => $reviewsQuery->count(),
        ];

        $recentDocuments = $documentsQuery->with('user')->latest()->take(5)->get();

        return view('dashboard.compliance', compact('stats', 'recentDocuments'));
    }

    public function landing(Request $request): View
    {
        return view('index-dashboard', $this->landingData());
    }

    public function search(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:500'],
        ]);

        $data = $this->landingData();
        $data['aiQuery'] = $validated['q'];

        try {
            $data['aiResults'] = $this->aiService->searchRegulations($validated['q']);
        } catch (\Throwable $e) {
            report($e);
            $data['aiResults'] = collect();
            $data['aiError'] = 'Maaf, pencarian AI sedang tidak dapat dihubungi. Coba lagi beberapa saat.';
        }

        return view('index-dashboard', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function landingData(): array
    {
        $documentsQuery = ReviewDocument::query();
        $reviewsQuery = Review::query();

        $stats = [
            'total_documents' => $documentsQuery->count(),
            'pending_documents' => (clone $documentsQuery)->where('status', 'submitted')->count(),
            'approved_documents' => (clone $documentsQuery)->where('status', 'approved')->count(),
            'total_reviews' => $reviewsQuery->count(),
        ];

        $recentDocuments = $documentsQuery->with('user')->latest()->take(5)->get();

        $latestRegulations = Regulation::with(['type', 'category'])
            ->latest()
            ->take(5)
            ->get();

        $regulationRelated = RegulationRelatedReference::with('regulation')
            ->latest()
            ->take(5)
            ->get();

        $publicSectors = Sector::where('is_active', true)->where('is_public', true)->orderBy('name')->get();

        return compact('stats', 'recentDocuments', 'latestRegulations', 'regulationRelated', 'publicSectors');
    }
}
