<?php

namespace App\Http\Controllers;

use App\Http\Requests\Regulation\StartAutomaticExtractRequest;
use App\Services\AutomaticReferenceExtractService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AutomaticReferenceExtractController extends Controller
{
    public function index(Request $request, AutomaticReferenceExtractService $service): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('automatic-extract.index', ['history' => $service->history()]);
    }

    public function show(Request $request, string $batchId, AutomaticReferenceExtractService $service): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $history = $service->historyDetail($batchId);
        $page = max(1, $request->integer('page', 1));
        $documents = new LengthAwarePaginator(array_slice($history['documents'], ($page - 1) * 50, 50), count($history['documents']), 50, $page, ['path' => $request->url()]);

        return view('automatic-extract.show', ['batch' => $history['batch'], 'documents' => $documents]);
    }

    public function store(StartAutomaticExtractRequest $request, AutomaticReferenceExtractService $service): RedirectResponse
    {
        try {
            $batch = $service->start((int) $request->validated('limit'), userId: $request->user()->id, sectorIds: $request->validated('sector_ids') ?? []);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['limit' => 'Permintaan ekstrak lain sedang diproses. Coba kembali.']);
        }

        return redirect()->route('regulations.automatic-parse.settings')->with($batch ? 'success' : 'info', $batch
            ? 'Ekstrak peraturan terkait otomatis dimulai untuk '.$batch->options['run_total'].' regulasi dalam '.$batch->options['batch_count'].' batch.'
            : 'Tidak ada regulasi terparse yang belum diekstrak peraturan terkaitnya.');
    }
}
