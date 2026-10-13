<?php

namespace App\Services;

use App\Models\Regulation;
use App\Repositories\RegulationRepository;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use XMLWriter;
use ZipArchive;

class RegulationExcelExporter
{
    public function download(Builder $query): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'lawco-regulations-');
        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat file Excel.');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Tidak dapat membuka file Excel.');
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Regulasi" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheet($query));
            if (! $zip->close()) {
                throw new RuntimeException('Tidak dapat menyimpan file Excel.');
            }

            return response()->download($path, 'regulasi-'.now()->format('Ymd-His').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }
            throw $exception;
        }
    }

    private function worksheet(Builder $query): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNS(null, 'worksheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xml->startElement('sheetViews');
        $xml->startElement('sheetView');
        $xml->writeAttribute('workbookViewId', '0');
        $xml->startElement('pane');
        $xml->writeAttribute('ySplit', '1');
        $xml->writeAttribute('topLeftCell', 'A2');
        $xml->writeAttribute('state', 'frozen');
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->startElement('sheetData');
        $row = 1;
        $this->writeRow($xml, $row, ['No. Regulasi', 'Judul', 'Jenis', 'Sektor', 'Kategori', 'Sub Kategori', 'Tahun', 'Tanggal Diundangkan', 'Sudah Diparse', 'Status Parse', 'Tanggal Parse']);
        foreach ($query->lazy(200)->take(RegulationRepository::RESULT_LIMIT) as $regulation) {
            $this->writeRow($xml, ++$row, $this->values($regulation));
        }
        $xml->endElement();
        $xml->startElement('autoFilter');
        $xml->writeAttribute('ref', 'A1:K'.$row);
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /** @return array<int, string> */
    private function values(Regulation $regulation): array
    {
        return [
            $regulation->regulation_number,
            $regulation->title,
            $regulation->type?->name ?? '',
            $regulation->sector?->name ?? '',
            $regulation->category?->name ?? '',
            $regulation->subCategories->pluck('name')->implode(', '),
            (string) $regulation->year,
            $regulation->tanggal_diundangkan?->format('Y-m-d') ?? '',
            $regulation->parse_status === 'complete' ? 'Sudah' : 'Belum',
            $regulation->parseStatusLabel(),
            $regulation->parsed_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    /** @param array<int, string> $values */
    private function writeRow(XMLWriter $xml, int $row, array $values): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $row);
        foreach ($values as $index => $value) {
            $xml->startElement('c');
            $xml->writeAttribute('r', chr(65 + $index).$row);
            $xml->writeAttribute('t', 'inlineStr');
            $xml->startElement('is');
            $xml->startElement('t');
            $xml->writeAttribute('xml:space', 'preserve');
            $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
            $xml->text(mb_substr($value, 0, 32767));
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();
    }
}
