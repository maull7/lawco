<?php

namespace App\Services;

use App\Exceptions\ParsingCancelledException;
use App\Models\Regulation;
use App\Models\RegulationDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;

class RegulationParserService
{
    private const TEXT_THRESHOLD = 10;

    public function __construct(
        private readonly DocumentParser $documentParser,
    ) {}

    public function detectPdfType(string $path): string
    {
        $fullPath = Storage::disk('public')->path($path);

        if (! file_exists($fullPath)) {
            return 'image';
        }

        try {
            $pdf = $this->parsePdf($fullPath);
            $pages = $pdf->getPages();

            foreach ($pages as $page) {
                $text = trim(preg_replace('/\s+/', ' ', $page->getText()));
                if (mb_strlen($text) > self::TEXT_THRESHOLD) {
                    return 'text';
                }
            }

            return 'image';
        } catch (\Exception $e) {
            Log::warning("Failed to detect PDF type for {$path}: {$e->getMessage()}");

            return 'image';
        }
    }

    public function parseRegulation(Regulation $regulation, ?callable $progress = null, ?callable $isCancelled = null): array
    {
        if ($isCancelled && $isCancelled()) {
            throw new ParsingCancelledException('Parsing dibatalkan.');
        }

        $fullPath = Storage::disk('public')->path($regulation->file_path);

        if (! file_exists($fullPath)) {
            return $this->result('error', 'File tidak ditemukan.');
        }

        set_time_limit(600);

        $pages = $this->ocrRegulation($regulation, $progress);

        if (empty($pages)) {
            return $this->result('error', 'Gagal mengekstrak teks dari PDF (OCR).');
        }

        $totalPages = count($pages);
        $parsedPages = array_filter($pages, fn ($p) => $p['char_count'] > 0);
        $parsedCount = count($parsedPages);
        $percentParsed = $totalPages > 0 ? round(($parsedCount / $totalPages) * 100) : 0;

        $fullText = collect($pages)->pluck('text')->implode("\n\n");

        $parseStatus = $percentParsed >= 95 ? 'complete' : ($percentParsed > 0 ? 'incomplete' : 'not_parsed');

        $contentStartPage = $this->detectContentStartPage($pages);
        $pageOffset = $contentStartPage ? $contentStartPage - 1 : 0;

        $stats = [
            'pdf_type' => 'image',
            'total_pages' => $totalPages,
            'parsed_pages' => $parsedCount,
            'empty_pages' => $totalPages - $parsedCount,
            'percent_parsed' => $percentParsed,
            'normal_pages' => 0,
            'ocr_pages' => $parsedCount,
            'char_total' => array_sum(array_column($pages, 'char_count')),
            'used_ocr' => true,
            'content_start_page' => $contentStartPage,
            'page_offset' => $pageOffset,
            'ocr_engine' => 'tesseract',
            'ocr_dpi' => 200,
            'ocr_langs' => 'ind+eng',
        ];

        $regulation->update([
            'parsed_at' => now(),
            'parse_status' => $parseStatus,
            'parsed_text' => $this->sanitizeUtf8($fullText),
            'parse_stats' => $stats,
            'parse_progress' => 100,
        ]);

        return $this->result('success', 'Regulasi berhasil diparse (OCR).', $stats, $fullText);
    }

    /**
     * Parse a bounded page range using text extraction or OCR.
     *
     * @return array{success: bool, message: string, stats: array{pages: array<int, array{page: int, text: string, char_count: int}}, text: string|null}
     */
    public function parseRegulationChunk(Regulation $regulation, int $startPage, int $endPage, string $method, ?callable $isCancelled = null): array
    {
        $fullPath = Storage::disk('public')->path($regulation->file_path);

        if (! file_exists($fullPath)) {
            return $this->result('error', 'File tidak ditemukan.');
        }

        if ($isCancelled) {
            $isCancelled();
        }

        $pages = $method === 'text'
            ? $this->extractTextRegulationChunk($fullPath, $startPage, $endPage)
            : $this->ocrRegulationChunk($fullPath, $startPage, $endPage, $isCancelled);

        if (empty($pages)) {
            return $this->result('error', "Gagal mengekstrak halaman {$startPage}-{$endPage} dari PDF.");
        }

        return $this->result(
            'success',
            "Halaman {$startPage}-{$endPage} berhasil diparse.",
            ['pages' => $pages],
            collect($pages)->pluck('text')->implode("\n\n"),
        );
    }

    private function sanitizeUtf8(string $text): string
    {
        // Hilangkan byte sequences yang tidak valid agar PREG tidak gagal.
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // Hapus karakter di luar BMP (emoji, dll.) agar kompatibel dengan penyimpanan.
        $cleaned = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);

