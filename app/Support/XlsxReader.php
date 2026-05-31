<?php

namespace App\Support;

/**
 * Minimal, dependency-free XLSX reader (uses only ZipArchive + SimpleXML, both standard PHP).
 *
 * Its purpose for the Veeva catalog is to recover HYPERLINK TARGETS: in Veeva exports the visible
 * document number is often a hyperlink whose URL (the ...permalink=V0Z... link) is stored separately
 * in the cell, and CSV would drop it. This reader returns both the cell text grid and, per row, any
 * hyperlink URLs found, so the importer can use an embedded link even when there is no link column.
 *
 * Not a general-purpose spreadsheet engine: it reads the first worksheet, shared strings, inline
 * strings, and the sheet's hyperlink relationships. That is all the catalog import needs.
 */
class XlsxReader
{
    /**
     * Read the first sheet of an xlsx file.
     *
     * @return array{rows: array<int,array<int,string>>, hyperlinks: array<int,array<int,string>>}
     *   rows: 0-indexed rows, each a 0-indexed array of cell strings.
     *   hyperlinks: same row/col indexing, value is the URL for cells that carry a hyperlink.
     */
    public static function read(string $path): array
    {
        $out = ['rows' => [], 'hyperlinks' => []];
        if (! class_exists(\ZipArchive::class)) {
            return $out;
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return $out;
        }

        // Shared strings (cell text is often referenced by index here).
        $shared = [];
        if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $xml = @simplexml_load_string($ss);
            if ($xml !== false) {
                foreach ($xml->si as $si) {
                    $shared[] = self::siText($si);
                }
            }
        }

