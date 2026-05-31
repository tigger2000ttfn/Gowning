<x-filament-panels::page>
    @php $tab = $this->tab ?? 'upload'; @endphp

    @include('filament.page-hero', ['title' => 'Veeva Catalog', 'icon' => 'heroicon-o-document-magnifying-glass', 'actions' => '
        <button type="button" wire:click="setTab(\'upload\')" class="gqs-tab ' . ($tab === 'upload' ? 'active' : '') . '">Upload</button>
        <button type="button" wire:click="setTab(\'catalog\')" class="gqs-tab ' . ($tab === 'catalog' ? 'active' : '') . '">Catalog</button>
    '])

    @if ($tab === 'upload')
        {{-- Step indicator --}}
        <div style="display:flex;gap:8px;margin-bottom:16px;align-items:center;">
            <span class="gqs-pill {{ ! $parsed && ! $imported ? 'gqs-pill-purple' : 'gqs-pill-gray' }}">1 · Upload</span>
            <span style="color:var(--gqs-text-dim,#9A9AA4);">→</span>
            <span class="gqs-pill {{ $parsed ? 'gqs-pill-purple' : 'gqs-pill-gray' }}">2 · Review &amp; Import</span>
            <span style="color:var(--gqs-text-dim,#9A9AA4);">→</span>
            <span class="gqs-pill {{ $imported ? 'gqs-pill-green' : 'gqs-pill-gray' }}">3 · Done</span>
        </div>

        @if (! $parsed && ! $imported)
            {{-- STEP 1: upload. Parse is disabled until the file upload finishes (Livewire fires
                 livewire-upload-start / livewire-upload-finish on the component root). --}}
            <div class="gqs-panel"
                 x-data="{ busy: false }"
                 x-on:form-processing-started="busy = true"
                 x-on:form-processing-finished="busy = false">
                <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-arrow-up-tray"/> Upload Weekly Export</div>
                <div class="gqs-panel-body" style="padding:16px;position:relative;">
                    <div style="display:flex;flex-direction:column;align-items:center;text-align:center;padding:10px 0 18px;">
                        <span style="width:72px;height:72px;border-radius:20px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#A4123F,#6E0C2A);box-shadow:0 10px 28px rgba(164,18,63,.32);margin-bottom:14px;">
                            <x-filament::icon icon="heroicon-o-document-arrow-up" style="width:38px;height:38px;color:#fff;"/>
                        </span>
                        <div style="font-size:16px;font-weight:800;color:var(--gqs-text,#1A1A1F);">Drop Your Veeva Export Here</div>
                        <p style="margin:6px 0 0;font-size:13px;color:var(--gqs-text-dim,#6A6A72);max-width:440px;">Upload the weekly export (.xlsx). Columns are detected automatically and report links are built for you.</p>
                    </div>
                    <form wire:submit.prevent>{{ $this->form }}</form>
                    <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <span style="font-size:12px;color:#A4123F;font-weight:600;" x-show="busy" x-cloak>Uploading file, please wait...</span>
                        <span style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">{{ $this->catalogCount() }} in catalog</span>
                        <button type="button" wire:click="parse" wire:loading.attr="disabled" wire:target="parse" class="gqs-btn gqs-btn-primary" style="margin-left:auto;"
                                x-bind:disabled="busy"
                                x-bind:style="busy ? 'opacity:.5;cursor:wait;' : ''">
                            <span x-show="busy" x-cloak>Uploading...</span>
                            <span x-show="!busy">Parse</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if ($parsed)
            {{-- STEP 2: review + import. Import is on the right; a processing overlay covers the panel
                 while the import runs (wire:loading targeting import). --}}
            <div class="gqs-panel" style="position:relative;">
                <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-table-cells"/> Review &amp; Import</div>
                <div class="gqs-panel-body" style="padding:0;">
                    <p style="margin:0;padding:12px 16px 6px;color:var(--gqs-text-dim,#6A6A72);font-size:13px;">Detected {{ count($rows) }} rows. Showing the first {{ count($preview) }}.</p>
                    <div style="overflow-x:auto;">
                        <table class="gqs-tbl">
                            <thead><tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                            <tbody>@foreach ($preview as $row)
                                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                            @endforeach</tbody>
                        </table>
                    </div>
                    <div style="padding:14px 16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <button type="button" wire:click="resetUpload" class="gqs-btn gqs-btn-ghost">Cancel</button>
                        <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import" class="gqs-btn gqs-btn-primary" style="margin-left:auto;">
                            <span wire:loading.remove wire:target="import">Import {{ count($rows) }} Rows</span>
                            <span wire:loading wire:target="import">Importing...</span>
                        </button>
                    </div>
                </div>
                {{-- processing overlay --}}
                <div wire:loading.flex wire:target="import" class="gqs-import-overlay">
                    <div class="gqs-import-card">
                        <div class="gqs-spinner"></div>
                        <div style="font-weight:700;color:#fff;margin-top:12px;">Importing {{ count($rows) }} rows...</div>
                        <div style="font-size:12px;color:#C9C9D2;margin-top:4px;">Building links and updating the catalog</div>
                    </div>
                </div>
            </div>
        @endif

        @if ($imported)
            {{-- STEP 3: done + backfill --}}
            <div class="gqs-panel">
                <div class="gqs-panel-head" style="background:linear-gradient(135deg,#2E7D5B,#225F46);"><x-filament::icon icon="heroicon-m-check-circle"/> Import Complete</div>
                <div class="gqs-panel-body" style="padding:16px;">
                    <p style="margin:0 0 14px;color:#1E7A52;font-size:13.5px;">Catalog updated: added {{ $lastCreated }}, updated {{ $lastUpdated }}.</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" wire:click="setTab('catalog')" class="gqs-btn gqs-btn-primary">View Catalog</button>
                        <button type="button" wire:click="runBackfill" class="gqs-btn gqs-btn-ghost">Backfill Links Now</button>
                        <button type="button" wire:click="resetUpload" class="gqs-btn gqs-btn-ghost">Upload Another</button>
                    </div>
                </div>
            </div>
        @endif
    @else
        {{-- ===================== CATALOG TAB ===================== --}}
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-document-magnifying-glass"/> Catalog
                <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;">{{ $this->catalogCount() }} documents</span>
            </div>
            <div class="gqs-panel-body">
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px;">
                    <input type="text" wire:model.live.debounce.300ms="search" class="gqs-fld" placeholder="Search document number, title, or type..." style="max-width:420px;">
                    <button type="button" wire:click="runBackfill" class="gqs-btn gqs-btn-ghost">Backfill Links Now</button>
                </div>
                <div style="overflow-x:auto;">
                    <table class="gqs-tbl">
                        <thead><tr><th>Document Number</th><th>Title</th><th>Type</th><th>Status</th><th>Version</th><th>Link</th><th>Synced</th></tr></thead>
                        <tbody>
                            @forelse ($this->catalogRows() as $d)
                                <tr>
                                    <td style="font-weight:700;">{{ $d['doc_number'] }}</td>
                                    <td>{{ $d['title'] ?: '—' }}</td>
                                    <td>{{ $d['type'] ?: '—' }}</td>
                                    <td>{{ $d['status'] ?: '—' }}</td>
                                    <td>{{ $d['version'] ?: '—' }}</td>
                                    <td>@if($d['url'])<a href="{{ $d['url'] }}" target="_blank" rel="noopener" style="color:#A4123F;font-weight:700;">Open ↗</a>@else <span style="color:var(--gqs-text-dim,#9A9AA4);">—</span>@endif</td>
                                    <td>{{ $d['synced'] ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" style="text-align:center;padding:18px;color:var(--gqs-text-dim,#6A6A72);">No documents in the catalog yet. Load a Veeva export on the Upload tab.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
