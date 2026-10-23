<?php

namespace App\Services;

use App\Models\Regulation;
use App\Models\RegulationAnalysis;

class RegulationAnalysisService
{
    private const MAX_TEXT_LENGTH = 30000;

    public function __construct(private readonly AiClient $aiClient) {}

    public function analyze(Regulation $regulation): ?RegulationAnalysis
    {
        return RegulationAnalysis::where('regulation_id', $regulation->id)
            ->with(['pasal', 'references'])
            ->latest()
            ->first();
    }

    public function regenerate(Regulation $regulation): RegulationAnalysis
    {
        RegulationAnalysis::where('regulation_id', $regulation->id)->delete();

        return $this->generate($regulation);
    }

    public function saveAnalysis(Regulation $regulation): ?RegulationAnalysis
    {
        $analysis = RegulationAnalysis::where('regulation_id', $regulation->id)->first();

        if (! $analysis) {
            return null;
        }

        $metadata = $analysis->metadata ?? [];
        $metadata['is_saved'] = true;
        $metadata['saved_at'] = now()->toIso8601String();
        $analysis->update(['metadata' => $metadata]);

        return $analysis->fresh()->load(['pasal', 'references']);
    }

    public function generate(Regulation $regulation): RegulationAnalysis
    {
        $regulation->loadMissing(['relatedRegulations.type', 'relatedRegulations.documents', 'documents']);

        $relatedData = $this->collectRelatedData($regulation);
        $context = $this->buildContext($regulation, $relatedData);

        $parsedText = $regulation->isParsed() && $regulation->parsed_text
            ? $this->getContentText($regulation)
            : null;

        $extracted = $parsedText ? $this->extractRegulationsFromText($parsedText) : null;

        $aiResult = $this->tryAiAnalysis($context, $relatedData, $parsedText);
        $analysis = $aiResult ?? $this->fallbackAnalysis($relatedData);

        $changesSummary = $extracted['changes_summary'] ?? null;
        $keyPoints = $extracted['key_points'] ?? null;

        $record = RegulationAnalysis::create([
            'regulation_id' => $regulation->id,
            'context' => $context,
            'comparison_insights' => $analysis['comparison_insights'],
            'change_analysis' => $analysis['change_analysis'],
            'revocation_analysis' => $analysis['revocation_analysis'],
            'changes_summary' => $changesSummary,
            'key_points' => $keyPoints,
            'related_data' => $relatedData,
            'metadata' => [
                'total_related' => count($relatedData['related']),
                'total_amendments' => count($relatedData['amendments']),
                'total_revocations' => count($relatedData['revocations']),
                'total_referenced_regulations' => count($extracted['referenced_regulations'] ?? []),
                'total_pasal' => count($extracted['pasal_structure'] ?? []),
                'generated_at' => now()->toIso8601String(),
                'ai_generated' => $aiResult !== null,
                'is_saved' => false,
            ],
        ]);

        if (! empty($extracted['pasal_structure'])) {
            $pasalData = array_map(fn (array $p, int $i) => [
                'regulation_analysis_id' => $record->id,
                'pasal' => $p['pasal'] ?? '',
                'content' => $p['content'] ?? null,
                'type' => $p['type'] ?? 'existing',
                'changes' => $p['changes'] ?? null,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ], $extracted['pasal_structure'], array_keys($extracted['pasal_structure']));

            $record->pasal()->insert($pasalData);
        }

        if (! empty($extracted['referenced_regulations'])) {
            $refData = array_map(fn (array $r, int $i) => [
                'regulation_analysis_id' => $record->id,
                'name' => $r['name'] ?? '',
                'number' => $r['number'] ?? null,
                'year' => $r['year'] ?? null,
                'relationship' => $r['relationship'] ?? 'disebut',
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ], $extracted['referenced_regulations'], array_keys($extracted['referenced_regulations']));

            $record->references()->insert($refData);
        }

        return $record->load(['pasal', 'references']);
    }

