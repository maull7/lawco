<?php

namespace App\Http\Controllers;

use App\Http\Requests\Regulation\StoreRegulationRequest;
use App\Http\Requests\Regulation\UpdateRegulationRequest;
use App\Jobs\ExtractRegulationReferences;
use App\Jobs\GenerateRegulationAiResult;
use App\Jobs\GenerateRegulationAnalysis;
use App\Jobs\ParseRegulation;
use App\Jobs\ParseRegulationDocument;
use App\Models\AiJobStatus;
use App\Models\AiPrompt;
use App\Models\Regulation;
use App\Models\RegulationChatMessage;
use App\Models\RegulationDocument;
use App\Models\UserActivityLog;
use App\Repositories\RegulationRepository;
use App\Services\RegulationAnalysisService;
use App\Services\RegulationParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegulationController extends Controller
{
    public function __construct(
        private readonly RegulationRepository $regulationRepository,
        private readonly RegulationParserService $regulationParserService,
        private readonly RegulationAnalysisService $regulationAnalysisService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'search_content', 'year', 'type_id', 'category_id', 'sector_id', 'sort', 'direction']);
        $regulations = $this->regulationRepository->paginateWithFilters($filters);
        $filterOptions = $this->regulationRepository->getFilterOptions();

        return view('regulations.index', compact('regulations', 'filterOptions', 'filters'));
    }

    public function create(): View
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);

        $options = $this->regulationRepository->getFormOptions();

        return view('regulations.create', $options);
    }

    public function store(StoreRegulationRequest $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('upload_regulations'), 403);

        $data = $request->validated();

        $filePath = $request->file('file')->store('regulations', 'public');

        $regulation = Regulation::create([
            'regulation_number' => $data['regulation_number'],
            'title' => $data['title'],
            'regulation_type_id' => $data['regulation_type_id'],
            'category_id' => $data['category_id'] ?? null,
            'year' => $data['year'],
            'effective_date' => $data['effective_date'] ?? null,
            'file_path' => $filePath,
            'tanggal_tetapkan' => $data['tanggal_tetapkan'] ?? null,
            'tanggal_diundangkan' => $data['tanggal_diundangkan'] ?? null,
        ]);

        if (! empty($data['sub_categories'])) {
            $regulation->subCategories()->sync($data['sub_categories']);
        }

        if (! empty($data['related_regulations'])) {
            $regulation->relatedRegulations()->sync($data['related_regulations']);
        }

        $documentsInput = $data['documents'] ?? [];
        foreach ($documentsInput as $i => $docData) {
            $file = $request->file("documents.{$i}.file");
            if ($file) {
                $docPath = $file->store('regulation-documents', 'public');
                RegulationDocument::create([
                    'regulation_id' => $regulation->id,
                    'name' => $docData['name'],
                    'document_type' => $docData['document_type'],
                    'file_path' => $docPath,
                ]);
            }
        }

        UserActivityLog::log('created', Regulation::class, $regulation->id, "Menambahkan regulasi {$regulation->regulation_number} - {$regulation->title}");

        return redirect()->route('regulations.show', $regulation)
            ->with('success', 'Regulasi berhasil ditambahkan.');
    }

    public function show(Regulation $regulation): View
    {
        $regulation = $this->regulationRepository->findByIdWithRelations($regulation->id);
        $aiPrompt = AiPrompt::all();
        $chatMessages = RegulationChatMessage::where('regulation_id', $regulation->id)
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(100)
            ->get()
            ->reverse();

        return view('regulations.show', compact('regulation', 'aiPrompt', 'chatMessages'));
    }

    public function generateAi(Request $request, Regulation $regulation): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('upload_regulations'), 403);

        $validated = $request->validate([
            'ai_prompt_id' => ['required', 'integer', 'exists:ai_prompts,id'],
        ]);

        $prompt = AiPrompt::findOrFail($validated['ai_prompt_id']);

        AiJobStatus::begin($regulation, 'regulation-ai');
        GenerateRegulationAiResult::dispatch($regulation, $prompt);

        UserActivityLog::log('generated', Regulation::class, $regulation->id, "Menjalankan Generate AI ({$prompt->title}) untuk regulasi {$regulation->regulation_number}");

        return redirect()->route('regulations.show', [$regulation, 'tab' => 'short-review'])
            ->with('info', 'Generate AI sedang diproses di background (queue). Halaman akan refresh otomatis saat selesai.');
    }

    public function edit(Regulation $regulation): View
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);

        $options = $this->regulationRepository->getFormOptions();
        $regulation->load(['subCategories', 'relatedRegulations.type']);

        return view('regulations.edit', array_merge($options, compact('regulation')));
    }

    public function update(UpdateRegulationRequest $request, Regulation $regulation): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);
        $data = $request->validated();

        $updateData = [
            'regulation_number' => $data['regulation_number'],
            'title' => $data['title'],
            'regulation_type_id' => $data['regulation_type_id'],
            'category_id' => $data['category_id'] ?? null,
            'year' => $data['year'],
            'effective_date' => $data['effective_date'] ?? null,
            'tanggal_tetapkan' => $data['tanggal_tetapkan'] ?? null,
            'tanggal_diundangkan' => $data['tanggal_diundangkan'] ?? null,
        ];

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($regulation->file_path);
            $updateData['file_path'] = $request->file('file')->store('regulations', 'public');
        }

        $regulation->update($updateData);
        $regulation->subCategories()->sync($data['sub_categories'] ?? []);
        $regulation->relatedRegulations()->sync($data['related_regulations'] ?? []);

        UserActivityLog::log('updated', Regulation::class, $regulation->id, "Memperbarui regulasi {$regulation->regulation_number} - {$regulation->title}");

        return redirect()->route('regulations.show', $regulation)
            ->with('success', 'Regulasi berhasil diperbarui.');
    }

    public function analyze(Regulation $regulation): View
    {
        $regulation = $this->regulationRepository->findByIdWithRelations($regulation->id);

        $analysis = $this->regulationAnalysisService->analyze($regulation);

        return view('regulations.analyze', compact('regulation', 'analysis'));
    }

    public function generateAnalysis(Regulation $regulation): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);
        AiJobStatus::begin($regulation, 'analysis');
        GenerateRegulationAnalysis::dispatch($regulation);

        UserActivityLog::log('generated', Regulation::class, $regulation->id, "Menghasilkan analisis AI untuk regulasi {$regulation->regulation_number}");

        return redirect()->route('regulations.analyze', $regulation)
            ->with('info', 'Analisis sedang diproses di background. Halaman akan refresh otomatis saat selesai.');
    }

    public function saveAnalysis(Regulation $regulation): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);
        $result = $this->regulationAnalysisService->saveAnalysis($regulation);

        if (! $result) {
            return redirect()->route('regulations.analyze', $regulation)
                ->with('error', 'Tidak ada analisis untuk disimpan.');
        }

        UserActivityLog::log('saved', Regulation::class, $regulation->id, "Menyimpan analisis AI untuk regulasi {$regulation->regulation_number}");

        return redirect()->route('regulations.analyze', $regulation)
            ->with('success', 'Analisis berhasil disimpan.');
    }

    public function connectReferences(Request $request, Regulation $regulation): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);
        $validated = $request->validate([
            'reference_ids' => ['required', 'array'],
            'reference_ids.*' => ['integer', 'exists:regulation_analysis_references,id'],
        ]);

        $connected = $this->regulationAnalysisService->connectReferences(
            $regulation,
            $validated['reference_ids'],
        );

        if ($connected > 0) {
            UserActivityLog::log('connected', Regulation::class, $regulation->id, "Menghubungkan {$connected} referensi regulasi ke {$regulation->regulation_number}");
        }

        $message = $connected > 0
            ? "{$connected} referensi berhasil dihubungkan."
            : 'Tidak ada referensi baru yang dihubungkan.';

        return redirect()->route('regulations.analyze', $regulation)
            ->with($connected > 0 ? 'success' : 'info', $message);
    }

    public function analyzeBabs(Request $request, Regulation $regulation): JsonResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);
        $results = $this->regulationAnalysisService->analyzeByBabs($regulation);

        return response()->json($results);
    }

    public function analyzeSingleBab(Request $request, Regulation $regulation, int $index): JsonResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);
        $result = $this->regulationAnalysisService->analyzeBabByIndex($regulation, $index);

        return response()->json($result);
    }

    public function babList(Regulation $regulation): JsonResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);
        $text = $this->regulationAnalysisService->getContentText($regulation);
        if (! $text) {
            return response()->json(['babs' => []]);
        }

        $babs = $this->regulationAnalysisService->splitTextToBabs($text);

        return response()->json([
            'babs' => array_map(fn ($b) => ['label' => $b['label']], $babs),
        ]);
    }

    public function reanalyze(Regulation $regulation): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);
        AiJobStatus::begin($regulation, 'analysis');
        GenerateRegulationAnalysis::dispatch($regulation, true);

        UserActivityLog::log('reanalyzed', Regulation::class, $regulation->id, "Melakukan re-analisis AI untuk regulasi {$regulation->regulation_number}");

        return redirect()->route('regulations.analyze', $regulation)
            ->with('info', 'Re-analisis sedang diproses di background. Halaman akan refresh otomatis saat selesai.');
    }

    public function destroy(Regulation $regulation): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);

        Storage::disk('public')->delete($regulation->file_path);

        foreach ($regulation->documents as $document) {
            Storage::disk('public')->delete($document->file_path);
        }

        $number = $regulation->regulation_number;
        $regulation->delete();

        UserActivityLog::log('deleted', Regulation::class, $regulation->id, "Menghapus regulasi {$number}");

        return redirect()->route('regulations.index')
            ->with('success', 'Regulasi berhasil dihapus.');
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $excludeId = $request->get('exclude_id');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = $this->regulationRepository->search($query, $excludeId ? (int) $excludeId : null);

        return response()->json($results->map(fn (Regulation $r) => [
            'id' => $r->id,
            'regulation_number' => $r->regulation_number,
            'title' => $r->title,
            'year' => $r->year,
            'type_name' => $r->type?->name,
        ]));
    }

    public function uploadDocument(Request $request, Regulation $regulation): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('upload_regulations'), 403);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,docx,doc,xlsx,xls,pptx,ppt', 'max:20480'],
        ]);

        $filePath = $request->file('file')->store('regulation-documents', 'public');

        RegulationDocument::create([
            'regulation_id' => $regulation->id,
            'name' => $request->input('name'),
            'document_type' => $request->input('document_type'),
            'file_path' => $filePath,
        ]);

        UserActivityLog::log('uploaded', Regulation::class, $regulation->id, "Mengunggah dokumen {$request->input('name')} ke regulasi {$regulation->regulation_number}");

        return redirect()->route('regulations.show', $regulation)
            ->with('success', 'Dokumen tambahan berhasil diunggah.');
    }

    public function deleteDocument(RegulationDocument $document): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);

        $regulation = $document->regulation;

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        UserActivityLog::log('deleted', Regulation::class, $regulation->id, "Menghapus dokumen {$document->name} dari regulasi {$regulation->regulation_number}");

        return redirect()->route('regulations.show', $regulation)
            ->with('success', 'Dokumen tambahan berhasil dihapus.');
    }

    public function updateDocument(Request $request, RegulationDocument $document): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('upload_regulations'), 403);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:pdf,docx,doc,xlsx,xls,pptx,ppt', 'max:20480'],
        ]);

        $updateData = [
            'name' => $request->input('name'),
            'document_type' => $request->input('document_type'),
        ];

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($document->file_path);
            $updateData['file_path'] = $request->file('file')->store('regulation-documents', 'public');
        }

        $document->update($updateData);

        $regulation = $document->regulation;

        UserActivityLog::log('updated', Regulation::class, $regulation->id, "Memperbarui dokumen {$document->name} dari regulasi {$regulation->regulation_number}");

        return redirect()->back()->with('success', 'Dokumen berhasil diperbarui.');
    }

    public function viewDocument(RegulationDocument $document): StreamedResponse
    {
        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
        $contentType = match ($extension) {
            'pdf' => 'application/pdf',
            'docx', 'doc' => 'application/msword',
            'xlsx', 'xls' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx', 'ppt' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/octet-stream',
        };

        return Storage::disk('public')->response($document->file_path, null, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline',
        ]);
    }

    public function viewDocumentParsedText(RegulationDocument $document): View
    {
        abort_unless($document->isParsed(), 404);

        return view('regulations.document-parsed-text', compact('document'));
    }

    public function viewFile(Regulation $regulation): StreamedResponse
    {
        return Storage::disk('public')->response($regulation->file_path, null, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function viewer(Regulation $regulation): View
    {
        abort_unless($regulation->file_path, 404);

        return view('regulations.viewer', compact('regulation'));
    }

    public function parseRegulation(Regulation $regulation): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);

        if ($regulation->parse_status === 'complete') {
            return redirect()->route('regulations.show', $regulation)
                ->with('info', 'Regulasi sudah diparse lengkap.');
        }

        $regulation->update(['parse_status' => 'parsing', 'parse_progress' => 0, 'parse_error' => null]);

        Cache::forget("parse_cancel:regulation:{$regulation->id}");
        $regulation->documents->each(fn ($d) => Cache::forget("parse_cancel:document:{$d->id}"));

        ParseRegulation::dispatch($regulation);

        UserActivityLog::log('parsed', Regulation::class, $regulation->id, "Memproses parse teks regulasi {$regulation->regulation_number}");

        return redirect()->route('regulations.show', $regulation)
            ->with('success', 'Parse regulasi sedang diproses di background. Silakan refresh halaman untuk melihat hasil.');
    }

    public function parseDocument(Regulation $regulation, RegulationDocument $document): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);

        try {
            $result = $this->regulationParserService->parseDocument($document);
        } catch (\Throwable $e) {
            $document->update(['parse_status' => 'failed', 'parse_progress' => null, 'parse_error' => mb_substr($e->getMessage(), 0, 500)]);

            return redirect()->route('regulations.show', $regulation)
                ->with('error', 'Parse dokumen gagal: '.$e->getMessage());
        }

        if (! $result['success']) {
            $document->update(['parse_status' => 'failed', 'parse_progress' => null, 'parse_error' => mb_substr($result['message'], 0, 500)]);

            return redirect()->route('regulations.show', $regulation)
                ->with('error', $result['message']);
        }

        $document->fresh()?->update(['parse_error' => null]);

        UserActivityLog::log('parsed', Regulation::class, $regulation->id, "Melakukan parse dokumen {$document->name} dari regulasi {$regulation->regulation_number}");

        return redirect()->route('regulations.show', $regulation)
            ->with('success', $result['message']);
    }

    public function parseAllDocuments(Regulation $regulation): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);

        $regulation->load('documents');
        $pending = $regulation->documents->reject(fn ($d) => $d->isParsed());

        if ($pending->isEmpty()) {
            return redirect()->route('regulations.show', $regulation)
                ->with('info', 'Semua dokumen tambahan sudah diparse.');
        }

        $count = $pending->count();

        foreach ($pending as $document) {
            $document->update(['parse_status' => 'parsing', 'parse_progress' => 0, 'parse_error' => null]);
            Cache::forget("parse_cancel:document:{$document->id}");
            ParseRegulationDocument::dispatch($document);
        }

        UserActivityLog::log('parsed', Regulation::class, $regulation->id, "Memproses {$count} dokumen tambahan dari regulasi {$regulation->regulation_number}");

        return redirect()->route('regulations.show', $regulation)
            ->with('success', "Memproses {$count} dokumen tambahan secara background. Silakan refresh halaman untuk melihat hasil.");
    }

    public function parseProgress(Regulation $regulation): JsonResponse
    {
        $regulation->loadMissing('documents');

        return response()->json([
            'regulation' => [
                'progress' => $regulation->parse_progress,
                'status' => $regulation->parse_status,
                'error' => $regulation->parse_error,
                'parsed_at' => $regulation->parsed_at?->toIso8601String(),
            ],
            'documents' => $regulation->documents->map(fn ($d) => [
                'id' => $d->id,
                'progress' => $d->parse_progress,
                'status' => $d->parse_status,
                'error' => $d->parse_error,
            ]),
        ]);
    }

    public function cancelParse(Regulation $regulation): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);

        Cache::put("parse_cancel:regulation:{$regulation->id}", true, now()->addHour());

        foreach ($regulation->documents as $document) {
            Cache::put("parse_cancel:document:{$document->id}", true, now()->addHour());
            $document->fresh()?->update(['parse_status' => 'not_parsed', 'parse_progress' => null, 'parse_error' => null]);
        }

        $regulation->update(['parse_status' => 'not_parsed', 'parse_progress' => null, 'parse_error' => null]);

        UserActivityLog::log('parsed', Regulation::class, $regulation->id, "Membatalkan parse regulasi {$regulation->regulation_number}");

        return redirect()->route('regulations.show', $regulation)
            ->with('info', 'Parse regulasi dibatalkan.');
    }

    public function cancelDocumentParse(Regulation $regulation, RegulationDocument $document): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);

        Cache::put("parse_cancel:document:{$document->id}", true, now()->addHour());
        $document->update(['parse_status' => 'not_parsed', 'parse_progress' => null, 'parse_error' => null]);

        return redirect()->route('regulations.show', $regulation)
            ->with('info', "Parse dokumen {$document->name} dibatalkan.");
    }

    public function extractReferences(Regulation $regulation): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('upload_regulations'), 403);

        AiJobStatus::begin($regulation, 'extract');
        ExtractRegulationReferences::dispatch($regulation);

        UserActivityLog::log('extracted', Regulation::class, $regulation->id, "Memproses ekstraksi peraturan terkait dari {$regulation->regulation_number}");

        return redirect()->route('regulations.show', $regulation)
            ->with('info', 'Ekstraksi peraturan terkait sedang diproses di background. Halaman akan refresh otomatis saat selesai.');
    }
}
