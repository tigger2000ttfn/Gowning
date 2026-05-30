<?php

namespace App\Filament\Admin\Pages;

use App\Models\ClassCompletion;
use App\Models\Personnel;
use App\Models\Qualification;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Import Classroom Or LMS Data.
 *
 * Imports gowning-class completion records exported from the LMS (CSV). Each row is matched
 * to a person by LIMS username, employee ID, or email, then recorded as a ClassCompletion and
 * the person's class is marked on file (advancing Class Pending -> Class Complete), making them
 * eligible to start qualification runs. (Bulk personnel loading is done via the
 * `gqs:import-personnel` command.)
 */
class ImportPersonnel extends Page implements HasForms
{
    use InteractsWithForms;

    public function getHeading(): string { return ''; }
    public static function canAccessNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ImportData));
    }
    public static function shouldRegisterNavigation(): bool { return false; }
    public static function canViewAny(): bool { return static::canAccessNavigation(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string|\UnitEnum|null $navigationGroup = 'Data Import';
    protected static ?int $navigationSort = 0;
    protected static ?string $navigationLabel = 'Import Classroom Or LMS Data';
    protected static ?string $title = 'Import Classroom Or LMS Data';

    protected string $view = 'filament.pages.import-personnel';

    public ?array $data = [];
    public array $headers = [];
    public array $rows = [];
    public array $preview = [];
    public bool $parsed = false;
    public bool $imported = false;
    public int $createdCount = 0;
    public int $skippedCount = 0;
    public array $skippedRows = [];

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('1. Upload CSV')->icon('heroicon-o-arrow-up-tray')->schema([
                FileUpload::make('csv')
                    ->label('LMS Completion CSV')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                    ->storeFiles(true)
                    ->directory('imports')
                    ->helperText('A class-completion export with a header row. Map the columns next.'),
            ]),
        ]);
    }

    public function parse(): void
    {
        $path = $this->data['csv'] ?? null;
        if (! $path) { Notification::make()->danger()->title('Upload a CSV first')->send(); return; }
        $full = Storage::disk('local')->path($path);
        if (! is_file($full)) { Notification::make()->danger()->title('File not found')->send(); return; }

        $fh = fopen($full, 'r');
        $this->headers = array_map('trim', fgetcsv($fh) ?: []);
        $this->rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            if (count(array_filter($r, fn ($c) => trim((string) $c) !== '')) === 0) continue;
            $this->rows[] = $r;
        }
        fclose($fh);

        $guess = function (array $names) {
            foreach ($this->headers as $i => $h) {
                $hl = strtolower($h);
                foreach ($names as $n) if (str_contains($hl, $n)) return (string) $i;
            }
            return null;
        };
        $this->data['map_match']      = $guess(['lims', 'username', 'user', 'employee', 'a-number', 'anumber', 'id', 'email']);
        $this->data['map_match_type'] = 'lims';
        $this->data['map_class']      = $guess(['class', 'course', 'curriculum', 'training']);
        $this->data['map_date']       = $guess(['complet', 'date', 'passed']);

        $this->preview = array_slice($this->rows, 0, 8);
        $this->parsed = true;
        $this->imported = false;
    }

    public function columnOptions(): array
    {
        $opts = ['' => '— none —'];
        foreach ($this->headers as $i => $h) $opts[(string) $i] = $h;
        return $opts;
    }

    /** Commit the mapped class-completion rows. */
    public function import(): void
    {
        $m = $this->data;
        $col = fn ($row, $key) => (isset($m[$key]) && $m[$key] !== '' && isset($row[(int) $m[$key]]))
            ? trim($row[(int) $m[$key]]) : null;

        $matchType = $m['map_match_type'] ?? 'lims';
        $created = 0; $skipped = 0; $skippedRows = [];

        foreach ($this->rows as $row) {
            $idVal = $col($row, 'map_match');
            $class = $col($row, 'map_class') ?: 'Gowning Class';
            $date  = $col($row, 'map_date');
            if (! $idVal) { $skipped++; $skippedRows[] = [$idVal, 'no match value']; continue; }

            $person = $this->findPerson($matchType, $idVal);
            if (! $person) { $skipped++; $skippedRows[] = [$idVal, 'no matching person']; continue; }

            $completionDate = $this->parseDate($date) ?? now()->toDateString();

            $exists = ClassCompletion::where('personnel_id', $person->id)
                ->where('class_name', $class)
                ->whereDate('completion_date', $completionDate)->exists();
            if (! $exists) {
                ClassCompletion::create([
                    'personnel_id' => $person->id,
                    'employee_id' => $person->employee_id,
                    'class_name' => $class,
                    'completion_date' => $completionDate,
                    'source' => 'LMS Import',
                ]);
            }

            // Mark the class on file + advance Class Pending -> Class Complete (run-eligible).
            $q = Qualification::firstOrCreate(
                ['personnel_id' => $person->id],
                ['type' => 'initial', 'status' => 'pending',
                 'runs_required' => (int) \App\Models\Setting::get('initial_runs_required', 3),
                 'runs_completed' => 0]
            );
            $q->class_on_file = true;
            if (! $q->class_on_file_date) $q->class_on_file_date = $completionDate;
            if (in_array($q->workflow_stage?->value, [null, 'class_pending'], true)) {
                $q->workflow_stage = \App\Enums\WorkflowStage::ClassComplete;
                $q->stage_changed_at = now();
            }
            $q->save();
            $created++;
        }

        $this->createdCount = $created;
        $this->skippedCount = $skipped;
        $this->skippedRows = array_slice($skippedRows, 0, 50);
        $this->imported = true;
        $this->parsed = false;

        Notification::make()->success()->title('Import Complete')
            ->body("Recorded {$created} completion(s); skipped {$skipped} (no match).")->send();
    }

    protected function findPerson(string $type, string $val): ?Personnel
    {
        $val = trim($val);
        return match ($type) {
            'employee_id' => Personnel::where('employee_id', $val)->first(),
            'email'       => Personnel::whereRaw('lower(email) = ?', [strtolower($val)])->first(),
            default       => Personnel::whereRaw('lower(lims_username) = ?', [strtolower($val)])->first()
                                ?? Personnel::where('employee_id', $val)->first(),
        };
    }

    protected function parseDate(?string $d): ?string
    {
        if (! $d) return null;
        try { return \Illuminate\Support\Carbon::parse($d)->toDateString(); }
        catch (\Throwable $e) { return null; }
    }
}
