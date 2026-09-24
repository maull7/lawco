<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationDocument;
use App\Models\RegulationType;
use App\Services\DocumentParser;
use App\Services\RegulationParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class RegulationDocumentParseTest extends TestCase
{
    use RefreshDatabase;

    public function test_docx_is_parsed_to_text(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('regulation-documents/fixture.docx', $this->buildDocx('Lampiran konten reksa dana investasi.'));

        $regulation = $this->makeRegulation();

        $document = RegulationDocument::create([
            'regulation_id' => $regulation->id,
            'name' => 'fixture',
            'document_type' => 'lampiran',
            'file_path' => 'regulation-documents/fixture.docx',
        ]);

        $service = new RegulationParserService(new DocumentParser);
        $result = $service->parseDocument($document);

        $document = $document->fresh();

        $this->assertTrue($result['success']);
        $this->assertSame('complete', $document->parse_status);
        $this->assertStringContainsString('reksa dana investasi', $document->parsed_text);
        $this->assertSame('docx', $document->parse_stats['doc_type']);
    }

    public function test_unsupported_format_returns_error(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('regulation-documents/fixture.txt', 'teks biasa');

        $document = RegulationDocument::create([
            'regulation_id' => $this->makeRegulation()->id,
            'name' => 'fixture',
            'document_type' => 'lampiran',
            'file_path' => 'regulation-documents/fixture.txt',
        ]);

        $result = (new RegulationParserService(new DocumentParser))->parseDocument($document);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('PDF dan DOCX', $result['message']);
    }

    private function makeRegulation(): Regulation
    {
        $type = RegulationType::create(['name' => 'POJK', 'level' => 1]);
        $category = RegulationCategory::create(['name' => 'Kontrak Investasi Kolektif']);

        return Regulation::create([
            'regulation_number' => 'POJK/01/2026',
            'title' => 'Regulasi Test',
            'regulation_type_id' => $type->id,
            'category_id' => $category->id,
            'year' => 2026,
            'file_path' => 'regulations/fixture.pdf',
        ]);
    }

    private function buildDocx(string $content): string
    {
        $zip = new ZipArchive;
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body><w:p><w:r><w:t>'.$content.'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }
}