    public function getContentText(Regulation $regulation): ?string
    {
        $text = $regulation->parsed_text;
        $stats = $regulation->parse_stats;
        $offset = $stats['page_offset'] ?? 0;

        if ($offset > 0) {
            $pages = explode("\n\n", $text);
            $pages = array_slice($pages, $offset);
            $text = implode("\n\n", $pages);
        }

        return mb_substr($text, 0, self::MAX_TEXT_LENGTH);
    }

    public function extractRegulationsFromText(string $text): ?array
    {

        $prompt = <<<PROMPT
Anda adalah ekstraktor regulasi Indonesia. Dari teks peraturan berikut, ekstrak:

1. SEMUA referensi ke peraturan lain yang disebut — perhatikan bagian "Menimbang", "Mengingat", "MEMUTUSKAN", "Pasal", dan "Ketentuan". Cari pola seperti "Undang-Undang Nomor ...", "Peraturan Pemerintah Nomor ...", "Peraturan Presiden Nomor ...", "POJK Nomor ...", "PP Nomor ...", "UU Nomor ...", "Peraturan OJK Nomor ...", dll.
2. Struktur Pasal/Pasal dalam teks
3. Perubahan yang disebutkan (sisipan, perubahan, pencabutan pasal)

TEKS:
{$text}

Kembalikan JSON SAJA (tanpa markdown, tanpa penjelasan) dengan format:

{
  "referenced_regulations": [
    {
      "name": "nama lengkap peraturan",
      "number": "nomor peraturan",
      "year": tahun,
      "relationship": "diubah|dicabut|dirujuk|disebut"
    }
  ],
  "pasal_structure": [
    {
      "pasal": "Pasal X",
      "content": "ringkasan/isi pasal (max 200 chars)",
      "type": "baru|diubah|dicabut|sisipan|existing",
      "changes": "deskripsi perubahan jika ada"
    }
  ],
  "changes_summary": "ringkasan singkat tentang perubahan yang dilakukan peraturan ini",
  "key_points": ["poin penting 1", "poin penting 2"]
}

Jika tidak ada data, gunakan array kosong [] untuk "referenced_regulations" dan "pasal_structure".
PROMPT;
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

            if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Output AI bukan objek JSON valid.');
            }

