<?php

namespace App\Http\Controllers;

use App\Http\Requests\Regulation\StartAutomaticParseRequest;
use App\Http\Requests\Regulation\UpdateAutomaticParseScheduleRequest;
use App\Models\Sector;
use App\Services\AutomaticRegulationParseService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AutomaticRegulationParseController extends Controller
{
    public function settings(Request $request, AutomaticRegulationParseService $service): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('automatic-parse.settings', [
            'sectors' => Sector::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedSector' => $service->scheduledSector(),
            'selectedSectorId' => $service->scheduledSectorId(),
        ]);
    }

    public function updateSettings(UpdateAutomaticParseScheduleRequest $request, AutomaticRegulationParseService $service): RedirectResponse
    {
        $service->saveScheduledSector((int) $request->validated('sector_id'));

        return redirect()->route('regulations.automatic-parse.settings')
            ->with('success', 'Sektor parse otomatis pukul 20.00 WIB berhasil disimpan.');
    }

    public function index(Request $request, AutomaticRegulationParseService $service): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('automatic-parse.index', ['history' => $service->history()]);
    }

    public function show(Request $request, string $batchId, AutomaticRegulationParseService $service): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $history = $service->historyDetail($batchId);
        $page = max(1, $request->integer('page', 1));
        $documents = new LengthAwarePaginator(array_slice($history['documents'], ($page - 1) * 50, 50), count($history['documents']), 50, $page, ['path' => $request->url()]);

        return view('automatic-parse.show', ['batch' => $history['batch'], 'documents' => $documents]);
    }

    public function store(StartAutomaticParseRequest $request, AutomaticRegulationParseService $service): RedirectResponse
    {
        try {
            $batch = $service->start((int) $request->validated('limit'), userId: $request->user()->id, sectorId: $request->filled('sector_id') ? (int) $request->validated('sector_id') : null);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['limit' => 'Permintaan parse lain sedang diproses. Coba kembali.']);
        }

        return redirect()->route('regulations.index')->with($batch ? 'success' : 'info', $batch
            ? 'Parse otomatis dimulai untuk '.$batch->totalJobs.' regulasi.'
            : 'Tidak ada regulasi belum diparse dengan PDF tersedia.');
    }
}
