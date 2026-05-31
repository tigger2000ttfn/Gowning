<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\VeevaDocument;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Veeva Catalog. Loads a weekly Veeva export (doc number + permalink + metadata) so the system can
 * auto-fill a report's Veeva link from just the doc number entered at sign-off, and backfill links
 * on existing runs/sessions. Idempotent on doc number (re-import updates, adds new).
 */
class VeevaCatalog extends Page implements HasForms
{
    use InteractsWithForms;

    public function getHeading(): string { return ''; }
    public static function canAccessNavigation(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ManageScheduling) || $u->hasCapability(Capability::QaApprove)));
    }
    public static function shouldRegisterNavigation(): bool { return false; }
    public static function canViewAny(): bool { return static::canAccessNavigation(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static string|\UnitEnum|null $navigationGroup = 'Data Import';
    protected static ?string $navigationLabel = 'Veeva Catalog';
    protected static ?string $slug = 'veeva-catalog';

    protected string $view = 'filament.pages.veeva-catalog';

    public ?array $data = [];
    public array $headers = [];
    public array $rows = [];
    public array $hyperlinks = [];   // [rowIndexInRows][colIndex] => url (from xlsx embedded links)
    public array $preview = [];
    public bool $parsed = false;
    public bool $imported = false;
    public int $lastCreated = 0;
    public int $lastUpdated = 0;
    public string $search = '';
    public string $tab = 'upload';
    public function setTab(string $t): void { $this->tab = in_array($t, ['upload', 'catalog'], true) ? $t : 'upload'; }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('1. Load The Weekly Veeva Export')->icon('heroicon-o-arrow-up-tray')->schema([
                FileUpload::make('csv')
                    ->label('Veeva Export (CSV or XLSX)')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->storeFiles(true)
                    ->directory('imports')
                    ->helperText('A Veeva export with a header row: document number, link, title, type, status, version. XLSX is preferred when the document number is a hyperlink (the link is read from the cell). CSV drops hyperlinks, so include a link/permalink column if exporting CSV.'),
                Textarea::make('paste')
                    ->label('Or Paste CSV / TSV')
                    ->rows(5)
                    ->helperText('Paste rows including a header. Tabs or commas both work.'),
            ]),
        ]);
    }

    public function parse(): void
    {
        $rows = [];
        $this->hyperlinks = [];
        $paste = trim((string) ($this->data['paste'] ?? ''));
        $path = $this->data['csv'] ?? null;
        $full = $path ? Storage::disk('local')->path($path) : null;
        $isXlsx = $full && preg_match('/\.xlsx$/i', $full);

        if ($paste !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $paste);
            $delim = str_contains($lines[0] ?? '', "\t") ? "\t" : ',';
            foreach ($lines as $ln) {
                if (trim($ln) === '') continue;
                $rows[] = str_getcsv($ln, $delim);
            }
        } elseif ($isXlsx) {
            if (! is_file($full)) { Notification::make()->danger()->title('File Not Found')->send(); return; }
            $read = \App\Support\XlsxReader::read($full);
            $rows = $read['rows'];
            // Re-index hyperlinks from absolute row index to the row's position after the header is shifted.
            // We shift by one (header row) below; capture raw here and remap after array_shift.
            $rawLinks = $read['hyperlinks'];
            if (count($rows) < 2) { Notification::make()->danger()->title('Need A Header Row And At Least One Row')->send(); return; }
            // Drop empty rows but keep a mapping so hyperlinks line up.
            $this->headers = array_map('trim', $rows[0]);
            $body = array_slice($rows, 1);
            // Remap hyperlinks: rawLinks is keyed by absolute row index; body row b corresponds to abs index b+1.
            $remapped = [];
            foreach ($body as $b => $_) {
                $abs = $b + 1;
                if (isset($rawLinks[$abs])) $remapped[$b] = $rawLinks[$abs];
            }
            $this->rows = $body;
            $this->hyperlinks = $remapped;
            $this->guessColumns();
            $this->preview = array_slice($this->rows, 0, 8);
            $this->parsed = true;
            $this->imported = false;
            return;
        } else {
            if (! $path) { Notification::make()->danger()->title('Upload Or Paste First')->send(); return; }
            if (! is_file($full)) { Notification::make()->danger()->title('File Not Found')->send(); return; }
            $fh = fopen($full, 'r');
            while (($r = fgetcsv($fh)) !== false) {
                if (count(array_filter($r, fn ($c) => trim((string) $c) !== '')) === 0) continue;
                $rows[] = $r;
            }
            fclose($fh);
        }

        if (count($rows) < 2) { Notification::make()->danger()->title('Need A Header Row And At Least One Row')->send(); return; }

        $this->headers = array_map('trim', array_shift($rows));
        $this->rows = $rows;
        $this->guessColumns();

        $this->preview = array_slice($this->rows, 0, 8);
        $this->parsed = true;
        $this->imported = false;
    }

    protected function guessColumns(): void
    {
        $guess = function (array $names) {
            foreach ($this->headers as $i => $h) {
                $hl = strtolower($h);
                foreach ($names as $n) if (str_contains($hl, $n)) return (string) $i;
            }
            return null;
        };
        $this->data['map_number']  = $guess(['document number', 'doc number', 'docnumber', 'number', 'rpt']);
        $this->data['map_url']     = $guess(['document link', 'source link', 'permalink', 'link', 'url']);
        $this->data['map_vaultid'] = $guess(['document id', 'doc id', 'docid', 'document i']);
        $this->data['map_title']   = $guess(['title', 'document name']);
        $this->data['map_type']    = $guess(['type']);
        $this->data['map_status']  = $guess(['status', 'state', 'lifecycle']);
        $this->data['map_version'] = $guess(['version', 'rev']);
    }

    public function columnOptions(): array
    {
        $opts = ['' => '— none —'];
        foreach ($this->headers as $i => $h) $opts[(string) $i] = $h;
        return $opts;
    }

    public function import(): void
    {
        if (! static::canAccessNavigation()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $m = $this->data;
        $col = fn ($row, $key) => (isset($m[$key]) && $m[$key] !== '' && isset($row[(int) $m[$key]]))
            ? trim((string) $row[(int) $m[$key]]) : null;

        if (! isset($m['map_number']) || $m['map_number'] === '') {
            Notification::make()->danger()->title('Map The Document Number Column')->send(); return;
        }

        $created = 0; $updated = 0;
        foreach ($this->rows as $ri => $row) {
            $number = $col($row, 'map_number');
            if (! $number) continue;
            $url = $col($row, 'map_url');
            // If no link column value, use a hyperlink embedded in the link cell or the number cell
            // (Veeva often hyperlinks the document number itself; CSV loses this, xlsx keeps it).
            if (! $url) {
                $linkCol = isset($m['map_url']) && $m['map_url'] !== '' ? (int) $m['map_url'] : null;
                $numCol = (int) $m['map_number'];
                $url = $this->hyperlinks[$ri][$linkCol] ?? $this->hyperlinks[$ri][$numCol] ?? null;
            }
            // Capture the numeric Vault document id and, if we still have no URL, build one from it
            // using the documented doc_info scheme (the most reliable path for these exports).
            $vaultId = $col($row, 'map_vaultid');
            if (! $url && $vaultId) {
                $url = VeevaDocument::urlFromVaultId($vaultId);
            }
            $docId = VeevaDocument::extractDocId($url);

            $existing = VeevaDocument::where('doc_number', $number)->first();
            $payload = [
                'doc_number' => $number,
                'doc_id' => $docId ?: ($existing->doc_id ?? null),
                'vault_id' => $vaultId ?: ($existing->vault_id ?? null),
                'url' => $url ?: ($existing->url ?? null),
                'title' => $col($row, 'map_title') ?: ($existing->title ?? null),
                'type' => $col($row, 'map_type') ?: ($existing->type ?? null),
                'status' => $col($row, 'map_status') ?: ($existing->status ?? null),
                'version' => $col($row, 'map_version') ?: ($existing->version ?? null),
                'catalog_synced_at' => now(),
            ];
            if ($existing) { $existing->update($payload); $updated++; }
            else { VeevaDocument::create($payload); $created++; }
        }

        $this->lastCreated = $created;
        $this->lastUpdated = $updated;
        $this->imported = true;
        $this->parsed = false;
        $this->rows = [];
        $this->preview = [];

        // After loading the catalog, backfill any reports that have a number but no link yet.
        $back = $this->backfillLinks();

        Notification::make()->success()->title('Catalog Updated')
            ->body("Added {$created}, updated {$updated}. Backfilled {$back} report link(s).")->send();
    }

    /** Backfill veeva_url on runs (and class sessions) that have a Veeva number but no link. */
    public function backfillLinks(): int
    {
        $n = 0;
        // Runs
        foreach (\App\Models\QualificationRun::whereNotNull('veeva_doc_number')
            ->where(fn ($q) => $q->whereNull('veeva_url')->orWhere('veeva_url', ''))->get() as $run) {
            $url = VeevaDocument::urlForNumber($run->veeva_doc_number);
            if ($url) { $run->update(['veeva_url' => $url]); $n++; }
        }
        // Class sessions
        if (\Illuminate\Support\Facades\Schema::hasColumn('class_sessions', 'veeva_url')) {
            foreach (\App\Models\ClassSession::whereNotNull('veeva_doc_number')
                ->where(fn ($q) => $q->whereNull('veeva_url')->orWhere('veeva_url', ''))->get() as $s) {
                $url = VeevaDocument::urlForNumber($s->veeva_doc_number);
                if ($url) { $s->update(['veeva_url' => $url]); $n++; }
            }
        }
        return $n;
    }

    public function runBackfill(): void
    {
        if (! static::canAccessNavigation()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $n = $this->backfillLinks();
        Notification::make()->success()->title('Backfill Complete')->body("Linked {$n} report(s) from the catalog.")->send();
    }

    public function catalogRows(): array
    {
        $q = VeevaDocument::query()->latest('catalog_synced_at');
        if (trim($this->search) !== '') {
            $s = '%' . trim($this->search) . '%';
            $q->where(fn ($w) => $w->where('doc_number', 'ilike', $s)->orWhere('title', 'ilike', $s)->orWhere('type', 'ilike', $s));
        }
        return $q->limit(50)->get()->map(fn ($d) => [
            'doc_number' => $d->doc_number,
            'title' => $d->title,
            'type' => $d->type,
            'status' => $d->status,
            'version' => $d->version,
            'url' => $d->url,
            'synced' => $d->catalog_synced_at?->gmpDt(),
        ])->all();
    }

    public function catalogCount(): int
    {
        return VeevaDocument::count();
    }
}
