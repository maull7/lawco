<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class RegulationExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_matches_filters_across_pages_and_contains_parse_information(): void
    {
        $sector = Sector::factory()->create(['name' => 'Keuangan']);
        $categories = RegulationCategory::factory()->count(2)->create(['name' => 'Perbankan', 'sector_id' => $sector->id]);
        $rows = Regulation::factory()->count(16)->create([
            'sector_id' => $sector->id, 'category_id' => $categories[0]->id,
            'year' => 2026, 'tanggal_diundangkan' => '2026-01-15', 'parse_status' => 'complete',
        ]);
        $rows->last()->update(['category_id' => $categories[1]->id, 'parse_status' => 'incomplete', 'title' => '=HYPERLINK("test")']);
        $excluded = Regulation::factory()->create(['sector_id' => $sector->id, 'category_id' => $categories[0]->id, 'year' => 2025]);
        $filters = ['sector_id' => $sector->id, 'category_keyword' => 'bank', 'year' => 2026,
            'start_date' => '2026-01-01', 'end_date' => '2026-01-31'];
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.index', $filters))
            ->assertOk()->assertViewHas('regulations', fn ($results) => $results->total() === 16 && $results->count() === 15);
        $response = $this->get(route('regulations.export', [...$filters, 'page' => 2]));
        $response->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $sheet = $this->worksheet($response->baseResponse);
        $this->assertCount(17, $sheet->sheetData->row);
        $text = $sheet->asXML();
        foreach ($rows as $row) {
            $this->assertStringContainsString($row->regulation_number, $text);
        }
        $this->assertStringNotContainsString($excluded->regulation_number, $text);
        $this->assertStringContainsString('Sudah Diparse', $text);
        $this->assertStringContainsString('Keuangan', $text);
        $this->assertStringContainsString('Complete', $text);
        $this->assertStringContainsString('InComplete', $text);
        $this->assertStringContainsString('Sudah', $text);
        $this->assertStringContainsString('Belum', $text);
        $this->assertStringContainsString('=HYPERLINK', $text);
        $this->assertCount(0, $sheet->xpath('//s:f'));
    }

    public function test_export_matches_document_content_search_and_all_parse_statuses(): void
    {
        foreach (['not_parsed', 'parsing', 'failed', 'incomplete', 'complete'] as $status) {
            Regulation::factory()->create(['parse_status' => $status, 'parsed_text' => 'kata ekspor']);
        }
        $excluded = Regulation::factory()->create(['parsed_text' => 'konten lain']);
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('regulations.export', ['search_content' => 'kata ekspor']));
        $response->assertOk();
        $sheet = $this->worksheet($response->baseResponse);
        $this->assertCount(6, $sheet->sheetData->row);
        $this->assertStringNotContainsString($excluded->regulation_number, $sheet->asXML());
        foreach (['Not Parsed', 'Parsing', 'Failed', 'InComplete', 'Complete'] as $label) {
            $this->assertStringContainsString($label, $sheet->asXML());
        }
    }

    public function test_export_respects_sector_visibility(): void
    {
        $public = Sector::factory()->create(['is_public' => true]);
        $private = Sector::factory()->create(['is_public' => false]);
        $visible = Regulation::factory()->create(['sector_id' => $public->id]);
        $hidden = Regulation::factory()->create(['sector_id' => $private->id]);
        $response = $this->actingAs(User::factory()->create(['role' => 'user']))->get(route('regulations.export'));
        $response->assertOk();
        $xml = $this->worksheet($response->baseResponse)->asXML();
        $this->assertStringContainsString($visible->regulation_number, $xml);
        $this->assertStringNotContainsString($hidden->regulation_number, $xml);
    }

    public function test_empty_export_contains_headers_and_guests_cannot_export(): void
    {
        $this->get(route('regulations.export'))->assertRedirect(route('login'));
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('regulations.export'));
        $response->assertOk();
        $this->assertCount(1, $this->worksheet($response->baseResponse)->sheetData->row);
        $this->get(route('regulations.export', ['category_keyword' => str_repeat('a', 256)]))
            ->assertSessionHasErrors('category_keyword');
    }

    private function worksheet(BinaryFileResponse $response): \SimpleXMLElement
    {
        $path = $response->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels'] as $part) {
            $this->assertNotFalse(simplexml_load_string($zip->getFromName($part)));
        }
        $zip->close();
        unlink($path);
        $sheet = simplexml_load_string($xml);
        $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $sheet;
    }
}
