<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\VeevaDocument;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
    public array $preview = [];        // first 8 rows only, for display
    public int $rowCount = 0;
    public ?string $parsedPath = null; // relative path of the uploaded file, re-read at import
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
            FileUpload::make('csv')
                ->label('Veeva Export File')
                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                ->disk('local')
                ->storeFiles(true)
                ->directory('imports')
                ->helperText('Excel (.xlsx) or CSV with a header row.'),
        ]);
    }

    /**
     * Filament's FileUpload stores its value as an array (keyed by a random id) even for a single
     * file, so $this->data['csv'] may be ['abc' => 'imports/x.xlsx']. Normalize to one string path.
     */
    /**
     * Resolve the uploaded file to an absolute filesystem path. The FileUpload value can be a string
     * path, an array keyed by a random id, or (mid-request) a TemporaryUploadedFile. We pin the
     * upload to the 'local' disk, but resolve defensively across local/public so a disk-config
     * difference cannot strand the file. Returns the absolute path, or null.
     */
    protected function resolveUploadFullPath(): ?string
    {
        $candidates = [];
        $candidates[] = $this->data['csv'] ?? null;
        try { $candidates[] = ($this->form->getState())['csv'] ?? null; } catch (\Throwable $e) {}

        foreach ($candidates as $v) {
            // TemporaryUploadedFile: read straight from its real temp path.
            if ($v instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                $rp = $v->getRealPath();
                if ($rp && is_file($rp)) return $rp;
            }
            if (is_array($v)) {
                $v = collect($v)->flatten()->first(function ($item) {
                    return $item instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
                        || (is_string($item) && $item !== '');
                });
                if ($v instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                    $rp = $v->getRealPath();
                    if ($rp && is_file($rp)) return $rp;
                }
            }
            if (is_string($v) && $v !== '') {
                foreach (['local', 'public'] as $disk) {
                    try {
                        if (Storage::disk($disk)->exists($v)) {
                            return Storage::disk($disk)->path($v);
                        }
                    } catch (\Throwable $e) {}
                }
                // Last resort: maybe it's already an absolute path.
                if (is_file($v)) return $v;
            }
        }
        return null;
    }

    /** The stored relative path (for cleanup), best-effort. */
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
        try { $this->form->fill(); } catch (\Throwable $e) { /* ignore */ }
    }

    /**
     * Read a file into [headers, body rows, per-row hyperlinks]. Shared by parse (preview) and import,
     * so all rows are never held in Livewire state. Detects xlsx/html/csv and sanitizes UTF-8.
     *
     * @return array{headers: array<int,string>, rows: array<int,array>, hyperlinks: array<int,array>}|null
     */
    protected function readRows(string $full): ?array
    {
        $fmt = \App\Support\XlsxReader::detectFormat($full);
        if ($fmt === 'xlsx' || $fmt === 'html') {
            $read = $fmt === 'xlsx' ? \App\Support\XlsxReader::read($full) : \App\Support\XlsxReader::readHtml($full);
            $rows = $read['rows'];
            if (count($rows) < 2) return null;
            $headers = array_map(fn ($h) => \App\Support\XlsxReader::toUtf8(trim((string) $h)), $rows[0]);
            $body = array_slice($rows, 1);
            $remapped = [];
            foreach ($body as $b => $_) {
                $abs = $b + 1;
                if (isset($read['hyperlinks'][$abs])) $remapped[$b] = $read['hyperlinks'][$abs];
            }
            return ['headers' => $headers, 'rows' => array_values($body), 'hyperlinks' => array_values($remapped)];
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
        try {
            $read = $this->readRows($full);
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Could Not Parse The File')
                ->body(\Illuminate\Support\Str::limit($e->getMessage(), 180))->send();
            return;
        }
        if (! $read) { Notification::make()->danger()->title('Need A Header Row And At Least One Row')->send(); return; }

        $this->headers = $read['headers'];
        $this->rowCount = count($read['rows']);
        $this->preview = array_map(
            fn ($row) => array_map(fn ($c) => \Illuminate\Support\Str::limit((string) $c, 80), $row),
            array_slice($read['rows'], 0, 8)
        );
        $this->parsedPath = $this->uploadedPath();
        $this->guessColumns();
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
        if (! \Illuminate\Support\Facades\Schema::hasTable('veeva_documents')) {
            Notification::make()->danger()->title('Veeva Catalog Table Missing')
                ->body('The veeva_documents table does not exist yet. Run: php artisan migrate --force, then try again.')->send();
            return;
        }
        $m = $this->data;
        $col = fn ($row, $key) => (isset($m[$key]) && $m[$key] !== '' && isset($row[(int) $m[$key]]))
            ? trim((string) $row[(int) $m[$key]]) : null;

        if (! isset($m['map_number']) || $m['map_number'] === '') {
            Notification::make()->danger()->title('Map The Document Number Column')->send(); return;
        }

        // Re-read the full file now (rows are NOT kept in Livewire state).
        $full = $this->parsedPath ? Storage::disk('local')->path($this->parsedPath) : $this->resolveUploadFullPath();
        if (! $full || ! is_file($full)) {
            Notification::make()->danger()->title('Upload Expired')->body('Please re-upload and parse the file, then import.')->send();
            return;
        }
        try {
            $read = $this->readRows($full);
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Import Failed')->body(\Illuminate\Support\Str::limit($e->getMessage(), 180))->send();
            return;
        }
        if (! $read) { Notification::make()->danger()->title('Could Not Read The File')->send(); return; }
        $rows = $read['rows'];
        $hyperlinks = $read['hyperlinks'];

        $created = 0; $updated = 0;
        try {
            foreach ($rows as $ri => $row) {
                $number = $col($row, 'map_number');
                if (! $number) continue;
                $url = $col($row, 'map_url');
                if (! $url) {
                    $linkCol = isset($m['map_url']) && $m['map_url'] !== '' ? (int) $m['map_url'] : null;
                    $numCol = (int) $m['map_number'];
                    $url = $hyperlinks[$ri][$linkCol] ?? $hyperlinks[$ri][$numCol] ?? null;
                }
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
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Import Failed')
                ->body(\Illuminate\Support\Str::limit($e->getMessage(), 180))->send();
            return;
        }

        $this->lastCreated = $created;
        $this->lastUpdated = $updated;
        $this->imported = true;
        $this->parsed = false;
        $this->preview = [];
        $this->rowCount = 0;

        foreach (array_filter([$this->parsedPath, $this->uploadedPath()]) as $p) {
            try { Storage::disk('local')->delete($p); } catch (\Throwable $e) { /* best-effort */ }
        }
        $this->parsedPath = null;
        $this->data['csv'] = null;
        $this->headers = [];

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
