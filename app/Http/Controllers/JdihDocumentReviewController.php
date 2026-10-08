<?php

namespace App\Http\Controllers;

use App\Http\Requests\JdihDocumentReview\IndexJdihDocumentReviewRequest;
use App\Http\Requests\JdihDocumentReview\StoreJdihDocumentReviewRequest;
use App\Jobs\SyncJdihRegulations;
use App\Models\JdihDocumentReview;
use App\Models\JdihTarget;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use App\Models\Sector;
use App\Services\JdihReviewDocuments;
use App\Services\SaveJdihDocumentReview;
use Illuminate\Bus\UniqueLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

class JdihDocumentReviewController extends Controller
{
    public function index(IndexJdihDocumentReviewRequest $request, JdihReviewDocuments $documents): View
    {
        $filters = $request->validated();
        $filters['tab'] = 'needs_review';
        $targets = JdihTarget::with('sector')->get()->keyBy('source');
        $review = $documents->listing($filters, $targets, (int) ($filters['review_page'] ?? 1), 'jdih-document-reviews.index');
        $sectors = Sector::orderBy('name')->get(['id', 'name']);
        $types = RegulationType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'level']);
        $categories = RegulationCategory::with('sector:id,name')->orderBy('name')->orderBy('id')->get(['id', 'name', 'sector_id']);
        $choices = JdihDocumentReview::with(['type', 'category'])->whereIn('source', collect($review['documents']->items())->pluck('source'))
            ->whereIn('document_id', collect($review['documents']->items())->pluck('id'))->get()
            ->keyBy(fn (JdihDocumentReview $choice): string => $choice->source.':'.$choice->document_id);

        return view('jdih-document-reviews.index', compact('filters', 'review', 'sectors', 'types', 'choices', 'categories'));
    }

    public function store(StoreJdihDocumentReviewRequest $request, SaveJdihDocumentReview $reviews): RedirectResponse
    {
        $data = $request->validated();
        $review = $reviews->save($data, $request->user()->id);
        if ($data['action'] === 'save') {
            return redirect()->route('jdih-document-reviews.index')->with('success', 'Pilihan jenis tersimpan. Sinkronisasi berikutnya akan memakai jenis ini.');
        }
        $job = new SyncJdihRegulations($review->source, 0, [$review->document_id], true);
        $lock = new UniqueLock(Cache::store());
        $acquired = false;
        try {
            $acquired = $lock->acquire($job);
            if (! $acquired) {
                return redirect()->route('jdih-document-reviews.index')->with('success', 'Pilihan tersimpan. Dokumen ini sudah mengantre atau sedang diimpor.');
            }
            Bus::dispatch($job);
        } catch (Throwable $exception) {
            if ($acquired) {
                $lock->release($job);
            }
            report($exception);

            return redirect()->route('jdih-document-reviews.index')->with('error', 'Pilihan jenis sudah tersimpan, tetapi impor gagal dimasukkan ke antrean. Silakan coba lagi.');
        }

        return redirect()->route('jdih-document-reviews.index')->with('success', 'Pilihan tersimpan dan dokumen dimasukkan ke antrean impor.');
    }
}
