<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Regulation;
use App\Models\RegulationRelatedReference;
use App\Models\Review;
use App\Models\ReviewDocument;
use App\Models\Sector;
use App\Repositories\RegulationRepository;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AiService $aiService,
        private readonly RegulationRepository $regulationRepository,
    ) {}

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

        if (! $user->isUser()) {
            $recentDocuments = $documentsQuery->with('user')->latest()->take(5)->get();
        } else {
            $recentDocuments = $documentsQuery->with('user')->where('user_id', $user->id)->latest()->take(5)->get();
        }
        $data = [...compact('stats', 'recentDocuments'), ...$this->regulationTables($request)];

        if ($request->routeIs('dashboard.search')) {
            $validated = $request->validate([
                'q' => ['required', 'string', 'min:2', 'max:500'],
            ]);
            $data['aiQuery'] = $validated['q'];

            try {
                $data['aiResults'] = $this->aiService->searchRegulationsWithHermes($validated['q']);
            } catch (\Throwable $e) {
                report($e);
                $data['aiResults'] = collect();
                $data['aiError'] = 'Maaf, pencarian AI (Hermes) sedang tidak dapat dihubungi. Coba lagi beberapa saat.';
            }
        }

        return view('dashboard.index', $data);
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
        return view('index-dashboard', $this->landingData($request));
    }

    public function search(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:500'],
        ]);

        $data = $this->landingData($request);
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
    private function landingData(Request $request): array
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

        $publicSectors = Sector::where('is_active', true)->where('is_public', true)->orderBy('name')->get();

        $packages = Package::where('is_active', true)->orderBy('sort')->orderBy('id')->get();

        return [...compact('stats', 'recentDocuments', 'publicSectors', 'packages'), ...$this->regulationTables($request)];
    }

    /**
     * Data for the shared "Peraturan Terkini" (filterable) and "Peraturan Terkait"
     * (narrowed by the ?related= regulation id) tables.
     *
     * @return array<string, mixed>
     */
    private function regulationTables(Request $request): array
    {
        $regulationFilters = array_filter($request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'search_content' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer'],
            'type_id' => ['nullable', 'integer'],
            'category_keyword' => ['nullable', 'string', 'max:255'],
        ]), fn ($value) => $value !== null && $value !== '');
        $columns = ['regulations.id', 'regulation_number', 'title', 'year', 'regulation_type_id', 'category_id', 'created_at'];

        $latestRegulations = $regulationFilters
            ? $this->regulationRepository->filteredQuery($regulationFilters, $columns)->with(['type', 'category'])->take(10)->get()
            : Regulation::with(['type', 'category'])->select($columns)
                ->orderByDesc('year')->orderByDesc('regulations.id')
                ->take(5)->get();

        $relatedRegulation = $request->filled('related') ? Regulation::find($request->integer('related')) : null;

        $regulationRelated = RegulationRelatedReference::with('regulation:id,regulation_number')
            ->when($relatedRegulation, fn ($query) => $query->where('regulation_id', $relatedRegulation->id))
            ->latest()
            ->when(! $relatedRegulation, fn ($query) => $query->take(5))
            ->get();

        $filterOptions = $this->regulationRepository->getFilterOptions();

        return compact('latestRegulations', 'regulationFilters', 'regulationRelated', 'relatedRegulation', 'filterOptions');
    }
}
