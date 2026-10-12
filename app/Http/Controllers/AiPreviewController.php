<?php

namespace App\Http\Controllers;

use App\Models\AiPrompt;
use App\Models\AiSummary;
use App\Models\DocumentParsedText;
use App\Models\ReviewDocument;
use App\Services\AiClient;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiPreviewController extends Controller
{
    public function __construct(
        private readonly AiService $aiService,
        private readonly AiClient $aiClient,
    ) {}

    public function show(Request $request, ReviewDocument $reviewDocument): View
    {
        abort_if(auth()->user()->isSubAdmin(), 403);

        $selectedType = $request->query('type', 'analisa');
        $prompts = AiPrompt::active()->get();

        $reviewDocument->load([
            'regulations.documents',
            'regulations.type',
            'partitions' => fn ($q) => $q->roots(),
            'partitions.analysis' => fn ($q) => $q->where('type', $selectedType),
        ]);

        $summary = AiSummary::where('review_document_id', $reviewDocument->id)
            ->where('type', $selectedType)
            ->latest()
            ->first();

        $activePrompt = AiPrompt::active()->where('type', $selectedType)->first();

        // Load parsed texts from DB (saved during generate)
        $parsedTexts = DocumentParsedText::where('review_document_id', $reviewDocument->id)
            ->orderBy('source_type')
            ->orderBy('page')
            ->get();

        return view('ai-preview.show', [
            'document' => $reviewDocument,
            'prompts' => $prompts,
            'selectedType' => $selectedType,
            'summary' => $summary,
            'activePrompt' => $activePrompt,
            'parsedTexts' => $parsedTexts,
        ]);
    }

    public function babList(ReviewDocument $reviewDocument): JsonResponse
    {
        $reviewDocument->load('pages');
        $text = $reviewDocument->pages->pluck('content')->implode("\n");

        if (! $text) {
            return response()->json(['babs' => []]);
        }

        $babs = $this->splitTextToBabs($text);

        return response()->json([
            'babs' => array_map(fn ($b) => ['label' => $b['label']], $babs),
        ]);
    }

    public function analyzeBabs(ReviewDocument $reviewDocument): JsonResponse
    {
        $reviewDocument->load('pages');
        $text = $reviewDocument->pages->pluck('content')->implode("\n");

        if (! $text) {
            return response()->json(['babs' => []]);
        }

        $babs = $this->splitTextToBabs($text);

        $results = [];
        foreach ($babs as $bab) {
            $babLabel = $bab['label'];
            $babText = $bab['text'];

            $prompt = <<<PROMPT
Anda adalah ekstraktor pasal. Dari teks BAB berikut, ekstrak struktur pasal dan referensi ke peraturan lain.

BAB: {$babLabel}

TEKS:
{$babText}

Kembalikan JSON SAJA dengan format:
{
  "pasal_structure": [
    {
      "pasal": "Pasal X",
      "content": "ringkasan isi pasal (max 200 chars)",
      "type": "baru|existing|diubah",
      "changes": "deskripsi perubahan jika ada"
    }
  ],
  "referenced_regulations": [
    {
      "name": "nama peraturan",
      "number": "nomor",
      "year": tahun,
      "relationship": "diubah|dicabut|dirujuk|disebut"
    }
  ]
}
PROMPT;

            $result = ['pasal_structure' => [], 'referenced_regulations' => []];
            try {
                $response = $this->aiClient->complete([
                    'messages' => [
                        ['role' => 'system', 'content' => 'Anda adalah asisten yang hanya mengembalikan JSON valid.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 4096,
                    'temperature' => 0.1,
                    'response_format' => ['type' => 'json_object'],
                ]);

                $content = $response['content'];

                $decoded = json_decode($content, true);
                if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                    $result = $decoded;
                }
            } catch (\Throwable $e) {
                report($e);
            }

            $results[] = [
                'label' => $babLabel,
                'pasal_count' => count($result['pasal_structure'] ?? []),
                'ref_count' => count($result['referenced_regulations'] ?? []),
                'pasal' => $result['pasal_structure'] ?? [],
                'references' => $result['referenced_regulations'] ?? [],
            ];
        }

        return response()->json(['babs' => $results]);
    }

    public function analyzeSingleBab(ReviewDocument $reviewDocument, int $index): JsonResponse
    {
        $reviewDocument->load(['pages', 'regulations']);
        $text = $reviewDocument->pages->pluck('content')->implode("\n");

        if (! $text) {
            return response()->json(['label' => null, 'pasal' => [], 'references' => [], 'insights' => null, 'compliance_assessment' => null, 'key_findings' => []]);
        }

        $babs = $this->splitTextToBabs($text);

        if (! isset($babs[$index])) {
            return response()->json(['label' => null, 'pasal' => [], 'references' => [], 'insights' => null, 'compliance_assessment' => null, 'key_findings' => []]);
        }

        $bab = $babs[$index];
        $babLabel = $bab['label'];
        $babText = $bab['text'];

        $regTexts = [];
        foreach ($reviewDocument->regulations as $reg) {
            $regContent = $reg->parsed_text ?? '';
            if ($regContent) {
                $regTexts[] = "{$reg->regulation_number} - {$reg->title}:\n".mb_substr($regContent, 0, 3000);
            }
        }
        $regBlock = $regTexts ? "\n\nREGULASI ACUAN:\n".implode("\n\n", $regTexts) : '';

        $prompt = <<<PROMPT
Anda adalah analis dokumen. Analisis BAB berikut dari dokumen "{$reviewDocument->title}" dan bandingkan dengan regulasi acuan.

BAB: {$babLabel}

Konten BAB:
{$babText}
{$regBlock}

Berikan analisis JSON dengan format:
{
  "pasal_structure": [
    {
      "pasal": "Pasal X",
      "content": "ringkasan isi pasal",
      "type": "baru|existing|diubah",
      "changes": "deskripsi perubahan jika ada"
    }
  ],
  "referenced_regulations": [
    {
      "name": "nama peraturan",
      "number": "nomor",
      "year": tahun,
      "relationship": "diubah|dicabut|dirujuk|disebut"
    }
  ],
  "insights": "analisis perbandingan bab ini dengan regulasi acuan: kesesuaian, perbedaan, atau celah",
  "compliance_assessment": "Sesuai|Perlu Penyesuaian|Tidak Sesuai|Tidak Ada Regulasi Terkait",
  "key_findings": ["temuan singkat 1", "temuan singkat 2"]
}
PROMPT;

        $result = ['pasal_structure' => [], 'referenced_regulations' => [], 'insights' => null, 'compliance_assessment' => null, 'key_findings' => []];
        try {
            $response = $this->aiClient->complete([
                'messages' => [
                    ['role' => 'system', 'content' => 'Anda adalah asisten yang hanya mengembalikan JSON valid.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 4096,
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response['content'];

            $decoded = json_decode($content, true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                $result = $decoded;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'label' => $babLabel,
            'pasal_count' => count($result['pasal_structure'] ?? []),
            'ref_count' => count($result['referenced_regulations'] ?? []),
            'pasal' => $result['pasal_structure'] ?? [],
            'references' => $result['referenced_regulations'] ?? [],
            'insights' => $result['insights'] ?? null,
            'compliance_assessment' => $result['compliance_assessment'] ?? null,
            'key_findings' => $result['key_findings'] ?? [],
        ]);
    }

    private function splitTextToBabs(string $text): array
    {
        $pattern = '/(BAB\s+(?:[IVXLCDM]+|\d+)[^\n]*)/i';
        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return [['label' => 'Full Text', 'text' => mb_substr($text, 0, 30000)]];
        }

        $babs = [];
        foreach ($matches[0] as $idx => $match) {
            $label = trim($match[0]);
            $start = $match[1];
            $nextStart = $matches[0][$idx + 1][1] ?? strlen($text);
            $babText = mb_substr($text, $start, $nextStart - $start);
            $babText = mb_substr($babText, 0, 30000);
            $babs[] = ['label' => $label, 'text' => $babText];
        }

        return $babs;
    }

    public function generate(Request $request, ReviewDocument $reviewDocument): RedirectResponse
    {
        abort_if($request->user()->isSubAdmin(), 403);

        set_time_limit(300);

        $request->validate([
            'type' => ['required', 'string', 'in:analisa,review,rekomendasi,validitas'],
            'partition_ids' => ['nullable', 'array'],
            'partition_ids.*' => ['integer', 'exists:document_partitions,id'],
        ]);

        $type = $request->input('type');

        if (! $reviewDocument->isParsed()) {
            return redirect()->route('ai-preview.show', [$reviewDocument, 'type' => $type])
                ->with('error', 'Dokumen belum di-parse. Silakan lakukan Parse PDF terlebih dahulu di menu Partisi.');
        }

        $reviewDocument->load('regulations');
        $unparsedRegs = $reviewDocument->regulations->reject(fn ($r) => $r->isParsed());
        if ($unparsedRegs->isNotEmpty()) {
            return redirect()->route('ai-preview.show', [$reviewDocument, 'type' => $type])
                ->with('error', 'Regulasi berikut belum diparse: '.$unparsedRegs->pluck('regulation_number')->implode(', ').'. Parse terlebih dahulu di menu Regulasi.');
        }

        $partitionIds = $request->input('partition_ids');

        try {
            $this->aiService->generateSummary($reviewDocument, $type);

            $selectedPartitions = $reviewDocument->partitions()
                ->when($partitionIds, fn ($q) => $q->whereIn('id', $partitionIds))
                ->count();

            if ($selectedPartitions > 0) {
                $this->aiService->generateAllPartitionAnalyses($reviewDocument, $type, $partitionIds);
            }

            $count = $partitionIds ? count($partitionIds) : $reviewDocument->partitions()->count();
            $msg = 'AI Preview berhasil digenerate'.($count > 0 ? " ({$count} partisi dianalisa)" : '');

            return redirect()->route('ai-preview.show', [$reviewDocument, 'type' => $type])
                ->with('success', $msg);
        } catch (\Exception $e) {
            return redirect()->route('ai-preview.show', [$reviewDocument, 'type' => $type])
                ->with('error', 'Gagal generate AI: '.$e->getMessage());
        }
    }
}
