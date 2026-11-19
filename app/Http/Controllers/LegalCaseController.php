<?php

namespace App\Http\Controllers;

use App\Helpers\DocumentStorage;
use App\Jobs\GenerateCaseAnalysis;
use App\Models\AiJobStatus;
use App\Models\LegalCase;
use App\Models\UserActivityLog;
use App\Services\DocumentParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalCaseController extends Controller
{
    public function __construct(private readonly DocumentParser $documentParser) {}

    public function index(): View
    {
        $cases = LegalCase::with('regulations')
            ->latest()
            ->paginate(15);

        return view('legal-cases.index', compact('cases'));
    }

    public function create(): View
    {
        return view('legal-cases.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'case_number' => ['nullable', 'string', 'max:255'],
            'court' => ['nullable', 'string', 'max:255'],
            'status_case' => ['required', 'in:ongoing,finished'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $case = LegalCase::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'case_number' => $validated['case_number'] ?? null,
            'court' => $validated['court'] ?? null,
            'status_case' => $validated['status_case'],
            'file_path' => $request->file('file')->store('legal-cases', DocumentStorage::diskName()),
        ]);

        $this->parseCase($case);

        UserActivityLog::log('created', LegalCase::class, $case->id, "Menambahkan analisa kasus {$case->title}");

        return redirect()->route('legal-cases.show', $case)
            ->with('success', $case->isParsed()
                ? 'Kasus berhasil ditambahkan dan PDF berhasil diparse.'
                : 'Kasus berhasil ditambahkan, namun gagal mengekstrak teks PDF.');
    }

    private function parseCase(LegalCase $legalCase): void
    {
        set_time_limit(300);

        $text = $this->documentParser->extractFromStoragePath($legalCase->file_path);

        if (mb_strlen(trim($text)) < 50) {
            return;
        }

        $legalCase->update(['parsed_text' => $text, 'parsed_at' => now()]);
    }

    public function show(LegalCase $legalCase): View
    {
        $legalCase->load('regulations.documents');

        return view('legal-cases.show', compact('legalCase'));
    }

    public function parse(LegalCase $legalCase): RedirectResponse
    {
        if ($legalCase->isParsed()) {
            return back()->with('info', 'Dokumen kasus sudah diparse.');
        }

        set_time_limit(300);

        $text = $this->documentParser->extractFromStoragePath($legalCase->file_path);

        if (mb_strlen(trim($text)) < 50) {
            return back()->with('error', 'Gagal mengekstrak teks dari PDF.');
        }

        $legalCase->update(['parsed_text' => $text, 'parsed_at' => now()]);

        UserActivityLog::log('parsed', LegalCase::class, $legalCase->id, "Parse PDF kasus {$legalCase->title}");

        return back()->with('success', 'PDF berhasil diparse.');
    }

    public function generate(LegalCase $legalCase): RedirectResponse
    {
        if (! $legalCase->isParsed()) {
            return back()->with('error', 'Dokumen belum diparse. Silakan parse terlebih dahulu.');
        }

        AiJobStatus::begin($legalCase, 'analysis');
        GenerateCaseAnalysis::dispatch($legalCase);

        return back()->with('info', 'Analisa kasus sedang diproses di background.');
    }

    public function edit(LegalCase $legalCase): View
    {
        return view('legal-cases.edit', compact('legalCase'));
    }

    public function update(Request $request, LegalCase $legalCase): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'case_number' => ['nullable', 'string', 'max:255'],
            'court' => ['nullable', 'string', 'max:255'],
            'status_case' => ['required', 'in:ongoing,finished'],
        ]);

        $legalCase->update([
            'title' => $validated['title'],
            'case_number' => $validated['case_number'] ?? null,
            'court' => $validated['court'] ?? null,
            'status_case' => $validated['status_case'],
        ]);

        UserActivityLog::log('updated', LegalCase::class, $legalCase->id, "Memperbarui analisa kasus {$legalCase->title}");

        return redirect()->route('legal-cases.show', $legalCase)
            ->with('success', 'Kasus berhasil diperbarui.');
    }

    public function destroy(LegalCase $legalCase): RedirectResponse
    {
        if ($legalCase->file_path) {
            DocumentStorage::disk()->delete($legalCase->file_path);
        }

        $title = $legalCase->title;
        $legalCase->delete();

        UserActivityLog::log('deleted', LegalCase::class, null, "Menghapus analisa kasus {$title}");

        return redirect()->route('legal-cases.index')
            ->with('success', 'Kasus berhasil dihapus.');
    }
}
