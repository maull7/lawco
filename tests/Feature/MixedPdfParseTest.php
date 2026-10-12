<?php

namespace Tests\Feature;

use App\Exceptions\ParsingCancelledException;
use App\Jobs\ParseRegulationChunk;
use App\Models\Regulation;
use App\Models\RegulationDocument;
use App\Services\RegulationParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MixedPdfParseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! is_executable('/usr/bin/tesseract') || ! is_executable('/usr/bin/pdftoppm') || ! is_executable('/usr/bin/pdftotext')) {
            $this->markTestSkipped('Poppler and Tesseract are required for real PDF recovery tests.');
        }
        Storage::fake('public');
    }

    public function test_mixed_regulation_recovers_scanned_page_and_records_real_coverage(): void
    {
        $regulation = $this->regulation(['text', 'scan']);
        (new ParseRegulationChunk($regulation, 1, 2, 2, 'text'))->handle(app(RegulationParserService::class));
        $regulation->refresh();

        $this->assertSame('complete', $regulation->parse_status);
        $this->assertStringContainsString('NATIVE PAGE', $regulation->parsed_text);
        $this->assertStringContainsString('SCAN', strtoupper($regulation->parsed_text));
        $this->assertSame(100, $regulation->parse_stats['percent_parsed']);
        $this->assertSame(1, $regulation->parse_stats['normal_pages']);
        $this->assertSame(1, $regulation->parse_stats['ocr_pages']);
        $this->assertSame('mixed', $regulation->parse_stats['method']);
        $this->assertSame([], $regulation->parse_stats['unread_page_numbers']);
    }

    public function test_mixed_attachment_recovers_scanned_page_instead_of_calling_it_empty(): void
    {
        $regulation = $this->regulation(['text', 'scan']);
        $document = RegulationDocument::factory()->for($regulation)->create(['file_path' => $regulation->file_path]);
        $result = app(RegulationParserService::class)->parseDocumentChoice($document, 'text');
        $document->refresh();

        $this->assertTrue($result['success']);
        $this->assertSame('complete', $document->parse_status);
        $this->assertSame(0, $document->parse_stats['empty_pages']);
        $this->assertSame(1, $document->parse_stats['ocr_pages']);
        $this->assertTrue($document->parse_stats['used_ocr']);
        $this->assertSame('mixed', $document->parse_stats['pdf_type']);
        $this->assertStringContainsString('SCAN', strtoupper($document->parsed_text));
    }

    public function test_unread_pages_remain_reported_and_do_not_inflate_the_percentage(): void
    {
        $regulation = $this->regulation(['text', 'scan', 'blank']);
        (new ParseRegulationChunk($regulation, 1, 3, 3, 'text'))->handle(app(RegulationParserService::class));
        $regulation->refresh();

        $this->assertSame('incomplete', $regulation->parse_status);
        $this->assertSame(67, $regulation->parse_stats['percent_parsed']);
        $this->assertSame([3], $regulation->parse_stats['unread_page_numbers']);
        $this->assertSame(2, $regulation->parse_stats['parsed_pages']);
        $this->assertSame(1, $regulation->parse_stats['empty_pages']);
    }

    public function test_ninety_percent_threshold_is_applied_to_actual_page_coverage(): void
    {
        $regulation = $this->regulation([...array_fill(0, 9, 'text'), 'blank']);
        (new ParseRegulationChunk($regulation, 1, 5, 10, 'text'))->handle(app(RegulationParserService::class));
        (new ParseRegulationChunk($regulation, 6, 10, 10, 'text'))->handle(app(RegulationParserService::class));
        $regulation->refresh();
        $this->assertSame('complete', $regulation->parse_status);
        $this->assertSame(90, $regulation->parse_stats['percent_parsed']);
        $this->assertSame([10], $regulation->parse_stats['unread_page_numbers']);

        $document = RegulationDocument::factory()->for($regulation)->create(['file_path' => $regulation->file_path]);
        app(RegulationParserService::class)->parseDocumentChoice($document, 'text');
        $document->refresh();
        $this->assertSame('complete', $document->parse_status);
        $this->assertSame(90, $document->parse_stats['percent_parsed']);
        $this->assertSame([10], $document->parse_stats['unread_page_numbers']);
    }

    public function test_cancellation_during_ocr_recovery_is_propagated(): void
    {
        $regulation = $this->regulation(['text', 'scan']);
        $calls = 0;
        $this->expectException(ParsingCancelledException::class);
        app(RegulationParserService::class)->parseRegulationChunk($regulation, 1, 2, 'text', function () use (&$calls): void {
            $calls++;
            if ($calls === 4) {
                throw new ParsingCancelledException('Cancelled during OCR.');
            }
        });
    }

    public function test_supplied_production_samples_reach_the_requested_coverage(): void
    {
        $directory = getenv('LAWCO_PARSE_SAMPLE_DIR');
        if (! $directory) {
            $this->markTestSkipped('Set LAWCO_PARSE_SAMPLE_DIR to verify downloaded production PDF samples.');
        }
        foreach (['regulation-341.pdf', 'document-597.pdf'] as $filename) {
            Storage::disk('public')->put('regulations/'.$filename, file_get_contents($directory.'/'.$filename));
            $regulation = Regulation::factory()->create([
                'file_path' => 'regulations/'.$filename,
                'parse_status' => 'parsing',
                'parse_stats' => ['processed_chunks' => []],
            ]);
            $parser = app(RegulationParserService::class);
            $totalPages = $parser->getPageCount($regulation->file_path);
            $this->assertGreaterThan(0, $totalPages);
            for ($start = 1; $start <= $totalPages; $start += ParseRegulationChunk::CHUNK_SIZE) {
                (new ParseRegulationChunk($regulation, $start, min($totalPages, $start + ParseRegulationChunk::CHUNK_SIZE - 1), $totalPages, 'text'))->handle($parser);
            }
            $regulation->refresh();
            $this->assertGreaterThanOrEqual(90, $regulation->parse_stats['percent_parsed'], $filename.' unread pages: '.json_encode($regulation->parse_stats['unread_page_numbers']));
            $this->assertSame('complete', $regulation->parse_status);
        }
    }

    /** @param array<int, string> $pages */
    private function regulation(array $pages): Regulation
    {
        Storage::disk('public')->put('regulations/mixed.pdf', $this->pdf($pages));

        return Regulation::factory()->create([
            'file_path' => 'regulations/mixed.pdf',
            'parse_status' => 'parsing',
            'parse_stats' => ['total_pages' => count($pages), 'processed_chunks' => []],
        ]);
    }

    /** @param array<int, string> $pages */
    private function pdf(array $pages): string
    {
        $width = 640;
        $height = 100;
        $image = file_get_contents(base_path('tests/Fixtures/scan-content.rgb.zlib'));
        $kids = implode(' ', array_map(fn (int $index): string => (5 + $index * 2).' 0 R', array_keys($pages)));
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pages).' >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /XObject /Subtype /Image /Width '.$width.' /Height '.$height.' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length '.strlen($image).">>\nstream\n".$image."\nendstream",
        ];
        foreach ($pages as $index => $type) {
            $stream = match ($type) {
                'text' => 'BT /F1 18 Tf 50 740 Td (NATIVE PAGE CONTENT '.($index + 1).') Tj ET',
                'scan' => 'q 540 0 0 84 40 650 cm /Scan Do Q',
                default => '',
            };
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> /XObject << /Scan 4 0 R >> >> /Contents '.(6 + $index * 2).' 0 R >>';
            $objects[] = '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";
        }
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".count($offsets)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        return $pdf.'trailer << /Size '.count($offsets).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF";
    }
}
