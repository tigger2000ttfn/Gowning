<?php

namespace App\Support;

/**
 * Minimal, dependency-free XLSX writer. An .xlsx is just a ZIP of XML parts,
 * so this needs only ext-zip (ubiquitous) - no phpspreadsheet, maatwebsite, gd, etc.
 *
 * Usage:
 *   $xlsx = new SimpleXlsx('Overdue');
 *   $xlsx->sheet('Overdue', [['Header A','Header B'], ['v1','v2'], ...]);
 *   return $xlsx->download('report.xlsx');
 */
class SimpleXlsx
{
    /** @var array<string, array<int, array<int, string|int|float|null>>> */
    protected array $sheets = [];

    public function __construct(?string $firstSheet = null)
    {
        // no-op; sheets added via sheet()
    }

    /** Add a worksheet. $rows is an array of rows; each row an array of cell values. */
    public function sheet(string $name, array $rows): static
    {
        // sanitize sheet name (max 31 chars, no special chars)
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $name);
        $name = trim(mb_substr($name, 0, 31)) ?: 'Sheet';
        $this->sheets[$name] = $rows;
        return $this;
    }

    protected function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected function colRef(int $col): string
    {
        $ref = '';
        $col++;
        while ($col > 0) {
            $mod = ($col - 1) % 26;
            $ref = chr(65 + $mod) . $ref;
            $col = intdiv($col - $mod, 26);
        }
        return $ref;
    }

    protected function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $r => $row) {
            $rowNum = $r + 1;
            $xml .= '<row r="' . $rowNum . '">';
            foreach (array_values($row) as $c => $val) {
                $ref = $this->colRef($c) . $rowNum;
                if (is_int($val) || is_float($val)) {
                    $xml .= '<c r="' . $ref . '"><v>' . $val . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                        . $this->esc((string) ($val ?? '')) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    public function build(): string
    {
        $names = array_keys($this->sheets);
        if (empty($names)) { $this->sheet('Sheet1', []); $names = array_keys($this->sheets); }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . implode('', array_map(fn ($i) => '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>', array_keys($names)))
            . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $sheetsXml = '';
        $relsXml = '';
        foreach ($names as $i => $name) {
            $n = $i + 1;
            $sheetsXml .= '<sheet name="' . $this->esc($name) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $relsXml .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', $this->sheetXml($this->sheets[$name]));
        }

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetsXml . '</sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relsXml . '</Relationships>');

        $zip->close();
        $data = file_get_contents($tmp);
        @unlink($tmp);
        return $data;
    }

    public function download(string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->build();
        return response()->streamDownload(function () use ($data) {
            echo $data;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