            return $decoded;
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    public function extractDatesFromText(string $text): array
    {

        $prompt = <<<PROMPT
Anda adalah ekstraktor tanggal dari teks peraturan Indonesia. Dari bagian akhir dokumen (footer/penandatanganan) berikut, temukan:
1. Tanggal ditetapkan (tanggal penetapan/pengesahan)
2. Tanggal diundangkan

TEKS:
{$text}

Kembalikan JSON SAJA (tanpa markdown) dengan format:
{
  "tanggal_tetapkan": "YYYY-MM-DD",
  "tanggal_diundangkan": "YYYY-MM-DD"
}

Gunakan null jika tidak ditemukan. Format tanggal wajib ISO 8601 (YYYY-MM-DD). Tulis tanggal secara literal (misal "2026-06-23") tanpa teks lain.
PROMPT;
        try {
            $response = $this->aiClient->complete([
                'messages' => [
                    ['role' => 'system', 'content' => 'Anda adalah asisten yang hanya mengembalikan JSON valid.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 512,
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response['content'];

            $decoded = json_decode($content, true);

            if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Output AI bukan objek JSON valid.');
            }

            return $decoded;
        } catch (\Throwable $e) {
            report($e);
        }

        return ['tanggal_tetapkan' => null, 'tanggal_diundangkan' => null];
    }

    private function collectRelatedData(Regulation $regulation): array
    {
        $related = [];
        $amendments = [];
        $revocations = [];

        foreach ($regulation->relatedRegulations as $relatedReg) {
            $parsedText = $relatedReg->isParsed() && $relatedReg->parsed_text
                ? mb_substr($relatedReg->parsed_text, 0, 3000)
                : null;

            $entry = [
                'id' => $relatedReg->id,
                'regulation_number' => $relatedReg->regulation_number,
                'title' => $relatedReg->title,
                'year' => $relatedReg->year,
                'type' => $relatedReg->type?->name,
                'is_parsed' => $relatedReg->isParsed(),
                'parse_status' => $relatedReg->parse_status,
                'parse_status_label' => $relatedReg->parseStatusLabel(),
                'parse_status_color' => $relatedReg->parseStatusBadgeColor(),
                'parsed_text_preview' => $parsedText,
                'documents_count' => $relatedReg->documents->count(),
            ];

            $related[] = $entry;

            $titleLower = mb_strtolower($relatedReg->title.' '.$relatedReg->regulation_number);
            $relationType = $this->detectRelationType($titleLower);

            if ($relationType === 'amendment') {
                $amendments[] = $entry;
            } elseif ($relationType === 'revocation') {
                $revocations[] = $entry;
            }
        }

        return [
            'related' => $related,
            'amendments' => $amendments,
            'revocations' => $revocations,
            'main_regulation' => [
                'regulation_number' => $regulation->regulation_number,
                'title' => $regulation->title,
                'year' => $regulation->year,
                'is_parsed' => $regulation->isParsed(),
                'documents_count' => $regulation->documents->count(),
            ],
        ];
    }

    private function detectRelationType(string $titleLower): string
    {
        $amendmentKeywords = ['perubahan', 'amandemen', 'amendment', 'revisi', 'ubah', 'perubahan atas'];
        $revocationKeywords = ['pencabutan', 'revocation', 'cabut', 'mencabut', 'pencabutan atas'];

        foreach ($revocationKeywords as $keyword) {
            if (str_contains($titleLower, $keyword)) {
                return 'revocation';
            }
        }

        foreach ($amendmentKeywords as $keyword) {
            if (str_contains($titleLower, $keyword)) {
                return 'amendment';
            }
        }

        return 'related';
    }

    private function buildContext(Regulation $regulation, array $relatedData): string
    {
        $parts = [];

        $parts[] = "Regulasi Utama: {$regulation->regulation_number} - {$regulation->title} ({$regulation->year})";

        if ($relatedData['amendments']) {
            $parts[] = "\nPerubahan terkait:";
            foreach ($relatedData['amendments'] as $amendment) {
                $parts[] = "- {$amendment['regulation_number']} - {$amendment['title']} ({$amendment['year']})";
            }
        }

        if ($relatedData['revocations']) {
            $parts[] = "\nPencabutan terkait:";
            foreach ($relatedData['revocations'] as $revocation) {
                $parts[] = "- {$revocation['regulation_number']} - {$revocation['title']} ({$revocation['year']})";
            }
        }

        if ($relatedData['related']) {
            $parts[] = "\nRegulasi terkait lainnya:";
            foreach ($relatedData['related'] as $rel) {
                $parts[] = "- {$rel['regulation_number']} - {$rel['title']} ({$rel['year']}) [{$rel['type']}]";
            }
        } else {
            $parts[] = "\nTidak ada regulasi terkait.";
        }

        return implode("\n", $parts);
    }

    private function tryAiAnalysis(string $context, array $relatedData, ?string $parsedText): ?array
    {

        $mainReg = $relatedData['main_regulation'];
        $regNumber = $mainReg['regulation_number'] ?? '';
        $regTitle = $mainReg['title'] ?? '';
        $regYear = $mainReg['year'] ?? '';

        $relatedTexts = [];
        foreach ($relatedData['related'] as $rel) {
            if ($rel['parsed_text_preview']) {
                $relatedTexts[] = "--- {$rel['regulation_number']} ---\n{$rel['parsed_text_preview']}";
            }
        }
        $relatedBlock = implode("\n\n", $relatedTexts);

        $mainTextBlock = $parsedText
            ? "TEKS REGULASI UTAMA:\n{$parsedText}"
            : 'TEKS REGULASI UTAMA: (belum diparse)';

        $relatedTotal = count($relatedData['related'] ?? []);
        $amendmentTotal = count($relatedData['amendments'] ?? []);
        $revocationTotal = count($relatedData['revocations'] ?? []);

        $prompt = <<<PROMPT
Anda adalah analis regulasi profesional. Analisis peraturan berikut secara mendalam.

REGULASI UTAMA:
{$regNumber} - {$regTitle} ({$regYear})

{$mainTextBlock}

REGULASI TERKAIT (dari database):
{$relatedBlock}

INFORMASI TAMBAHAN:
- Jumlah regulasi terkait di database: {$relatedTotal}
- Jumlah perubahan: {$amendmentTotal}
- Jumlah pencabutan: {$revocationTotal}

Berdasarkan teks di atas, berikan analisis dalam bahasa Indonesia:

1. COMPARISON_INSIGHTS:
Jelaskan perbandingan antara peraturan ini dengan peraturan terkait. Sorot persamaan, perbedaan, dan ketidaksesuaian.

2. CHANGE_ANALYSIS:
Analisis perubahan apa saja yang dilakukan. Sebutkan pasal-pasal yang berubah, apa yang berubah, dan implikasinya.

3. REVOCATION_ANALYSIS:
Analisis pencabutan yang dilakukan. Sebutkan peraturan atau pasal apa yang dicabut dan dampaknya.
PROMPT;
        try {
            $response = $this->aiClient->complete([
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 4096,
                'temperature' => 0.3,
            ], allowGroq: true);

            $content = $response['content'];

            return $this->parseAiResponse($content);
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    private function parseAiResponse(string $content): array
    {
        $sections = [
            'comparison_insights' => '',
            'change_analysis' => '',
            'revocation_analysis' => '',
        ];

        $currentSection = null;
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $upperLine = mb_strtoupper(trim($line));

            if (str_contains($upperLine, 'COMPARISON_INSIGHTS')) {
                $currentSection = 'comparison_insights';

                continue;
            }
            if (str_contains($upperLine, 'CHANGE_ANALYSIS')) {
                $currentSection = 'change_analysis';

                continue;
            }
            if (str_contains($upperLine, 'REVOCATION_ANALYSIS')) {
                $currentSection = 'revocation_analysis';

                continue;
            }

            if ($currentSection && trim($line) !== '') {
                $sections[$currentSection] .= $line."\n";
            }
        }

        foreach ($sections as $key => $value) {
            $sections[$key] = trim($value);
        }

        return $sections;
    }

    public function matchReferencesWithDb(RegulationAnalysis $analysis): array
    {
        return $this->matchReferences($analysis->references);
    }

    /**
     * @param  iterable<int, object{name: ?string, number: ?string, year: mixed}>  $references
     * @return array<int, array{reference: object, match: ?Regulation, confidence: ?string, is_available: bool}>
     */
    public function matchReferences(iterable $references): array
    {
        $results = [];

        foreach ($references as $ref) {
            $match = null;
            $confidence = null;
            $candidates = collect();

            // Strategy 1: number + year
            if ($ref->number && $ref->year) {
                $candidates = Regulation::where('year', $ref->year)
                    ->where(function ($q) use ($ref) {
                        $q->where('regulation_number', $ref->number)
                            ->orWhere('regulation_number', 'like', "%{$ref->number}%");
                    })
                    ->with('type')
                    ->limit(5)
                    ->get();
            }

            // Strategy 2: name + year (title LIKE full name)
            if ($candidates->isEmpty() && $ref->name && $ref->year) {
                $nameClean = preg_replace('/\s+/', ' ', trim(mb_substr($ref->name, 0, 60)));
                $candidates = Regulation::where('title', 'like', "%{$nameClean}%")
                    ->where('year', $ref->year)
                    ->with('type')
                    ->limit(5)
                    ->get();
            }

            // Strategy 3: number only (year might be wrong)
            if ($candidates->isEmpty() && $ref->number) {
                $candidates = Regulation::where('regulation_number', 'like', "%{$ref->number}%")
                    ->with('type')
                    ->limit(5)
                    ->get();
            }

            // Strategy 4: name only (year might be wrong)
            if ($candidates->isEmpty() && $ref->name) {
                $nameClean = preg_replace('/\s+/', ' ', trim(mb_substr($ref->name, 0, 60)));
                $candidates = Regulation::where('title', 'like', "%{$nameClean}%")
                    ->with('type')
                    ->limit(5)
                    ->get();
            }

            if ($candidates->count() === 1) {
                $match = $candidates->first();
                $confidence = 'exact';
            } elseif ($candidates->count() > 1) {
                $best = null;
                $bestScore = 0;
                foreach ($candidates as $c) {
                    $score = 0;
                    if ($c->regulation_number === $ref->number) {
                        $score += 3;
                    }
                    if ($ref->name && mb_strpos(mb_strtolower($c->title), mb_strtolower(mb_substr($ref->name, 0, 30))) !== false) {
                        $score += 2;
                    }
                    if ($c->year === (int) $ref->year) {
                        $score += 1;
                    }
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = $c;
                    }
                }
                if ($best && $bestScore >= 2) {
                    $match = $best;
                    $confidence = 'fuzzy';
                }
            }

            $results[] = [
                'reference' => $ref,
                'match' => $match,
                'confidence' => $confidence,
                'is_available' => $match !== null,
            ];
        }

        return $results;
    }

    public function connectReferences(Regulation $regulation, array $referenceIds): int
    {
        $analysis = RegulationAnalysis::where('regulation_id', $regulation->id)->first();

        if (! $analysis) {
            return 0;
        }

        $matched = $this->matchReferencesWithDb($analysis);
        $toConnect = [];

        foreach ($matched as $item) {
            if (in_array($item['reference']->id, $referenceIds) && $item['match']) {
                $toConnect[] = $item['match']->id;
            }
        }

        if (empty($toConnect)) {
            return 0;
        }

        $existing = $regulation->relatedRegulations()->pluck('related_regulation_id')->toArray();
        $new = array_diff($toConnect, $existing);

        if (empty($new)) {
            return 0;
        }

        $regulation->relatedRegulations()->attach($new);

        return count($new);
    }

    public function analyzeByBabs(Regulation $regulation): array
    {
        $text = $this->getContentText($regulation);
        if (! $text) {
            return ['babs' => []];
        }

        $babs = $this->splitTextToBabs($text);

        $results = [];
        foreach ($babs as $i => $bab) {
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

        return ['babs' => $results];
    }

    public function analyzeBabByIndex(Regulation $regulation, int $index): array
    {
        $text = $this->getContentText($regulation);
        if (! $text) {
            return ['label' => null, 'pasal' => [], 'references' => [], 'insights' => null, 'compliance_assessment' => null, 'key_findings' => []];
        }

        $babs = $this->splitTextToBabs($text);

        if (! isset($babs[$index])) {
            return ['label' => null, 'pasal' => [], 'references' => [], 'insights' => null, 'compliance_assessment' => null, 'key_findings' => []];
        }

        $bab = $babs[$index];
        $babLabel = $bab['label'];
        $babText = $bab['text'];

        $regulation->loadMissing(['relatedRegulations']);
        $relatedTexts = [];
        foreach ($regulation->relatedRegulations as $rel) {
            if ($rel->isParsed() && $rel->parsed_text) {
                $relatedTexts[] = "{$rel->regulation_number} - {$rel->title}:\n".mb_substr($rel->parsed_text, 0, 3000);
            }
        }
        $relatedBlock = $relatedTexts ? "\n\nREGULASI TERKAIT:\n".implode("\n\n", $relatedTexts) : '';

        $prompt = <<<PROMPT
Anda adalah analis regulasi. Analisis BAB berikut dari {$regulation->regulation_number} - {$regulation->title} ({$regulation->year}).

BAB: {$babLabel}

Konten BAB:
{$babText}
{$relatedBlock}

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
  "insights": "analisis perbandingan bab ini dengan regulasi terkait, apakah ada kesesuaian, perubahan, atau celah",
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

        return [
            'label' => $babLabel,
            'pasal_count' => count($result['pasal_structure'] ?? []),
            'ref_count' => count($result['referenced_regulations'] ?? []),
            'pasal' => $result['pasal_structure'] ?? [],
            'references' => $result['referenced_regulations'] ?? [],
            'insights' => $result['insights'] ?? null,
            'compliance_assessment' => $result['compliance_assessment'] ?? null,
            'key_findings' => $result['key_findings'] ?? [],
        ];
    }

    public function splitTextToBabs(string $text): array
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

    private function fallbackAnalysis(array $relatedData): array
    {
        $insights = [];
        $changes = [];
        $revocations = [];

        $reg = $relatedData['main_regulation'];

        if ($reg['is_parsed']) {
            $insights[] = "Regulasi {$reg['regulation_number']} sudah diparse dan siap untuk dianalisis.";
        } else {
            $insights[] = "Regulasi {$reg['regulation_number']} belum diparse. Lakukan parser PDF terlebih dahulu untuk analisis yang lebih mendalam.";
        }

        $relatedTotal = count($relatedData['related']);
        $amendmentTotal = count($relatedData['amendments']);
        $revocationTotal = count($relatedData['revocations']);

        if ($relatedTotal > 0) {
            $insights[] = "Regulasi ini memiliki {$relatedTotal} regulasi terkait.";
            $insights[] = "Dari jumlah tersebut, {$amendmentTotal} merupakan regulasi perubahan dan {$revocationTotal} merupakan regulasi pencabutan.";
            $insights[] = 'Disarankan untuk melakukan review menyeluruh terhadap seluruh regulasi terkait untuk memastikan kepatuhan dan konsistensi.';
        } else {
            $insights[] = 'Regulasi ini tidak memiliki regulasi terkait.';
            $insights[] = 'Tambahkan regulasi terkait melalui menu edit untuk analisis perbandingan yang lebih komprehensif.';
        }

        if ($amendmentTotal > 0) {
            $changes[] = 'Terdeteksi '.$amendmentTotal.' regulasi perubahan:';
            foreach ($relatedData['amendments'] as $amendment) {
                $changes[] = "- {$amendment['regulation_number']} - {$amendment['title']} ({$amendment['year']})";
                $changes[] = '  Status: '.($amendment['is_parsed'] ? 'Sudah diparse' : 'Belum diparse');
            }
        } else {
            $changes[] = 'Tidak terdeteksi regulasi perubahan yang terkait dengan regulasi ini.';
        }

        if ($revocationTotal > 0) {
            $revocations[] = 'Terdeteksi '.$revocationTotal.' regulasi pencabutan:';
            foreach ($relatedData['revocations'] as $revocation) {
                $revocations[] = "- {$revocation['regulation_number']} - {$revocation['title']} ({$revocation['year']})";
                $revocations[] = '  Status: '.($revocation['is_parsed'] ? 'Sudah diparse' : 'Belum diparse');
            }
        } else {
            $revocations[] = 'Tidak terdeteksi regulasi pencabutan yang terkait dengan regulasi ini.';
        }

        return [
            'comparison_insights' => implode("\n", $insights),
            'change_analysis' => implode("\n", $changes),
            'revocation_analysis' => implode("\n", $revocations),
        ];
    }
}
