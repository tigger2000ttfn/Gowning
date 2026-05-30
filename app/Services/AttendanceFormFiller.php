<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

/**
 * Fills the Astellas Class Training Form (FORM-AST-36513) by overlaying text onto the
 * exact original PDF using FPDI. Coordinates are in PDF points (72/inch) measured from
 * the top-left, matching the source layout (Letter landscape, 792 x 612 pt).
 *
 * The page geometry was measured from the real template:
 *  - Page 1 Section 1 (Trainer): training date, document #, revision #, title.
 *  - Page 1 Section 2 (Trainees): 16 rows. Columns: Name 36-405, Signature 405-531,
 *    Department 531-639, Date 639-760. First row top ~218, row pitch ~19.3 pt.
 *  - Page 2 Section 2 continued: 14 rows, first row top ~107.
 *  - Page 2 Section 3 (Trainer) and Section 4 (Coordinator) name cells.
 */
class AttendanceFormFiller
{
    protected string $template;

    // Section 2 table geometry (points from top-left).
    protected float $colNameX = 42;       // left padding inside Name column
    protected float $colDeptX = 537;      // Department column left
    protected float $colDateX = 645;      // Date column left
    protected float $rowPitch = 19.3;     // vertical distance between rows

    protected int $page1Rows = 16;
    protected float $page1FirstRowBaseline = 232;  // baseline for first data row text on p1
    protected int $page2Rows = 14;
    protected float $page2FirstRowBaseline = 121;  // baseline for first data row text on p2

    public function __construct()
    {
        $this->template = resource_path('pdf-templates/FORM-AST-36513.pdf');
    }

    /**
     * @param array $header  ['training_date'=>'', 'document_no'=>'', 'revision_no'=>'', 'title'=>'',
     *                        'trainer_name'=>'']
     * @param array $trainees  list of ['name'=>'', 'employee_id'=>'', 'department'=>'', 'date'=>'']
     *                         (name printed + department prefilled; signature left blank to sign)
     * @return string  raw PDF bytes
     */
    public function fill(array $header, array $trainees): string
    {
        $pdf = new Fpdi('L', 'pt', 'Letter');
        $pageCount = $pdf->setSourceFile($this->template);

        // ---------- PAGE 1 ----------
        $pdf->AddPage();
        $tpl1 = $pdf->importPage(1);
        $pdf->useTemplate($tpl1, 0, 0, 792, 612);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', 9);

        // Section 1 header fields. Smaller font so a long title/Document # stays inside its cell.
        $pdf->SetFont('Helvetica', '', 8);
        // "Training Date:" label sits at row 92-116; value cell is right of the x=216 divider.
        if (! empty($header['training_date'])) $this->text($pdf, 230, 110, $header['training_date']);
        // Columns (from the grid): Document # 36-283, Revision # 283-328, Title 328-760.
        if (! empty($header['document_no']))   $this->text($pdf, 42, 162, $header['document_no']);
        if (! empty($header['revision_no']))   $this->text($pdf, 290, 162, $header['revision_no']);
        if (! empty($header['title']))         $this->text($pdf, 333, 162, $header['title']);
        $pdf->SetFont('Helvetica', '', 9);

        // Section 2 trainees, page 1 (rows 0..15)
        $rowsP1 = array_slice($trainees, 0, $this->page1Rows);
        foreach ($rowsP1 as $i => $t) {
            $y = $this->page1FirstRowBaseline + ($i * $this->rowPitch);
            $this->row($pdf, $y, $t);
        }

        // ---------- PAGE 2 ----------
        if ($pageCount >= 2) {
            $pdf->AddPage();
            $tpl2 = $pdf->importPage(2);
            $pdf->useTemplate($tpl2, 0, 0, 792, 612);
            $pdf->SetFont('Helvetica', '', 9);

            $rowsP2 = array_slice($trainees, $this->page1Rows, $this->page2Rows);
            foreach ($rowsP2 as $i => $t) {
                $y = $this->page2FirstRowBaseline + ($i * $this->rowPitch);
                $this->row($pdf, $y, $t);
            }

            // Section 3 Trainer name + date (coordinator is filled on paper at training time)
            if (! empty($header['trainer_name'])) $this->text($pdf, 42, 437, $header['trainer_name']);
            if (! empty($header['trainer_date'])) $this->text($pdf, 645, 437, $header['trainer_date']);
        }

        return $pdf->Output('S');
    }

    /** One trainee row: Name (Printed, full name) + Department + Date. Signature left blank to sign. */
    protected function row(Fpdi $pdf, float $baselineY, array $t): void
    {
        $name = trim((string) ($t['name'] ?? ''));
        if ($name !== '') $this->text($pdf, $this->colNameX, $baselineY, $name);
        if (! empty($t['department'])) $this->text($pdf, $this->colDeptX, $baselineY, (string) $t['department']);
        if (! empty($t['date']))       $this->text($pdf, $this->colDateX, $baselineY, (string) $t['date']);
    }

    /** Place text at (x, y) where y is the text baseline measured from the top of the page. */
    protected function text(Fpdi $pdf, float $x, float $y, string $s): void
    {
        // FPDI Text() uses the baseline; our y values are baselines from the top.
        $pdf->Text($x, $y, $this->ascii($s));
    }

    /** FPDF core fonts are latin-1; strip anything that would render as garbage. */
    protected function ascii(string $s): string
    {
        $s = str_replace(['—', '–', '·', '’', '‘', '“', '”'], ['-', '-', '-', "'", "'", '"', '"'], $s);
        return @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s) ?: $s;
    }
}
