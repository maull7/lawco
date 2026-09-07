<?php

namespace App\Services;

use App\Models\DocumentPage;
use App\Models\ReviewDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;

class DocumentParser
{
    private const MAX_CHARS = 30000;

    private const CHAPTER_MAX_CHARS = 20000;

    private array $parsedCache = [];

    public function extractFromStoragePath(string $path): string
    {
        $fullPath = Storage::disk('public')->path($path);

        if (! file_exists($fullPath)) {
            Log::warning("Document file not found: {$path}");

            return '';
        }

        try {
            $pdf = $this->parsePdf($fullPath);
            $text = $pdf->getText();
            $clean = $this->cleanAndTruncate($text, self::MAX_CHARS);

            if (mb_strlen(trim($clean)) < 50) {
                return $this->ocrFallback($fullPath, null, null, self::MAX_CHARS);
            }

            return $clean;
        } catch (\Exception $e) {
            Log::warning("Failed to parse PDF {$path}: {$e->getMessage()}");

            return $this->ocrFallback($fullPath, null, null, self::MAX_CHARS);
        }
    }

    public function extractPagesFromStoragePath(string $path, int $startPage, int $endPage): string
    {
        $fullPath = Storage::disk('public')->path($path);

        if (! file_exists($fullPath)) {
            Log::warning("Document file not found: {$path}");

            return '';
        }

        try {
            $pdf = $this->parsePdf($fullPath);
            $pages = $pdf->getPages();
            $totalPages = count($pages);

            $startPage = max(1, $startPage);
            $endPage = min($totalPages, $endPage);

            $texts = [];
            for ($i = $startPage - 1; $i < $endPage; $i++) {
                $texts[] = $pages[$i]->getText();
            }

            $result = $this->cleanAndTruncate(implode(' ', $texts), self::CHAPTER_MAX_CHARS);

            if (mb_strlen(trim($result)) < 50) {
                return $this->ocrFallback($fullPath, $startPage, $endPage, self::CHAPTER_MAX_CHARS);
            }

            return $result;
        } catch (\Exception $e) {
            Log::warning("Failed to parse PDF pages {$path}: {$e->getMessage()}");

            return $this->ocrFallback($fullPath, $startPage, $endPage, self::CHAPTER_MAX_CHARS);
        }
    }

    public function extractAllPagesText(string $path): array
    {
        $fullPath = Storage::disk('public')->path($path);

        if (! file_exists($fullPath)) {
            return [];
        }

        try {
            $pdf = $this->parsePdf($fullPath);
            $pages = $pdf->getPages();
            $result = [];
            $needsOcr = true;

            foreach ($pages as $index => $page) {
                $text = preg_replace('/\s+/', ' ', $page->getText());
                $text = trim($text);
                if (mb_strlen($text) > 10) {
                    $needsOcr = false;
                }
                $result[] = [
                    'page' => $index + 1,
                    'text' => $text,
                    'char_count' => mb_strlen($text),
                ];
            }

            if ($needsOcr) {
                return $this->ocrAllPages($fullPath);
            }

            return $result;
        } catch (\Exception $e) {
            Log::warning("Failed to extract all pages {$path}: {$e->getMessage()}");

            return $this->ocrAllPages($fullPath);
        }
    }

