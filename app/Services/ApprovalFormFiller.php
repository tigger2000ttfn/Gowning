<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

/**
 * Fills the Astellas MATC QCM Gowning Qualification Approval form (FORM-AST-36749)
 * by overlaying text/checkmarks onto the exact original PDF using FPDI.
 * Portrait Letter, 612 x 792 pt; coordinates measured from the top-left of the template.
 *
 * This is the form QA approves after results come in. GQS prefills everything the
 * analyst/engine already knows: person, qualification type, pass/fail outcome,
 * QCM completer + date, and the next sample (due) date. QA verifies and signs.
 */
class ApprovalFormFiller
{
    protected string $template;

    public function __construct()
    {
        $uploaded = storage_path('app/pdf-templates/FORM-AST-36749.pdf');
        $this->template = is_file($uploaded) ? $uploaded : resource_path('pdf-templates/FORM-AST-36749.pdf');
    }

    /**
     * @param array $d keys:
     *   personnel_name, is_initial(bool), is_requal(bool),
     *   result_initial ('yes'|'no'|'na'), result_requal ('yes'|'no'|'na'),
     *   passed(bool|null) -> Section 3 (true=met, false=not met),
     *   results_attached(bool), qcm_by, qcm_date, qa_by, qa_date,
     *   registered(bool), next_sample_date, qa_completed_by, qa_completed_date
     * @return string raw PDF bytes
     */
    public function fill(array $d): string
    {
        $pdf = new Fpdi('P', 'pt', 'Letter');
        $pdf->setSourceFile($this->template);
        $pdf->AddPage();
        $tpl = $pdf->importPage(1);
        $pdf->useTemplate($tpl, 0, 0, 612, 792);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', 10);

        // Section 1: Personnel Sampled (value cell starts right of the x=165 divider)
        if (! empty($d['personnel_name'])) $this->text($pdf, 175, 135, $d['personnel_name']);

        // Section 2: Qualification type checkboxes
        if (! empty($d['is_initial'])) $this->check($pdf, 123, 180);
        if (! empty($d['is_requal']))  $this->check($pdf, 385, 180);

        // Section 2 results: left group = 3 consecutive runs (initial), right group = met spec (requal)
        $this->resultBoxes($pdf, $d['result_initial'] ?? null, 125, 166, 203, 236);
        $this->resultBoxes($pdf, $d['result_requal'] ?? null, 377, 418, 455, 236);

        // Section 3: verification checkboxes
        if (($d['passed'] ?? null) === true)  $this->check($pdf, 59, 291);   // passing criteria met
        if (($d['passed'] ?? null) === false) $this->check($pdf, 59, 328);   // did NOT meet
        if (! empty($d['results_attached']))  $this->check($pdf, 59, 364);   // all results attached

        // QCM Completed By / Date  (value cell right of x=165; date cell right of x=390)
        if (! empty($d['qcm_by']))   $this->text($pdf, 175, 408, $d['qcm_by']);
        if (! empty($d['qcm_date'])) $this->text($pdf, 398, 408, $d['qcm_date']);

        // QA Verified By / Date
        if (! empty($d['qa_by']))    $this->text($pdf, 175, 434, $d['qa_by']);
        if (! empty($d['qa_date']))  $this->text($pdf, 398, 434, $d['qa_date']);

        // Section 4: registered checkbox, next sample date, QA completed by/date
        if (! empty($d['registered']))        $this->check($pdf, 59, 473);
        if (! empty($d['next_sample_date']))  $this->text($pdf, 398, 517, $d['next_sample_date']);
        if (! empty($d['qa_completed_by']))   $this->text($pdf, 175, 543, $d['qa_completed_by']);
        if (! empty($d['qa_completed_date'])) $this->text($pdf, 398, 543, $d['qa_completed_date']);

        return $pdf->Output('S');
    }

    /** Place the result Yes/No/N/A check. */
    protected function resultBoxes(Fpdi $pdf, ?string $val, float $yesX, float $noX, float $naX, float $y): void
    {
        $val = strtolower((string) $val);
        if ($val === 'yes') $this->check($pdf, $yesX, $y);
        elseif ($val === 'no') $this->check($pdf, $noX, $y);
        elseif ($val === 'na' || $val === 'n/a') $this->check($pdf, $naX, $y);
    }

    protected function text(Fpdi $pdf, float $x, float $y, string $s): void
    {
        $pdf->Text($x, $y, $this->ascii($s));
    }

    /** Stamp an X centered on a checkbox glyph at (x, top-of-box). */
    protected function check(Fpdi $pdf, float $x, float $y): void
    {
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Text($x + 0.5, $y + 9.5, 'X');
        $pdf->SetFont('Helvetica', '', 10);
    }

    protected function ascii(string $s): string
    {
        $s = str_replace(['—', '–', '·', '’', '‘', '“', '”'], ['-', '-', '-', "'", "'", '"', '"'], $s);
        return @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s) ?: $s;
    }
}