        return $cleaned ?? $text;
    }

    private function detectContentStartPage(array $pages): ?int
    {
        foreach ($pages as $page) {
            $text = trim($page['text']);
            if (preg_match('/^BAB\s+I/i', $text)) {
                return $page['page'];
            }
        }

        return null;
    }

    public function parseDocument(RegulationDocument $document): array
    {
        return $this->parseDocumentChoice($document, $this->resolveMethod($document->file_path));
    }

    public function parseDocumentChoice(RegulationDocument $document, string $method, ?callable $progress = null, ?callable $isCancelled = null): array
    {
        if ($isCancelled && $isCancelled()) {
            throw new ParsingCancelledException('Parsing dibatalkan.');
        }

        $fullPath = Storage::disk('public')->path($document->file_path);

        if (! file_exists($fullPath)) {
            return $this->result('error', 'File tidak ditemukan.');
        }

        set_time_limit(300);

        if ($isCancelled && $isCancelled()) {
            throw new ParsingCancelledException('Parsing dibatalkan.');
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (! in_array($ext, ['pdf', 'docx'])) {
            return $this->result('error', 'Format file tidak didukung. Hanya PDF dan DOCX.');
        }

        $pages = match ($ext) {
            'docx' => $this->extractDocxText($fullPath),
            'pdf' => $this->extractPdfPages($document, $method, $progress, $isCancelled),
        };

        if ($isCancelled && $isCancelled()) {
            throw new ParsingCancelledException('Parsing dibatalkan.');
        }

        if (empty($pages)) {
            return $this->result('error', 'Gagal mengekstrak teks.');
        }

        $totalPages = count($pages);
        $parsedPages = array_filter($pages, fn ($p) => $p['char_count'] > 0);
        $parsedCount = count($parsedPages);
        $percentParsed = $totalPages > 0 ? round(($parsedCount / $totalPages) * 100) : 0;

        $fullText = collect($pages)->pluck('text')->implode("\n\n");

        $parseStatus = $percentParsed >= 100 ? 'complete' : ($percentParsed > 0 ? 'incomplete' : 'not_parsed');

        $pdfType = $ext === 'pdf' ? ($method === 'ocr' ? 'image' : 'text') : $ext;

        $stats = [
            'pdf_type' => $pdfType,
            'total_pages' => $totalPages,
            'parsed_pages' => $parsedCount,
            'empty_pages' => $totalPages - $parsedCount,
            'percent_parsed' => $percentParsed,
            'normal_pages' => in_array($pdfType, ['text', 'docx']) ? $parsedCount : 0,
            'ocr_pages' => $pdfType === 'image' ? $parsedCount : 0,
            'char_total' => array_sum(array_column($pages, 'char_count')),
            'used_ocr' => $pdfType === 'image',
            'method' => $method,
            'doc_type' => $ext,
        ];

        $document->update([
            'parsed_at' => now(),
            'parse_status' => $parseStatus,
            'parsed_text' => $this->sanitizeUtf8($fullText),
            'parse_stats' => $stats,
            'parse_progress' => 100,
        ]);

        return $this->result('success', 'Dokumen berhasil diparse.', $stats, $fullText);
    }

    private function resolveMethod(string $path): string
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'docx') {
            return 'text';
        }

        return $this->detectPdfType($path) === 'text' ? 'text' : 'ocr';
    }

    private function extractPdfPages(RegulationDocument $document, string $method, ?callable $progress = null, ?callable $isCancelled = null): array
    {
        if ($method === 'ocr') {
            return $this->ocrDocument($document, $progress);
        }

        $pages = $this->documentParser->extractAllPagesText($document->file_path);

        if ($isCancelled && $isCancelled()) {
            throw new ParsingCancelledException('Parsing dibatalkan.');
        }

        if ($this->hasContent($pages)) {
            return $pages;
        }

        // ponytail: normal text extraction produced nothing -> fallback to OCR (scanned PDF)
        return $this->ocrDocument($document, $progress);
    }

    private function hasContent(array $pages): bool
    {
        foreach ($pages as $page) {
            if (($page['char_count'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    private function extractDocxText(string $fullPath): array
    {
        $zip = new \ZipArchive;

        if ($zip->open($fullPath) !== true) {
            return [];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return [];
        }

        $text = str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n+/', "\n", trim($text));

        return [[
            'page' => 1,
            'text' => $text,
            'char_count' => mb_strlen($text),
        ]];
    }

    private function ocrDocument(RegulationDocument $document, ?callable $progress = null): array
    {
        $fullPath = Storage::disk('public')->path($document->file_path);

        $tmpDir = sys_get_temp_dir().'/ocr_doc_'.md5($fullPath).'_'.time();
        @mkdir($tmpDir, 0755, true);

        try {
            exec('pdftoppm -png -r 200 '.escapeshellarg($fullPath).' '.escapeshellarg($tmpDir.'/page'), $output, $returnCode);

            if ($returnCode !== 0) {
                return [];
            }

            $images = glob($tmpDir.'/page-*.png');
            sort($images);
            $total = count($images);

            $result = [];
            foreach ($images as $index => $image) {
                try {
                    $text = (new TesseractOCR($image))->lang('ind', 'eng')->run();
                } catch (\Throwable $e) {
                    Log::warning("OCR page {$index} failed: {$e->getMessage()}");
                    $text = '';
                }
                $text = trim($text);
                $result[] = [
                    'page' => $index + 1,
                    'text' => $text,
                    'char_count' => mb_strlen($text),
                ];

                if ($progress) {
                    $progress((int) round((($index + 1) / max(1, $total)) * 100), 'OCR page '.($index + 1).'/'.$total);
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::warning("OCR document failed: {$e->getMessage()}");

            return [];
        } finally {
            array_map('unlink', glob($tmpDir.'/*'));
            @rmdir($tmpDir);
        }
    }

    private function ocrRegulation(Regulation $regulation, ?callable $progress = null): array
    {
        $fullPath = Storage::disk('public')->path($regulation->file_path);

        $tmpDir = sys_get_temp_dir().'/ocr_reg_'.md5($fullPath).'_'.time();
        @mkdir($tmpDir, 0755, true);

        try {
            exec('pdftoppm -png -r 200 '.escapeshellarg($fullPath).' '.escapeshellarg($tmpDir.'/page'), $output, $returnCode);

            if ($returnCode !== 0) {
                return [];
            }

            $images = glob($tmpDir.'/page-*.png');
            sort($images);
            $total = count($images);

            $result = [];
            foreach ($images as $index => $image) {
                try {
                    $text = (new TesseractOCR($image))->lang('ind', 'eng')->psm(6)->run();
                } catch (\Throwable $e) {
                    Log::warning("OCR regulation page {$index} failed: {$e->getMessage()}");
                    $text = '';
                }
                $text = trim($text);
                $result[] = [
                    'page' => $index + 1,
                    'text' => $text,
                    'char_count' => mb_strlen($text),
                ];

                if ($progress) {
                    $progress((int) round((($index + 1) / max(1, $total)) * 100), 'OCR page '.($index + 1).'/'.$total);
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::warning("OCR regulation failed: {$e->getMessage()}");

            return [];
        } finally {
            array_map('unlink', glob($tmpDir.'/*'));
            @rmdir($tmpDir);
        }
    }

    /**
     * @return array<int, array{page: int, text: string, char_count: int}>
     */
    private function extractTextRegulationChunk(string $fullPath, int $startPage, int $endPage): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'reg_text_');

        if ($tmpFile === false) {
            return [];
        }

        try {
            $command = 'pdftotext -f '.max(1, $startPage).' -l '.max($startPage, $endPage).' -layout '
                .escapeshellarg($fullPath).' '.escapeshellarg($tmpFile);
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                return [];
            }

            $rawText = file_get_contents($tmpFile);
            if ($rawText === false) {
                return [];
            }

            $rawPages = explode("\f", $rawText);
            $pages = [];
            $expectedPages = $endPage - $startPage + 1;

            for ($index = 0; $index < $expectedPages; $index++) {
                $text = trim(preg_replace('/\s+/', ' ', $rawPages[$index] ?? ''));
                $text = $this->sanitizeUtf8($text);
                $pages[] = [
                    'page' => $startPage + $index,
                    'text' => $text,
                    'char_count' => mb_strlen($text),
                ];
            }

            return $pages;
        } catch (\Throwable $e) {
            Log::warning("Text extraction failed for pages {$startPage}-{$endPage}: {$e->getMessage()}");

            return [];
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * @return array<int, array{page: int, text: string, char_count: int}>
     */
    private function ocrRegulationChunk(string $fullPath, int $startPage, int $endPage, ?callable $isCancelled = null): array
    {
        $tmpDir = sys_get_temp_dir().'/ocr_reg_chunk_'.md5($fullPath).'_'.time().'_'.$startPage;
        @mkdir($tmpDir, 0755, true);

        try {
            $command = 'pdftoppm -f '.max(1, $startPage).' -l '.max($startPage, $endPage).' -png -r 200 '
                .escapeshellarg($fullPath).' '.escapeshellarg($tmpDir.'/page');
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                return [];
            }

            $images = glob($tmpDir.'/page-*.png');
            sort($images);
            $result = [];

            foreach ($images as $index => $image) {
                if ($isCancelled) {
                    $isCancelled();
                }

                try {
                    $text = (new TesseractOCR($image))->lang('ind', 'eng')->psm(6)->run();
                } catch (\Throwable $e) {
                    Log::warning('OCR regulation chunk page '.($startPage + $index).' failed: '.$e->getMessage());
                    $text = '';
                }

                $text = $this->sanitizeUtf8(trim($text));
                $result[] = [
                    'page' => $startPage + $index,
                    'text' => $text,
                    'char_count' => mb_strlen($text),
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning("OCR regulation chunk failed for {$fullPath}: {$e->getMessage()}");

            return [];
        } finally {
            array_map('unlink', glob($tmpDir.'/*'));
            @rmdir($tmpDir);
        }
    }

    private function parsePdf(string $fullPath): Document
    {
        static $parserCache = [];
        if (! isset($parserCache[$fullPath])) {
            $parser = new Parser;
            $parserCache[$fullPath] = $parser->parseFile($fullPath);
        }

        return $parserCache[$fullPath];
    }

    private function result(string $status, string $message, array $stats = [], ?string $text = null): array
    {
        return [
            'success' => $status === 'success',
            'message' => $message,
            'stats' => $stats,
            'text' => $text,
        ];
    }
}