    public function getPageCount(string $path): int
    {
        $fullPath = Storage::disk('public')->path($path);

        if (! file_exists($fullPath)) {
            return 0;
        }

        // Prefer pdfinfo because it is lightweight, but keep a library fallback
        // for hosts where Poppler is not installed or returns incomplete output.
        $output = [];
        $returnCode = 1;
        exec('pdfinfo '.escapeshellarg($fullPath).' 2>/dev/null', $output, $returnCode);

        foreach ($output as $line) {
            if (preg_match('/^Pages:\s*(\d+)/i', trim($line), $matches)) {
                return (int) $matches[1];
            }
        }

        try {
            $pageCount = count($this->parsePdf($fullPath)->getPages());

            if ($pageCount > 0) {
                return $pageCount;
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to determine page count for {$path}: {$e->getMessage()}");
        }

        return 0;
    }

    public function extractAndCachePages(ReviewDocument $document): void
    {
        set_time_limit(300);

        $fullPath = Storage::disk('public')->path($document->file_path);

        if (! file_exists($fullPath)) {
            Log::warning("Document file not found: {$document->file_path}");

            return;
        }

        $document->pages()->delete();

        try {
            $tocPartitions = $document->partitions()
                ->where('has_toc', true)
                ->whereNull('parent_id')
                ->get(['end_page']);

            $startPage = 1;
            if ($tocPartitions->isNotEmpty()) {
                $startPage = $tocPartitions->max('end_page') + 1;
            }

            $pdf = $this->parsePdf($fullPath);
            $pdfPages = $pdf->getPages();
            $needsOcr = true;

            $batch = [];
            for ($i = $startPage - 1; $i < count($pdfPages); $i++) {
                $page = $pdfPages[$i];
                $pageNumber = $i + 1;
                $text = preg_replace('/\s+/', ' ', $page->getText());
                $text = trim($text);
                if (mb_strlen($text) > 10) {
                    $needsOcr = false;
                }
                $batch[] = [
                    'review_document_id' => $document->id,
                    'page_number' => $pageNumber,
                    'content' => preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text),
                    'char_count' => mb_strlen($text),
                ];
            }

            if ($needsOcr) {
                $ocrPages = $this->ocrAllPages($fullPath);
                $batch = [];
                foreach ($ocrPages as $page) {
                    if ($page['page'] >= $startPage) {
                        $batch[] = [
                            'review_document_id' => $document->id,
                            'page_number' => $page['page'],
                            'content' => $page['text'],
                            'char_count' => $page['char_count'],
                        ];
                    }
                }
            }

            foreach (array_chunk($batch, 50) as $chunk) {
                DocumentPage::insert($chunk);
            }

            $document->update(['parsed_at' => now()]);
        } catch (\Exception $e) {
            Log::warning("Failed to cache pages for {$document->file_path}: {$e->getMessage()}");
        }
    }

    private function ocrFallback(string $fullPath, ?int $startPage, ?int $endPage, int $maxChars): string
    {
        $tmpDir = sys_get_temp_dir().'/ocr_'.md5($fullPath).'_'.time();
        @mkdir($tmpDir, 0755, true);

        try {
            // Limit OCR to max 10 pages for performance
            if (! $startPage && ! $endPage) {
                $startPage = 1;
                $endPage = 10;
            }

            $pageRange = "-f {$startPage} -l {$endPage}";

            exec("pdftoppm {$pageRange} -png -r 150 ".escapeshellarg($fullPath).' '.escapeshellarg($tmpDir.'/page'), $output, $returnCode);

            if ($returnCode !== 0) {
                return '';
            }

            $images = glob($tmpDir.'/page-*.png');
            sort($images);

            $texts = [];
            foreach ($images as $image) {
                $text = (new TesseractOCR($image))->lang('ind', 'eng')->run();
                if (trim($text)) {
                    $texts[] = trim($text);
                }
            }

            return $this->cleanAndTruncate(implode("\n\n", $texts), $maxChars);
        } catch (\Exception $e) {
            Log::warning("OCR fallback failed for {$fullPath}: {$e->getMessage()}");

            return '';
        } finally {
            array_map('unlink', glob($tmpDir.'/*'));
            @rmdir($tmpDir);
        }
    }

    private function ocrAllPages(string $fullPath): array
    {
        $tmpDir = sys_get_temp_dir().'/ocr_all_'.md5($fullPath).'_'.time();
        @mkdir($tmpDir, 0755, true);

        try {
            exec('pdftoppm -png -r 200 '.escapeshellarg($fullPath).' '.escapeshellarg($tmpDir.'/page'), $output, $returnCode);

            if ($returnCode !== 0) {
                return [];
            }

            $images = glob($tmpDir.'/page-*.png');
            sort($images);

            $result = [];
            foreach ($images as $index => $image) {
                $text = (new TesseractOCR($image))->lang('ind', 'eng')->run();
                $text = trim($text);
                $result[] = [
                    'page' => $index + 1,
                    'text' => $text,
                    'char_count' => mb_strlen($text),
                ];
            }

            return $result;
        } catch (\Exception $e) {
            Log::warning("OCR all pages failed: {$e->getMessage()}");

            return [];
        } finally {
            array_map('unlink', glob($tmpDir.'/*'));
            @rmdir($tmpDir);
        }
    }

    private function parsePdf(string $fullPath): Document
    {
        if (! isset($this->parsedCache[$fullPath])) {
            $parser = new Parser;
            $this->parsedCache[$fullPath] = $parser->parseFile($fullPath);
        }

        return $this->parsedCache[$fullPath];
    }

    private function cleanAndTruncate(string $text, int $maxChars = self::MAX_CHARS): string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        $text = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);

        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars).'... [konten dipotong]';
        }

        return $text;
    }
}
