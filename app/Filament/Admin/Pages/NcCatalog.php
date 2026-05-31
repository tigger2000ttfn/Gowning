<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\NcDocument;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * NC Catalog. Loads a weekly TrackWise (Digital / Salesforce) nonconformance export so NC links
 * auto-fill from the NC number entered at a failed run, and the NC's workflow status is shown
 * wherever it is referenced. Mirrors the Veeva Catalog. Idempotent on NC number.
 */
class NcCatalog extends Page implements HasForms
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

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $slug = 'nc-catalog';

    protected string $view = 'filament.pages.nc-catalog';

    public ?array $data = [];
    public array $headers = [];
    public array $rows = [];
    public array $hyperlinks = [];
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
            FileUpload::make('csv')
                ->label('TrackWise NC Export File')
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
        $this->parsed = false;
        $this->imported = false;
        $this->rows = [];
        $this->preview = [];
        $this->headers = [];
        $this->hyperlinks = [];
        $this->data = [];
        try { $this->form->fill(); } catch (\Throwable $e) { /* ignore */ }
    }

    public function parse(): void
    {
        $rows = [];
        $this->hyperlinks = [];
        $full = $this->resolveUploadFullPath();
        if (! $full || ! is_file($full)) {
            Notification::make()->danger()->title('Upload A File First')
                ->body('The file upload did not complete. Pick the file again, wait for the progress bar to finish, then Parse.')->send();
            return;
        }
        $fmt = \App\Support\XlsxReader::detectFormat($full);

        if ($fmt === 'xlsx' || $fmt === 'html') {
            $read = $fmt === 'xlsx' ? \App\Support\XlsxReader::read($full) : \App\Support\XlsxReader::readHtml($full);
            $rows = $read['rows'];
            if (empty($rows)) { Notification::make()->danger()->title('Could Not Read The File')->body('No rows were found in the export.')->send(); return; }
            $rawLinks = $read['hyperlinks'];
            if (count($rows) < 2) { Notification::make()->danger()->title('Need A Header Row And At Least One Row')->send(); return; }
            $this->headers = array_map('trim', $rows[0]);
            $body = array_slice($rows, 1);
            $remapped = [];
            foreach ($body as $b => $_) {
                $abs = $b + 1;
                if (isset($rawLinks[$abs])) $remapped[$b] = $rawLinks[$abs];
            }
            $this->rows = $body;
            $this->hyperlinks = $remapped;
        } else {
            $fh = fopen($full, 'r');
            while (($r = fgetcsv($fh)) !== false) {
                if (count(array_filter($r, fn ($c) => trim((string) $c) !== '')) === 0) continue;
                $rows[] = $r;
            }
            fclose($fh);
            if (count($rows) < 2) { Notification::make()->danger()->title('Need A Header Row And At Least One Row')->send(); return; }
            $this->headers = array_map('trim', array_shift($rows));
            $this->rows = $rows;
        }

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
        $this->data['map_number']    = $guess(['nonconformance number', 'nc number', 'number']);
        $this->data['map_recordid']  = $guess(['nonconformance: id', ': id', 'record id', 'recordid', 'id']);
        $this->data['map_url']       = $guess(['link', 'url', 'permalink']);
        $this->data['map_status']    = $guess(['workflow status', 'status', 'state']);
        $this->data['map_created']   = $guess(['created date', 'created']);
        $this->data['map_closed']    = $guess(['date closed', 'closed']);
        $this->data['map_approver']  = $guess(['qa approver', 'approver']);
        $this->data['map_dept']      = $guess(['department']);
        $this->data['map_refs']      = $guess(['reference number', 'reference']);
        $this->data['map_site']      = $guess(['site']);
        $this->data['map_subgroup']  = $guess(['sub-group', 'subgroup', 'sub group']);
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
            Notification::make()->danger()->title('Map The Nonconformance Number Column')->send(); return;
        }

        $created = 0; $updated = 0;
        foreach ($this->rows as $ri => $row) {
            $number = $col($row, 'map_number');
            if (! $number) continue;
            $url = $col($row, 'map_url');
            if (! $url) {
                $linkCol = isset($m['map_url']) && $m['map_url'] !== '' ? (int) $m['map_url'] : null;
                $numCol = (int) $m['map_number'];
                $url = $this->hyperlinks[$ri][$linkCol] ?? $this->hyperlinks[$ri][$numCol] ?? null;
            }
            $recordId = $col($row, 'map_recordid') ?: NcDocument::extractRecordId($url);
            if (! $url && $recordId) {
                $url = NcDocument::urlFromRecordId($recordId);
            }

            $existing = NcDocument::where('nc_number', $number)->first();
            $payload = [
                'nc_number' => $number,
                'record_id' => $recordId ?: ($existing->record_id ?? null),
                'url' => $url ?: ($existing->url ?? null),
                'workflow_status' => $col($row, 'map_status') ?: ($existing->workflow_status ?? null),
                'created_date' => $this->parseDate($col($row, 'map_created')) ?: ($existing->created_date ?? null),
                'date_closed' => $this->parseDate($col($row, 'map_closed')) ?: ($existing->date_closed ?? null),
                'qa_approver' => $col($row, 'map_approver') ?: ($existing->qa_approver ?? null),
                'department' => $col($row, 'map_dept') ?: ($existing->department ?? null),
                'reference_numbers' => $col($row, 'map_refs') ?: ($existing->reference_numbers ?? null),
                'site' => $col($row, 'map_site') ?: ($existing->site ?? null),
                'sub_group' => $col($row, 'map_subgroup') ?: ($existing->sub_group ?? null),
                'catalog_synced_at' => now(),
            ];
            if ($existing) { $existing->update($payload); $updated++; }
            else { NcDocument::create($payload); $created++; }
        }

        $this->lastCreated = $created;
        $this->lastUpdated = $updated;
        $this->imported = true;
        $this->parsed = false;
        $this->rows = [];
        $this->preview = [];
        $this->hyperlinks = [];

        $path = $this->uploadedPath();
        if ($path) {
            try { Storage::disk('local')->delete($path); } catch (\Throwable $e) { /* best-effort */ }
            $this->data['csv'] = null;
        }
        $this->headers = [];

        $back = $this->backfillLinks();
        Notification::make()->success()->title('NC Catalog Updated')
            ->body("Added {$created}, updated {$updated}. Backfilled {$back} NC link(s).")->send();
    }

    protected function parseDate(?string $d): ?string
    {
        if (! $d) return null;
        try { return \Illuminate\Support\Carbon::parse($d)->toDateString(); }
        catch (\Throwable $e) { return null; }
    }

    /** Backfill NC links + TrackWise ids onto nonconformance records that have an NC number. */
    public function backfillLinks(): int
    {
        $n = 0;
        if (! \Illuminate\Support\Facades\Schema::hasColumn('non_conformances', 'trackwise_id')) return 0;
        foreach (\App\Models\NonConformance::whereNotNull('trackwise_id')->where('trackwise_id', '!=', '')->get() as $nc) {
            $doc = NcDocument::findByNumber($nc->trackwise_id);
            if (! $doc) continue;
            $changed = false;
            if (\Illuminate\Support\Facades\Schema::hasColumn('non_conformances', 'trackwise_url') && $doc->url && $nc->trackwise_url !== $doc->url) {
                $nc->trackwise_url = $doc->url; $changed = true;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('non_conformances', 'trackwise_status') && $doc->workflow_status && $nc->trackwise_status !== $doc->workflow_status) {
                $nc->trackwise_status = $doc->workflow_status; $changed = true;
            }
            if ($changed) { $nc->save(); $n++; }
        }
        return $n;
    }

    public function runBackfill(): void
    {
        if (! static::canAccessNavigation()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $n = $this->backfillLinks();
        Notification::make()->success()->title('Backfill Complete')->body("Linked {$n} NC record(s) from the catalog.")->send();
    }

    public function catalogRows(): array
    {
        $q = NcDocument::query()->latest('catalog_synced_at');
        if (trim($this->search) !== '') {
            $s = '%' . trim($this->search) . '%';
            $q->where(fn ($w) => $w->where('nc_number', 'ilike', $s)->orWhere('workflow_status', 'ilike', $s)->orWhere('qa_approver', 'ilike', $s));
        }
        return $q->limit(50)->get()->map(fn ($d) => [
            'nc_number' => $d->nc_number,
            'status' => $d->workflow_status,
            'closed' => in_array(strtolower(trim((string) $d->workflow_status)), NcDocument::CLOSED_STATUSES, true),
            'created' => $d->created_date?->format('d-M-Y'),
            'date_closed' => $d->date_closed?->format('d-M-Y'),
            'approver' => $d->qa_approver,
            'url' => $d->url,
            'synced' => $d->catalog_synced_at?->gmpDt(),
        ])->all();
    }

    public function catalogCount(): int
    {
        return NcDocument::count();
    }
}
