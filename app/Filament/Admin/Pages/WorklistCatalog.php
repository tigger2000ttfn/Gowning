<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\LimsWorklist;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * LIMS Worklist Catalog. Loads the weekly LabWare EM export so worklists can be tied to runs and
 * drive the workflow (incubation-complete, QCM-result-ready). Mirrors the Veeva/NC catalogs: wizard
 * upload -> review -> import, path-based (rows are not held in Livewire state), idempotent on worklist.
 */
class WorklistCatalog extends Page implements HasForms
{
    use InteractsWithForms;

    public function getHeading(): string { return ''; }
    public static function canAccessNavigation(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ManageScheduling) || $u->hasCapability(Capability::QaApprove) || $u->hasCapability(Capability::QaReview)));
    }
    public static function shouldRegisterNavigation(): bool { return false; }
    public static function canViewAny(): bool { return static::canAccessNavigation(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $slug = 'worklist-catalog';
    protected string $view = 'filament.pages.worklist-catalog';

    public ?array $data = [];
    public array $headers = [];
    public array $preview = [];
    public int $rowCount = 0;
    public ?string $parsedPath = null;
    public bool $parsed = false;
    public bool $imported = false;
    public int $lastCreated = 0;
    public int $lastUpdated = 0;
    public int $lastSkipped = 0;
    public string $search = '';
    public string $tab = 'upload';
    public string $sqlQuery = '';
    // Catalog filters
    public string $filterType = '';     // '', initial, annual, additional, routine
    public string $filterStatus = '';   // '', A, P, I, C, X
    public string $filterReady = '';    // '', ready, not
    public string $filterLegacy = '';   // '', legacy, active
    public function setTab(string $t): void { $this->tab = in_array($t, ['upload', 'catalog', 'sql'], true) ? $t : 'upload'; }

    public function mount(): void
    {
        $this->form->fill();
        $this->sqlQuery = (string) \App\Models\Setting::get('lims_sql_query', $this->defaultSqlQuery());
    }

    public function saveSql(): void
    {
        if (! static::canAccessNavigation()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        \App\Models\Setting::put('lims_sql_query', $this->sqlQuery);
        Notification::make()->success()->title('Query Saved')->body('The LIMS SQL query has been stored.')->send();
    }

    protected function defaultSqlQuery(): string
    {
        return <<<'SQL'
-- LIMS (LabWare) gowning worklist export.
-- Pulls the QC_EM_PERSONNEL_QUAL meta-sample per worklist and joins the QC_INC_META incubation sample.
SELECT t.BATCH AS Worklist,
       b.DESCRIPTION AS Worklist_Description,
       s.SAMPLE_NUMBER,
       s.STATUS AS Sample_Status,
       COUNT(*) OVER (PARTITION BY t.BATCH) AS Samples_On_Worklist,
       SUM(CASE WHEN s.STATUS NOT IN ('A','C') THEN 1 ELSE 0 END)
           OVER (PARTITION BY t.BATCH) AS Non_Final_Count,
       CASE WHEN SUM(CASE WHEN s.STATUS NOT IN ('A','C') THEN 1 ELSE 0 END)
                 OVER (PARTITION BY t.BATCH) = 0
            THEN 'Yes' ELSE 'No' END AS Worklist_All_Final,
       MAX(CASE WHEN r.NAME = 'Qualification Type' THEN r.FORMATTED_ENTRY END) AS [Qualification Type],
       MAX(CASE WHEN r.NAME = 'Personnel' THEN r.FORMATTED_ENTRY END) AS [Personnel],
       MAX(CASE WHEN r.NAME = 'Initial Gowning Qualification Run #' THEN r.FORMATTED_ENTRY END) AS [Initial Gowning Qualification Run #],
       MAX(CASE WHEN r.NAME = 'Annual ReQualification' THEN r.FORMATTED_ENTRY END) AS [Annual ReQualification],
       MAX(CASE WHEN r.NAME = 'Additional Requalification' THEN r.FORMATTED_ENTRY END) AS [Additional Requalification],
       MAX(CASE WHEN r.NAME = 'Gowning Qual/Requal Evaluation' THEN r.FORMATTED_ENTRY END) AS [Gowning Qual/Requal Evaluation],
       MAX(CASE WHEN r.NAME = 'EM Area' THEN r.FORMATTED_ENTRY END) AS [EM Area],
       MAX(CASE WHEN r.NAME = 'CR Grade 1' THEN r.FORMATTED_ENTRY END) AS [CR Grade 1],
       MAX(CASE WHEN r.NAME = 'CR Grade 2' THEN r.FORMATTED_ENTRY END) AS [CR Grade 2],
       MAX(CASE WHEN r.NAME = 'CR Grade 3' THEN r.FORMATTED_ENTRY END) AS [CR Grade 3],
       MAX(CASE WHEN r.NAME = 'Grade A Critical Ops MFG Selected' THEN r.FORMATTED_ENTRY END) AS [Grade A Critical Ops MFG Selected],
       MAX(CASE WHEN r.NAME = 'Grade B Critical Ops MFG Selected' THEN r.FORMATTED_ENTRY END) AS [Grade B Critical Ops MFG Selected],
       MAX(CASE WHEN r.NAME = 'Qual Date 1' THEN CONVERT(char(10), TRY_CONVERT(date, r.FORMATTED_ENTRY, 105), 23) END) AS [Qual Date 1],
       MAX(CASE WHEN r.NAME = 'Qual Date 2' THEN CONVERT(char(10), TRY_CONVERT(date, r.FORMATTED_ENTRY, 105), 23) END) AS [Qual Date 2],
       MAX(CASE WHEN r.NAME = 'Qual Date 3' THEN CONVERT(char(10), TRY_CONVERT(date, r.FORMATTED_ENTRY, 105), 23) END) AS [Qual Date 3],
       MAX(CASE WHEN r.NAME = 'Run 2 Rescheduled ?' THEN r.FORMATTED_ENTRY END) AS [Run 2 Rescheduled ?],
       MAX(CASE WHEN r.NAME = 'Run 3 Rescheduled ?' THEN r.FORMATTED_ENTRY END) AS [Run 3 Rescheduled ?],
       MAX(CASE WHEN r.NAME = 'TSA Contact Plate' THEN r.FORMATTED_ENTRY END) AS [TSA Contact Plate],
       MAX(CASE WHEN r.NAME = 'TSA Contact Plate 1' THEN r.FORMATTED_ENTRY END) AS [TSA Contact Plate 1],
       MAX(CASE WHEN r.NAME = 'TSA Contact Plate 2' THEN r.FORMATTED_ENTRY END) AS [TSA Contact Plate 2],
       MAX(CASE WHEN r.NAME = 'TSA Contact Plate 3' THEN r.FORMATTED_ENTRY END) AS [TSA Contact Plate 3],
       MAX(CASE WHEN r.NAME = 'TSA Control Sample ID 1' THEN r.FORMATTED_ENTRY END) AS [TSA Control Sample ID 1],
       MAX(CASE WHEN r.NAME = 'TSA Control Sample ID 2' THEN r.FORMATTED_ENTRY END) AS [TSA Control Sample ID 2],
       MAX(CASE WHEN r.NAME = 'TSA Control Sample ID 3' THEN r.FORMATTED_ENTRY END) AS [TSA Control Sample ID 3],
       MAX(CASE WHEN r.NAME = 'Reference' THEN r.FORMATTED_ENTRY END) AS [Qual Reference],
       inc.Inc_Sample_Number,
       inc.Inc_Sample_Status,
       inc.[30-35C Incubator for Incubation 1],
       inc.[Store Samples in Incubation Bin],
       inc.[1st Incubation Start Date/Time],
       inc.[1st Incubation End Date/Time],
       inc.[1st Incubation Due to End],
       inc.[1st Total Incubation Time],
       inc.[20-25C Incubator for Incubation 2],
       inc.[2nd Incubation Start Date/Time],
       inc.[2nd Incubation End Date/Time],
       inc.[2nd Incubation Due to End],
       inc.[2nd Total Incubation Time],
       inc.[3rd Storage Location],
       inc.[3rd Storage Movement Start Date/Time],
       inc.[Inc Reference]
FROM TEST t
INNER JOIN SAMPLE s ON t.SAMPLE_NUMBER = s.SAMPLE_NUMBER
LEFT JOIN RESULT r ON r.TEST_NUMBER = t.TEST_NUMBER
LEFT JOIN BATCH b ON b.NAME = t.BATCH
LEFT JOIN (
    SELECT t2.BATCH,
           MAX(s2.SAMPLE_NUMBER) AS Inc_Sample_Number,
           MAX(s2.STATUS)        AS Inc_Sample_Status,
           MAX(CASE WHEN r2.NAME IN ('30-35C Incubator for Incubation 1','Incubator for Incubation 1')
                    THEN r2.FORMATTED_ENTRY END) AS [30-35C Incubator for Incubation 1],
           MAX(CASE WHEN r2.NAME = 'Store Samples in Incubation Bin' THEN r2.FORMATTED_ENTRY END) AS [Store Samples in Incubation Bin],
           MAX(CASE WHEN r2.NAME = '1st Incubation Start Date/Time' THEN CONVERT(varchar(16), TRY_CONVERT(datetime, r2.FORMATTED_ENTRY, 105), 120) END) AS [1st Incubation Start Date/Time],
           MAX(CASE WHEN r2.NAME = '1st Incubation End Date/Time'   THEN CONVERT(varchar(16), TRY_CONVERT(datetime, r2.FORMATTED_ENTRY, 105), 120) END) AS [1st Incubation End Date/Time],
           MAX(CASE WHEN r2.NAME = '1st Incubation Due to End'      THEN CONVERT(varchar(16), TRY_CONVERT(datetime, r2.FORMATTED_ENTRY, 105), 120) END) AS [1st Incubation Due to End],
           MAX(CASE WHEN r2.NAME = '1st  Total Incubation Time'     THEN r2.FORMATTED_ENTRY END) AS [1st Total Incubation Time],
           MAX(CASE WHEN r2.NAME IN ('20-25C Incubator for Incubation 2','Incubator for Incubation 2')
                    THEN r2.FORMATTED_ENTRY END) AS [20-25C Incubator for Incubation 2],
           MAX(CASE WHEN r2.NAME = '2nd Incubation Start Date/Time' THEN CONVERT(varchar(16), TRY_CONVERT(datetime, r2.FORMATTED_ENTRY, 105), 120) END) AS [2nd Incubation Start Date/Time],
           MAX(CASE WHEN r2.NAME = '2nd Incubation End Date/Time'   THEN CONVERT(varchar(16), TRY_CONVERT(datetime, r2.FORMATTED_ENTRY, 105), 120) END) AS [2nd Incubation End Date/Time],
           MAX(CASE WHEN r2.NAME = '2nd Incubation Due to End'      THEN CONVERT(varchar(16), TRY_CONVERT(datetime, r2.FORMATTED_ENTRY, 105), 120) END) AS [2nd Incubation Due to End],
           MAX(CASE WHEN r2.NAME = '2nd Total Incubation Time'      THEN r2.FORMATTED_ENTRY END) AS [2nd Total Incubation Time],
           MAX(CASE WHEN r2.NAME = '3rd Storage Location'           THEN r2.FORMATTED_ENTRY END) AS [3rd Storage Location],
           MAX(CASE WHEN r2.NAME = '3rd Storage Movement Start Date/Time' THEN CONVERT(varchar(16), TRY_CONVERT(datetime, r2.FORMATTED_ENTRY, 105), 120) END) AS [3rd Storage Movement Start Date/Time],
           MAX(CASE WHEN r2.NAME = 'Reference' THEN r2.FORMATTED_ENTRY END) AS [Inc Reference]
    FROM TEST t2
    INNER JOIN SAMPLE s2 ON t2.SAMPLE_NUMBER = s2.SAMPLE_NUMBER
    LEFT JOIN RESULT r2  ON r2.TEST_NUMBER = t2.TEST_NUMBER
    WHERE t2.ANALYSIS = 'QC_INC_META'
    GROUP BY t2.BATCH
) inc ON inc.BATCH = t.BATCH
WHERE t.ANALYSIS = 'QC_EM_PERSONNEL_QUAL'
GROUP BY t.BATCH, b.DESCRIPTION, s.SAMPLE_NUMBER, s.STATUS,
         inc.Inc_Sample_Number, inc.Inc_Sample_Status,
         inc.[30-35C Incubator for Incubation 1],
         inc.[Store Samples in Incubation Bin], inc.[1st Incubation Start Date/Time],
         inc.[1st Incubation End Date/Time], inc.[1st Incubation Due to End],
         inc.[1st Total Incubation Time],
         inc.[20-25C Incubator for Incubation 2],
         inc.[2nd Incubation Start Date/Time], inc.[2nd Incubation End Date/Time],
         inc.[2nd Incubation Due to End], inc.[2nd Total Incubation Time],
         inc.[3rd Storage Location], inc.[3rd Storage Movement Start Date/Time],
         inc.[Inc Reference]
ORDER BY t.BATCH, s.SAMPLE_NUMBER
SQL;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            FileUpload::make('csv')
                ->label('LIMS Worklist Export File')
                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                ->disk('local')
                ->storeFiles(true)
                ->directory('imports')
                ->helperText('Excel (.xlsx) or CSV with a header row.'),
        ]);
    }

    protected function resolveUploadFullPath(): ?string
    {
        $candidates = [];
        $candidates[] = $this->data['csv'] ?? null;
        try { $candidates[] = ($this->form->getState())['csv'] ?? null; } catch (\Throwable $e) {}
        foreach ($candidates as $v) {
            if ($v instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                $rp = $v->getRealPath();
                if ($rp && is_file($rp)) return $rp;
            }
            if (is_array($v)) {
                $v = collect($v)->flatten()->first(fn ($i) => $i instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile || (is_string($i) && $i !== ''));
                if ($v instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                    $rp = $v->getRealPath();
                    if ($rp && is_file($rp)) return $rp;
                }
            }
            if (is_string($v) && $v !== '') {
                foreach (['local', 'public'] as $disk) {
                    try { if (Storage::disk($disk)->exists($v)) return Storage::disk($disk)->path($v); } catch (\Throwable $e) {}
                }
                if (is_file($v)) return $v;
            }
        }
        return null;
    }

    protected function uploadedPath(): ?string
    {
        $v = $this->data['csv'] ?? null;
        if (is_array($v)) $v = collect($v)->flatten()->filter()->first();
        return is_string($v) && $v !== '' ? $v : null;
    }

    public function resetUpload(): void
    {
        if ($this->parsedPath) {
            try { Storage::disk('local')->delete($this->parsedPath); } catch (\Throwable $e) {}
        }
        $this->parsed = false;
        $this->imported = false;
        $this->preview = [];
        $this->headers = [];
        $this->rowCount = 0;
        $this->parsedPath = null;
        $this->data = [];
        try { $this->form->fill(); } catch (\Throwable $e) {}
    }

    /** @return array{headers: array, rows: array, hyperlinks: array}|null */
    protected function readRows(string $full): ?array
    {
        $fmt = \App\Support\XlsxReader::detectFormat($full);
        if ($fmt === 'xlsx' || $fmt === 'html') {
            $read = $fmt === 'xlsx' ? \App\Support\XlsxReader::read($full) : \App\Support\XlsxReader::readHtml($full);
            $rows = $read['rows'];
            if (count($rows) < 2) return null;
            $headers = array_map(fn ($h) => \App\Support\XlsxReader::toUtf8(trim((string) $h)), $rows[0]);
            return ['headers' => $headers, 'rows' => array_values(array_slice($rows, 1)), 'hyperlinks' => []];
        }
        $rows = [];
        $fh = fopen($full, 'r');
        if (! $fh) return null;
        while (($r = fgetcsv($fh)) !== false) {
            if (count(array_filter($r, fn ($c) => trim((string) $c) !== '')) === 0) continue;
            $rows[] = array_map(fn ($c) => \App\Support\XlsxReader::toUtf8((string) $c), $r);
        }
        fclose($fh);
        if (count($rows) < 2) return null;
        $headers = array_map('trim', array_shift($rows));
        return ['headers' => $headers, 'rows' => array_values($rows), 'hyperlinks' => []];
    }

    public function parse(): void
    {
        $full = $this->resolveUploadFullPath();
        if (! $full || ! is_file($full)) {
            Notification::make()->danger()->title('Upload A File First')
                ->body('The file upload did not complete. Pick the file again, wait for the progress bar to finish, then Parse.')->send();
            return;
        }
        try { $read = $this->readRows($full); }
        catch (\Throwable $e) {
            Notification::make()->danger()->title('Could Not Parse The File')->body(\Illuminate\Support\Str::limit($e->getMessage(), 180))->send();
            return;
        }
        if (! $read) { Notification::make()->danger()->title('Need A Header Row And At Least One Row')->send(); return; }

        $this->headers = $read['headers'];
        $this->rowCount = count($read['rows']);
        $this->preview = array_map(
            fn ($row) => array_map(fn ($c) => \Illuminate\Support\Str::limit((string) $c, 60), $row),
            array_slice($read['rows'], 0, 8)
        );
        $this->parsedPath = $this->uploadedPath();
        $this->guessColumns();
        $this->parsed = true;
        $this->imported = false;
    }

    /** Map this export's known headers to field keys (silent auto-detect). */
    protected function guessColumns(): void
    {
        $find = function (array $names) {
            foreach ($this->headers as $i => $h) {
                $hl = strtolower(trim($h));
                foreach ($names as $n) if ($hl === strtolower($n)) return (int) $i;
            }
            // fallback: contains
            foreach ($this->headers as $i => $h) {
                $hl = strtolower(trim($h));
                foreach ($names as $n) if (str_contains($hl, strtolower($n))) return (int) $i;
            }
            return null;
        };
        $this->data['cols'] = [
            'worklist' => $find(['worklist']),
            'worklist_description' => $find(['worklist_description', 'worklist description']),
            'sample_number' => $find(['sample_number', 'sample number']),
            'sample_status' => $find(['sample_status', 'sample status']),
            'samples_on_worklist' => $find(['samples_on_worklist']),
            'non_final_count' => $find(['non_final_count']),
            'worklist_all_final' => $find(['worklist_all_final']),
            'qualification_type' => $find(['qualification type']),
            'personnel' => $find(['personnel']),
            'initial_run_no' => $find(['initial gowning qualification run #', 'initial gowning qualification run']),
            'annual_requal' => $find(['annual requalification']),
            'additional_requal' => $find(['additional requalification']),
            'evaluation' => $find(['gowning qual/requal evaluation', 'evaluation']),
            'em_area' => $find(['em area']),
            'cr_grade_1' => $find(['cr grade 1']),
            'cr_grade_2' => $find(['cr grade 2']),
            'cr_grade_3' => $find(['cr grade 3']),
            'grade_a_ops' => $find(['grade a critical ops mfg selected']),
            'grade_b_ops' => $find(['grade b critical ops mfg selected']),
            'qual_date_1' => $find(['qual date 1']),
            'qual_date_2' => $find(['qual date 2']),
            'qual_date_3' => $find(['qual date 3']),
            'run2_rescheduled' => $find(['run 2 rescheduled ?', 'run 2 rescheduled']),
            'run3_rescheduled' => $find(['run 3 rescheduled ?', 'run 3 rescheduled']),
            'tsa_contact_plate' => $find(['tsa contact plate']),
            'tsa_contact_plate_1' => $find(['tsa contact plate 1']),
            'tsa_contact_plate_2' => $find(['tsa contact plate 2']),
            'tsa_contact_plate_3' => $find(['tsa contact plate 3']),
            'tsa_control_1' => $find(['tsa control sample id 1']),
            'tsa_control_2' => $find(['tsa control sample id 2']),
            'tsa_control_3' => $find(['tsa control sample id 3']),
            'qual_reference' => $find(['qual reference']),
            'inc_sample_number' => $find(['inc_sample_number']),
            'inc_sample_status' => $find(['inc_sample_status']),
            'inc1_incubator' => $find(['30-35c incubator for incubation 1', 'incubator for incubation 1', 'incubator 1']),
            'inc1_bin' => $find(['store samples in incubation bin']),
            'inc1_start' => $find(['1st incubation start date/time']),
            'inc1_end' => $find(['1st incubation end date/time']),
            'inc1_due' => $find(['1st incubation due to end']),
            'inc1_total' => $find(['1st total incubation time']),
            'inc2_incubator' => $find(['20-25c incubator for incubation 2', 'incubator for incubation 2', 'incubator 2']),
            'inc2_start' => $find(['2nd incubation start date/time']),
            'inc2_end' => $find(['2nd incubation end date/time']),
            'inc2_due' => $find(['2nd incubation due to end']),
            'inc2_total' => $find(['2nd total incubation time']),
            'storage3_location' => $find(['3rd storage location']),
            'storage3_start' => $find(['3rd storage movement start date/time']),
            'inc_reference' => $find(['inc reference']),
        ];
    }

    protected function boolFromYesNo(?string $v): ?bool
    {
        $v = strtolower(trim((string) $v));
        if ($v === '') return null;
        return in_array($v, ['yes', 'y', 'true', '1'], true);
    }

    public function import(): void
    {
        if (! static::canAccessNavigation()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        if (! \Illuminate\Support\Facades\Schema::hasTable('lims_worklists')) {
            Notification::make()->danger()->title('Worklist Table Missing')
                ->body('The lims_worklists table does not exist yet. Run: php artisan migrate --force, then try again.')->send();
            return;
        }
        $cols = $this->data['cols'] ?? [];
        if (! isset($cols['worklist']) || $cols['worklist'] === null) {
            Notification::make()->danger()->title('Worklist Column Not Found')->body('Could not locate the WORKLIST column in the file.')->send();
            return;
        }

        $full = $this->parsedPath ? Storage::disk('local')->path($this->parsedPath) : $this->resolveUploadFullPath();
        if (! $full || ! is_file($full)) {
            Notification::make()->danger()->title('Upload Expired')->body('Please re-upload and parse the file, then import.')->send();
            return;
        }
        try { $read = $this->readRows($full); }
        catch (\Throwable $e) { Notification::make()->danger()->title('Import Failed')->body(\Illuminate\Support\Str::limit($e->getMessage(), 180))->send(); return; }
        if (! $read) { Notification::make()->danger()->title('Could Not Read The File')->send(); return; }

        $get = fn ($row, $key) => (isset($cols[$key]) && $cols[$key] !== null && isset($row[$cols[$key]]))
            ? trim((string) $row[$cols[$key]]) : null;

        $created = 0; $updated = 0; $skipped = 0;
        try {
            foreach ($read['rows'] as $row) {
                $wl = $get($row, 'worklist');
                if (! $wl) { $skipped++; continue; }

                $payload = [
                    'worklist' => $wl,
                    'worklist_description' => $get($row, 'worklist_description'),
                    'sample_number' => $get($row, 'sample_number'),
                    'sample_status' => strtoupper((string) $get($row, 'sample_status')) ?: null,
                    'samples_on_worklist' => is_numeric($get($row, 'samples_on_worklist')) ? (int) $get($row, 'samples_on_worklist') : null,
                    'non_final_count' => is_numeric($get($row, 'non_final_count')) ? (int) $get($row, 'non_final_count') : null,
                    'worklist_all_final' => $this->boolFromYesNo($get($row, 'worklist_all_final')),
                    'qualification_type' => $get($row, 'qualification_type'),
                    'personnel' => $get($row, 'personnel'),
                    'initial_run_no' => $get($row, 'initial_run_no'),
                    'annual_requal' => $get($row, 'annual_requal'),
                    'additional_requal' => $get($row, 'additional_requal'),
                    'evaluation' => $get($row, 'evaluation'),
                    'em_area' => $get($row, 'em_area'),
                    'cr_grade_1' => $get($row, 'cr_grade_1'),
                    'cr_grade_2' => $get($row, 'cr_grade_2'),
                    'cr_grade_3' => $get($row, 'cr_grade_3'),
                    'grade_a_ops' => $get($row, 'grade_a_ops'),
                    'grade_b_ops' => $get($row, 'grade_b_ops'),
                    'qual_date_1' => $get($row, 'qual_date_1'),
                    'qual_date_2' => $get($row, 'qual_date_2'),
                    'qual_date_3' => $get($row, 'qual_date_3'),
                    'run2_rescheduled' => $get($row, 'run2_rescheduled'),
                    'run3_rescheduled' => $get($row, 'run3_rescheduled'),
                    'tsa_contact_plate' => $get($row, 'tsa_contact_plate'),
                    'tsa_contact_plate_1' => $get($row, 'tsa_contact_plate_1'),
                    'tsa_contact_plate_2' => $get($row, 'tsa_contact_plate_2'),
                    'tsa_contact_plate_3' => $get($row, 'tsa_contact_plate_3'),
                    'tsa_control_1' => $get($row, 'tsa_control_1'),
                    'tsa_control_2' => $get($row, 'tsa_control_2'),
                    'tsa_control_3' => $get($row, 'tsa_control_3'),
                    'qual_reference' => $get($row, 'qual_reference'),
                    'inc_reference' => $get($row, 'inc_reference'),
                    'inc_sample_number' => $get($row, 'inc_sample_number'),
                    'inc_sample_status' => strtoupper((string) $get($row, 'inc_sample_status')) ?: null,
                    'inc1_incubator' => $get($row, 'inc1_incubator'),
                    'inc1_bin' => $get($row, 'inc1_bin'),
                    'inc1_start' => $get($row, 'inc1_start'),
                    'inc1_end' => $get($row, 'inc1_end'),
                    'inc1_due' => $get($row, 'inc1_due'),
                    'inc1_total' => $get($row, 'inc1_total'),
                    'inc2_incubator' => $get($row, 'inc2_incubator'),
                    'inc2_start' => $get($row, 'inc2_start'),
                    'inc2_end' => $get($row, 'inc2_end'),
                    'inc2_due' => $get($row, 'inc2_due'),
                    'inc2_total' => $get($row, 'inc2_total'),
                    'storage3_location' => $get($row, 'storage3_location'),
                    'storage3_start' => $get($row, 'storage3_start'),
                    'catalog_synced_at' => now(),
                ];

                $existing = LimsWorklist::where('worklist', $wl)->first();
                if ($existing && $existing->is_legacy) { $skipped++; continue; } // legacy-locked: never overwrite
                if ($existing) { $existing->update($payload); $updated++; }
                else { LimsWorklist::create($payload); $created++; }
            }
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Import Failed')->body(\Illuminate\Support\Str::limit($e->getMessage(), 180))->send();
            return;
        }

        $this->lastCreated = $created;
        $this->lastUpdated = $updated;
        $this->lastSkipped = $skipped;
        $this->imported = true;
        $this->parsed = false;
        $this->preview = [];
        $this->rowCount = 0;

        foreach (array_filter([$this->parsedPath, $this->uploadedPath()]) as $p) {
            try { Storage::disk('local')->delete($p); } catch (\Throwable $e) {}
        }
        $this->parsedPath = null;
        $this->data['csv'] = null;
        $this->headers = [];

        // Sync any runs already linked to these worklists.
        $synced = app(\App\Services\WorklistSync::class)->syncAll();
        Notification::make()->success()->title('Worklist Catalog Updated')
            ->body("Added {$created}, updated {$updated}. Synced {$synced} linked run(s).")->send();
    }

    public function runSync(): void
    {
        if (! static::canAccessNavigation()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $n = app(\App\Services\WorklistSync::class)->syncAll();
        Notification::make()->success()->title('Sync Complete')->body("Synced {$n} linked run(s) from the catalog.")->send();
    }

    // ---- Historic backfill: build qualification history from matched catalog worklists ----
    public bool $showBackfill = false;
    public array $backfillPreview = [];
    public ?int $backfillPersonId = null;   // null = bulk; set = per-person

    public function bulkBackfillDone(): bool
    {
        return (bool) \App\Models\Setting::get('worklist_backfill_done', false);
    }

    /** Personnel options for the per-person backfill picker. */
    public function backfillPersonOptions(): array
    {
        return \App\Models\Personnel::orderBy('last_name')->orderBy('first_name')->get()
            ->mapWithKeys(fn ($p) => [$p->id => trim($p->last_name . ', ' . $p->first_name) . ($p->lims_username ? ' (' . $p->lims_username . ')' : '')])
            ->all();
    }

    public function previewBackfill(): void
    {
        if (! static::canAccessNavigation()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        if ($this->backfillPersonId === null && $this->bulkBackfillDone()) {
            Notification::make()->warning()->title('Bulk Backfill Already Done')
                ->body('The one-time bulk backfill has already run. Use per-person backfill for individual additions.')->send();
            return;
        }
        $this->backfillPreview = app(\App\Services\WorklistBackfill::class)->run(true, $this->backfillPersonId);
        $this->showBackfill = true;
    }

    public function runBackfill(): void
    {
        if (! static::canAccessNavigation()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $result = app(\App\Services\WorklistBackfill::class)->run(false, $this->backfillPersonId);
        $this->showBackfill = false;
        $this->backfillPreview = [];
        if (! empty($result['blocked'])) {
            Notification::make()->warning()->title('Bulk Backfill Already Done')
                ->body('The one-time bulk backfill has already run. Use per-person backfill for individual additions.')->send();
            return;
        }
        Notification::make()->success()->title('Backfill Complete')
            ->body("Created {$result['created']} run(s) across {$result['quals']} qualification(s). Matched {$result['matched']}, unmatched {$result['unmatched']}, skipped {$result['skipped']}.")->send();
    }

    public function closeBackfill(): void { $this->showBackfill = false; }

    // ---- Legacy lock + hand-edit a worklist row ----
    public ?int $editId = null;
    public array $editData = [];

    /** Fields hand-editable on a legacy row. */
    protected array $editableFields = [
        'worklist_description', 'sample_status', 'worklist_all_final', 'qualification_type', 'personnel',
        'evaluation', 'em_area', 'cr_grade_1', 'cr_grade_2', 'cr_grade_3',
        'qual_date_1', 'qual_date_2', 'qual_date_3', 'run2_rescheduled', 'run3_rescheduled',
        'inc_sample_status', 'qual_reference', 'inc_reference',
    ];

    public function toggleLegacy(int $id): void
    {
        if (! static::canAccessNavigation()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $wl = LimsWorklist::find($id);
        if (! $wl) return;
        $wl->is_legacy = ! $wl->is_legacy;
        $wl->save();
        Notification::make()->success()->title($wl->is_legacy ? 'Marked Legacy' : 'Legacy Removed')
            ->body($wl->is_legacy ? 'Imports will no longer update ' . $wl->worklist . '.' : $wl->worklist . ' will update on import again.')->send();
    }

    public function editRow(int $id): void
    {
        if (! static::canAccessNavigation()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $wl = LimsWorklist::find($id);
        if (! $wl) return;
        $this->editId = $id;
        $this->editData = collect($this->editableFields)->mapWithKeys(fn ($f) => [$f => (string) ($wl->{$f} ?? '')])->all();
        // booleans as yes/no strings for the form
        $this->editData['worklist_all_final'] = $wl->worklist_all_final ? 'Yes' : 'No';
        // If type is blank but we can infer it from the description, pre-fill so a save captures it.
        if (trim((string) $wl->qualification_type) === '' && ! $wl->isRoutineEm()) {
            $inferred = $wl->effectiveType();
            if ($inferred) $this->editData['qualification_type'] = $inferred;
        }
    }

    public function saveRow(): void
    {
        if (! static::canAccessNavigation()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $wl = LimsWorklist::find($this->editId);
        if (! $wl) { $this->editId = null; return; }
        $payload = [];
        foreach ($this->editableFields as $f) {
            if ($f === 'worklist_all_final') { $payload[$f] = $this->boolFromYesNo($this->editData[$f] ?? null); continue; }
            $payload[$f] = ($this->editData[$f] ?? '') !== '' ? $this->editData[$f] : null;
        }
        // editing implies this row is hand-managed; lock it from future imports.
        $payload['is_legacy'] = true;
        $wl->update($payload);
        $savedId = $wl->id;
        $this->editId = null;
        $this->editData = [];
        // re-sync any run linked to this worklist so edits flow through
        app(\App\Services\WorklistSync::class)->syncAll();
        // reopen the refreshed detail so the changes are visible right away
        $this->viewId = $savedId;
        Notification::make()->success()->title('Worklist Updated')->body($wl->worklist . ' saved and marked legacy.')->send();
    }

    public function closeEdit(): void { $this->editId = null; $this->editData = []; }

    // ---- Full-row detail view (click a row) ----
    public ?int $viewId = null;

    public function viewRow(int $id): void
    {
        $this->viewId = $id;
    }
    public function closeView(): void { $this->viewId = null; }

    /** Resolve the matched personnel (id + name) for the currently-viewed worklist, if any. */
    public function viewMatchedPerson(): ?array
    {
        if (! $this->viewId) return null;
        $d = LimsWorklist::find($this->viewId);
        if (! $d) return null;
        $login = strtoupper(trim((string) $d->personnel));
        $person = null;
        if ($login !== '') {
            $byLogin = \App\Models\Personnel::whereRaw('UPPER(lims_username) = ?', [$login])->get();
            if ($byLogin->count() === 1) $person = $byLogin->first();
        }
        if (! $person && $login !== '' && strlen($login) >= 2) {
            $cands = \App\Models\Personnel::whereRaw('UPPER(last_name) = ?', [substr($login, 1)])
                ->whereRaw('UPPER(LEFT(first_name,1)) = ?', [substr($login, 0, 1)])->get();
            if ($cands->count() === 1) $person = $cands->first();
        }
        return $person ? ['id' => $person->id, 'name' => trim($person->first_name . ' ' . $person->last_name)] : null;
    }

    /** Open the backfill preview scoped to this worklist's matched person. */
    public function backfillThisPerson(): void
    {
        $match = $this->viewMatchedPerson();
        if (! $match) {
            Notification::make()->warning()->title('No Confident Person Match')
                ->body('This worklist does not map to a single person by login or name. Use the person picker, or fix the row.')->send();
            return;
        }
        $this->backfillPersonId = $match['id'];
        $this->viewId = null;
        $this->previewBackfill();
    }

    /** All fields of one worklist, grouped, for the detail panel. */
    public function viewRecord(): ?array
    {
        if (! $this->viewId) return null;
        $d = LimsWorklist::find($this->viewId);
        if (! $d) return null;
        $dash = fn ($v) => ($v === null || $v === '') ? '—' : $v;
        return [
            'id' => $d->id,
            'worklist' => $d->worklist,
            'legacy' => (bool) $d->is_legacy,
            'qcm_ready' => $d->isQcmReady(),
            'groups' => [
                'Worklist' => [
                    'Worklist' => $dash($d->worklist),
                    'Description' => $dash($d->worklist_description),
                    'Samples On Worklist' => $dash($d->samples_on_worklist),
                    'Non-Final Count' => $dash($d->non_final_count),
                    'All Final' => $d->worklist_all_final === null ? '—' : ($d->worklist_all_final ? 'Yes' : 'No'),
                ],
                'Personnel Qual Sample (QC_EM_PERSONNEL_QUAL)' => [
                    'Sample Number' => $dash($d->sample_number),
                    'Sample Status' => LimsWorklist::statusLabel($d->sample_status),
                    'Qualification Type' => $dash($d->qualification_type),
                    'Personnel (LIMS Login)' => $dash($d->personnel),
                    'Initial Run #' => $dash($d->initial_run_no),
                    'Annual Requalification' => $dash($d->annual_requal),
                    'Additional Requalification' => $dash($d->additional_requal),
                    'Evaluation' => $dash($d->evaluation),
                    'EM Area' => $dash($d->em_area),
                    'CR Grade 1' => $dash($d->cr_grade_1),
                    'CR Grade 2' => $dash($d->cr_grade_2),
                    'CR Grade 3' => $dash($d->cr_grade_3),
                    'Grade A Critical Ops' => $dash($d->grade_a_ops),
                    'Grade B Critical Ops' => $dash($d->grade_b_ops),
                    'Qual Date 1' => $dash($d->qual_date_1),
                    'Qual Date 2' => $dash($d->qual_date_2),
                    'Qual Date 3' => $dash($d->qual_date_3),
                    'Run 2 Rescheduled?' => $dash($d->run2_rescheduled),
                    'Run 3 Rescheduled?' => $dash($d->run3_rescheduled),
                    'Qual Reference (NC)' => $dash($d->qual_reference),
                ],
                'Plates & Controls' => [
                    'TSA Contact Plate' => $dash($d->tsa_contact_plate),
                    'TSA Contact Plate 1' => $dash($d->tsa_contact_plate_1),
                    'TSA Contact Plate 2' => $dash($d->tsa_contact_plate_2),
                    'TSA Contact Plate 3' => $dash($d->tsa_contact_plate_3),
                    'TSA Control Sample ID 1' => $dash($d->tsa_control_1),
                    'TSA Control Sample ID 2' => $dash($d->tsa_control_2),
                    'TSA Control Sample ID 3' => $dash($d->tsa_control_3),
                ],
                'Incubation Sample (QC_INC_META)' => [
                    'Inc Sample Number' => $dash($d->inc_sample_number),
                    'Inc Sample Status' => LimsWorklist::statusLabel($d->inc_sample_status),
                    '30-35C Incubator (Inc 1)' => $dash($d->inc1_incubator),
                    'Incubation Bin' => $dash($d->inc1_bin),
                    '1st Incubation Start' => $dash($d->inc1_start),
                    '1st Incubation End' => $dash($d->inc1_end),
                    '1st Incubation Due' => $dash($d->inc1_due),
                    '1st Total Time' => $dash($d->inc1_total),
                    '20-25C Incubator (Inc 2)' => $dash($d->inc2_incubator),
                    '2nd Incubation Start' => $dash($d->inc2_start),
                    '2nd Incubation End' => $dash($d->inc2_end),
                    '2nd Incubation Due' => $dash($d->inc2_due),
                    '2nd Total Time' => $dash($d->inc2_total),
                    '3rd Storage Location' => $dash($d->storage3_location),
                    '3rd Storage Movement Start' => $dash($d->storage3_start),
                    'Inc Reference' => $dash($d->inc_reference),
                ],
                'Catalog' => [
                    'Legacy Locked' => $d->is_legacy ? 'Yes' : 'No',
                    'Last Synced' => $d->catalog_synced_at?->gmpDt() ?: '—',
                ],
            ],
        ];
    }

    public function catalogRows(): array
    {
        $q = LimsWorklist::query()->orderByDesc('updated_at')->orderByDesc('catalog_synced_at');
        if (trim($this->search) !== '') {
            $s = '%' . trim($this->search) . '%';
            $q->where(fn ($w) => $w->where('worklist', 'ilike', $s)
                ->orWhere('personnel', 'ilike', $s)
                ->orWhere('worklist_description', 'ilike', $s)
                ->orWhere('evaluation', 'ilike', $s));
        }
        if ($this->filterStatus !== '') $q->whereRaw('UPPER(sample_status) = ?', [strtoupper($this->filterStatus)]);
        if ($this->filterLegacy === 'legacy') $q->where('is_legacy', true);
        if ($this->filterLegacy === 'active') $q->where('is_legacy', false);

        $rows = $q->limit(200)->get();

        // Computed filters (type / qcm-ready) applied after load.
        if ($this->filterType !== '') {
            $rows = $rows->filter(function ($d) {
                if ($this->filterType === 'routine') return $d->isRoutineEm();
                $et = strtolower((string) $d->effectiveType());
                return match ($this->filterType) {
                    'initial' => str_contains($et, 'initial'),
                    'annual' => str_contains($et, 'annual'),
                    'additional' => str_contains($et, 'additional'),
                    default => true,
                };
            });
        }
        if ($this->filterReady === 'ready') $rows = $rows->filter(fn ($d) => $d->isQcmReady());
        if ($this->filterReady === 'not') $rows = $rows->filter(fn ($d) => ! $d->isQcmReady());

        return $rows->take(80)->map(fn ($d) => [
            'id' => $d->id,
            'legacy' => (bool) $d->is_legacy,
            'worklist' => $d->worklist,
            'description' => $d->worklist_description,
            'personnel' => $d->personnel,
            'type' => $d->effectiveType() ?: $d->qualification_type,
            'type_inferred' => $d->typeWasInferred(),
            'routine' => $d->isRoutineEm(),
            'date_review' => $d->effectiveRunDates()['needs_review'],
            'evaluation' => $d->evaluation,
            'sample_status' => LimsWorklist::statusLabel($d->sample_status),
            'inc_status' => LimsWorklist::statusLabel($d->inc_sample_status),
            'all_final' => $d->worklist_all_final,
            'qcm_ready' => $d->isQcmReady(),
            'reference' => $d->qual_reference,
            'synced' => $d->catalog_synced_at?->gmpDt(),
        ])->all();
    }

    public function catalogCount(): int { return LimsWorklist::count(); }
}