        // First worksheet path (resolve via workbook rels; default to sheet1.xml).
        $sheetPath = 'xl/worksheets/sheet1.xml';
        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            // find the first worksheet entry
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                    $sheetPath = $name;
                    $sheetXml = $zip->getFromName($name);
                    break;
                }
            }
        }
        if ($sheetXml === false) { $zip->close(); return $out; }

        // Hyperlink relationships for this sheet: r:id -> target URL.
        $relTargets = [];
        $relName = preg_replace('#worksheets/(sheet\d+)\.xml$#', 'worksheets/_rels/$1.xml.rels', $sheetPath);
        if (($rels = $zip->getFromName($relName)) !== false) {
            $rx = @simplexml_load_string($rels);
            if ($rx !== false) {
                foreach ($rx->Relationship as $rel) {
                    $id = (string) $rel['Id'];
                    $type = (string) $rel['Type'];
                    if (str_contains($type, 'hyperlink')) {
                        $relTargets[$id] = (string) $rel['Target'];
                    }
                }
            }
        }

        $sx = @simplexml_load_string($sheetXml);
        if ($sx === false) { $zip->close(); return $out; }

        // Cell grid.
        $rows = [];
        foreach ($sx->sheetData->row as $row) {
            $r = (int) $row['r'] - 1;
            if ($r < 0) $r = count($rows);
            foreach ($row->c as $c) {
                [$colIdx] = self::refToColRow((string) $c['r']);
                $val = self::cellValue($c, $shared);
                $rows[$r][$colIdx] = $val;
            }
        }
        // Normalize to dense 0-indexed arrays.
        $maxCol = 0;
        foreach ($rows as $cells) {
            foreach (array_keys($cells) as $k) $maxCol = max($maxCol, $k);
        }
        $dense = [];
        foreach ($rows as $r => $cells) {
            $line = [];
            for ($i = 0; $i <= $maxCol; $i++) $line[$i] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            $dense[$r] = $line;
        }
        ksort($dense);
        $out['rows'] = array_values($dense);

        // Hyperlinks: map each <hyperlink ref="A2" r:id="rId1"/> to its row/col + URL.
        if (isset($sx->hyperlinks)) {
            $ns = $sx->getNamespaces(true);
            foreach ($sx->hyperlinks->hyperlink as $hl) {
                $ref = (string) $hl['ref'];
                $rid = '';
                if (isset($ns['r'])) {
                    $ratt = $hl->attributes($ns['r']);
                    $rid = (string) ($ratt['id'] ?? '');
                }
                $target = $rid && isset($relTargets[$rid]) ? $relTargets[$rid] : (string) ($hl['location'] ?? '');
                if ($target === '') continue;
                // ref can be a single cell or a range; take the top-left.
                $first = explode(':', $ref)[0];
                [$col, $rowNum] = self::refToColRow($first);
                $out['hyperlinks'][$rowNum][$col] = $target;
            }
        }

        $zip->close();
        return $out;
    }

    /** Text of a shared-string <si> (handles plain <t> and rich-text runs). */
    protected static function siText(\SimpleXMLElement $si): string
    {
        if (isset($si->t)) return (string) $si->t;
        $buf = '';
        foreach ($si->r as $r) $buf .= (string) $r->t;
        return $buf;
    }

    /** Resolve a cell value, dereferencing shared/inline strings. */
    protected static function cellValue(\SimpleXMLElement $c, array $shared): string
    {
        $type = (string) $c['t'];
        if ($type === 's') {
            $idx = (int) $c->v;
            return $shared[$idx] ?? '';
        }
        if ($type === 'inlineStr' && isset($c->is)) {
            return self::siText($c->is);
        }
        if (isset($c->v)) return (string) $c->v;
        return '';
    }

    /** "B3" -> [colIndex0=1, rowIndex0=2]. */
    protected static function refToColRow(string $ref): array
    {
        if (! preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) return [0, 0];
        $col = 0;
        foreach (str_split($m[1]) as $ch) {
            $col = $col * 26 + (ord($ch) - 64);
        }
        return [$col - 1, (int) $m[2] - 1];
    }

    /**
     * Detect the file format. Many systems (Salesforce/TrackWise "Export to Excel", older Veeva)
     * produce an HTML <table> with an .xls/.xlsx extension rather than a real OOXML zip. We sniff the
     * first bytes: 'PK' = real xlsx zip; a leading '<' or an <html>/<table> tag = HTML table.
     *
     * @return 'xlsx'|'html'|'csv'
     */
    public static function detectFormat(string $path): string
    {
        $fh = fopen($path, 'rb');
        if (! $fh) return 'csv';
        $head = fread($fh, 1024);
        fclose($fh);
        if (substr($head, 0, 2) === 'PK') return 'xlsx';
        $lower = strtolower($head);
        if (str_contains($lower, '<table') || str_contains($lower, '<html') || str_contains($lower, '<tr') || ltrim($head)[0] === '<') {
            return 'html';
        }
        return 'csv';
    }

    /**
     * Parse an HTML-table export into a row grid (and any <a href> hyperlinks per cell). Handles the
     * Salesforce/TrackWise "Excel" export which is really HTML. Tabs inside a cell (common in these
     * exports for multi-value fields) are collapsed to a space so they do not split columns.
     *
     * @return array{rows: array<int,array<int,string>>, hyperlinks: array<int,array<int,string>>}
     */
    public static function readHtml(string $path): array
    {
        $out = ['rows' => [], 'hyperlinks' => []];
        $html = @file_get_contents($path);
        if ($html === false || $html === '') return $out;

        // Raise PCRE limits; large exports (thousands of rows) can otherwise hit backtrack limits.
        @ini_set('pcre.backtrack_limit', '20000000');
        @ini_set('pcre.recursion_limit', '20000000');

        // Split into <tr> chunks by boundary rather than one giant lazy regex (safer on big files).
        $trChunks = preg_split('#</tr\s*>#is', $html);
        if ($trChunks === false) return $out;

        $r = 0;
        foreach ($trChunks as $chunk) {
            // keep only the part from the first <tr...> onward
            $pos = stripos($chunk, '<tr');
            if ($pos === false) continue;
            $chunk = substr($chunk, $pos);

            // split into cells on closing td/th tags
            $cellChunks = preg_split('#</t[dh]\s*>#is', $chunk);
            if ($cellChunks === false) continue;

            $cells = [];
            $links = [];
            $c = 0;
            foreach ($cellChunks as $cellChunk) {
                // must contain an opening td/th to be a real cell
                if (! preg_match('#<t[dh]\b[^>]*>(.*)$#is', $cellChunk, $cm)) continue;
                $cellHtml = $cm[1];
                if (preg_match('#<a\b[^>]*href=["\']([^"\']+)["\']#i', $cellHtml, $hm)) {
                    $links[$c] = html_entity_decode($hm[1], ENT_QUOTES | ENT_HTML5);
                }
                $text = preg_replace('#<br\s*/?>#i', ' ', $cellHtml);
                $text = strip_tags($text);
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
                $text = str_replace(["\t", "\r", "\n"], ' ', $text);
                $text = trim(preg_replace('/\s+/', ' ', (string) $text));
                $cells[$c] = $text;
                $c++;
            }
            if (empty($cells)) continue;
            if (count(array_filter($cells, fn ($v) => $v !== '')) === 0) continue;
            $out['rows'][$r] = $cells;
            if ($links) $out['hyperlinks'][$r] = $links;
            $r++;
        }
        return $out;
    }
}
