<?php

namespace App\Filament\Admin\Pages;

use App\Models\Personnel;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ImportPersonnel extends Page implements HasForms
{
    public function getHeading(): string { return ''; }
    public static function canAccessNavigation(): bool
    {
        $r = \Illuminate\Support\Facades\Auth::user()?->role;
        return $r && $r->canAdminister();
    }
    public static function shouldRegisterNavigation(): bool { return false; }
    public static function canViewAny(): bool
    {
        $r = \Illuminate\Support\Facades\Auth::user()?->role;
        return (bool) ($r && $r->canAdminister());
    }
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string|\UnitEnum|null $navigationGroup = 'Data Import';
    protected static ?int $navigationSort = 0;
    protected static ?string $navigationLabel = 'Import Personnel';
    protected static ?string $title = 'Import Personnel';

    protected string $view = 'filament.pages.import-personnel';

    public ?array $data = [];
    public array $headers = [];
    public array $rows = [];
    public array $preview = [];
    public bool $parsed = false;
    public bool $imported = false;
    public int $createdCount = 0;
    public int $updatedCount = 0;
    public int $skippedCount = 0;

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('1. Upload CSV')->schema([
                FileUpload::make('csv')
                    ->label('Personnel CSV')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                    ->storeFiles(true)
                    ->directory('imports')
                    ->helperText('CSV with a header row. Columns can be in any order — you map them next.'),
            ]),
        ]);
    }

    /** Read headers + sample rows from the uploaded file. */
    public function parse(): void
    {
        $path = $this->data['csv'] ?? null;
        if (! $path) {
            Notification::make()->danger()->title('Upload a CSV first')->send();
            return;
        }
        $full = Storage::disk('local')->path($path);
        if (! is_file($full)) {
            Notification::make()->danger()->title('File not found')->send();
            return;
        }
        $fh = fopen($full, 'r');
        $this->headers = array_map('trim', fgetcsv($fh) ?: []);
        $this->rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            if (count(array_filter($r)) === 0) continue;
            $this->rows[] = $r;
        }
        fclose($fh);

        // auto-guess mapping
        $guess = function (array $names) {
            foreach ($this->headers as $i => $h) {
                $hl = strtolower($h);
                foreach ($names as $n) if (str_contains($hl, $n)) return (string) $i;
            }
            return null;
        };
        $this->data['map_employee_id'] = $guess(['employee', 'emp id', 'id', 'badge']);
        $this->data['map_first']       = $guess(['first']);
        $this->data['map_last']        = $guess(['last', 'surname']);
        $this->data['map_name']        = $guess(['name']);
        $this->data['map_email']       = $guess(['email', 'e-mail']);
        $this->data['map_department']  = $guess(['dept', 'department']);

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

    /** Commit the mapped rows. */
    public function import(): void
    {
        $m = $this->data;
        $col = fn ($row, $key) => (isset($m[$key]) && $m[$key] !== '' && isset($row[(int) $m[$key]]))
            ? trim($row[(int) $m[$key]]) : null;

        $created = $updated = $skipped = 0;
        foreach ($this->rows as $row) {
            $eid = $col($row, 'map_employee_id');
            if (! $eid) { $skipped++; continue; }

            $first = $col($row, 'map_first');
            $last  = $col($row, 'map_last');
            if (! $first && ! $last) {
                $name = $col($row, 'map_name');
                if ($name) {
                    $parts = preg_split('/\s+/', $name, 2);
                    $first = $parts[0] ?? '';
                    $last = $parts[1] ?? '';
                }
            }

            $existing = Personnel::where('employee_id', $eid)->first();
            Personnel::updateOrCreate(
                ['employee_id' => $eid],
                array_filter([
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $col($row, 'map_email'),
                    'department' => $col($row, 'map_department'),
                    'is_active' => true,
                ], fn ($v) => $v !== null),
            );
            $existing ? $updated++ : $created++;
        }

        $this->createdCount = $created;
        $this->updatedCount = $updated;
        $this->skippedCount = $skipped;
        $this->imported = true;
        $this->parsed = false;

        Notification::make()->success()->title('Import complete')
            ->body("Created {$created}, updated {$updated}, skipped {$skipped} (no employee ID).")->send();
    }
}
