<x-filament-panels::page>
    @php $tab = $this->tab ?? 'upload'; @endphp

    @include('filament.page-hero', ['title' => 'Veeva Catalog', 'icon' => 'heroicon-o-document-magnifying-glass', 'actions' => '
        <button type="button" wire:click="setTab(\'upload\')" class="gqs-tab ' . ($tab === 'upload' ? 'active' : '') . '">Upload</button>
        <button type="button" wire:click="setTab(\'catalog\')" class="gqs-tab ' . ($tab === 'catalog' ? 'active' : '') . '">Catalog</button>
    '])

    @if ($tab === 'upload')
        <div style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);margin-bottom:14px;">Upload the weekly Veeva export, then map the columns and import. Report links are built automatically.</div>

        <form wire:submit.prevent>{{ $this->form }}</form>

        <div style="margin:14px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <button type="button" wire:click="parse" class="gqs-btn gqs-btn-primary">Parse</button>
            <button type="button" wire:click="runBackfill" class="gqs-btn gqs-btn-ghost">Backfill Links Now</button>
            <span style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">{{ $this->catalogCount() }} documents in catalog. Backfill links existing reports from the catalog.</span>
        </div>

        @if ($parsed)
            <div class="gqs-panel">
                <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-link"/> Map Columns</div>
                <div class="gqs-panel-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
                        @php $fields = [
                            'map_number'  => 'Document Number *',
                            'map_vaultid' => 'Document ID (builds link)',
                            'map_url'     => 'Link / Permalink',
                            'map_title'   => 'Title',
                            'map_type'    => 'Type',
                            'map_status'  => 'Status',
                            'map_version' => 'Version',
                        ]; @endphp
                        @foreach ($fields as $key => $label)
                            <div>
                                <label class="gqs-flbl">{{ $label }}</label>
                                <select wire:model="data.{{ $key }}" class="gqs-fld">
                                    @foreach ($this->columnOptions() as $i => $h)<option value="{{ $i }}">{{ $h }}</option>@endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="gqs-panel">
                <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-table-cells"/> Preview</div>
                <div class="gqs-panel-body" style="padding:0;">
                    <p style="margin:0;padding:10px 16px 4px;color:var(--gqs-text-dim,#6A6A72);font-size:13px;">First {{ count($preview) }} of {{ count($rows) }} rows.</p>
                    <div style="overflow-x:auto;">
                        <table class="gqs-tbl">
                            <thead><tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                            <tbody>@foreach ($preview as $row)
                                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                            @endforeach</tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div style="margin:14px 0;">
                <button type="button" wire:click="import" class="gqs-btn gqs-btn-primary">Import {{ count($rows) }} Rows</button>
            </div>
        @endif

        @if ($imported)
            <div class="gqs-panel"><div class="gqs-panel-body" style="color:#1E7A52;">Catalog updated: added {{ $lastCreated }}, updated {{ $lastUpdated }}. Switch to the Catalog tab to browse.</div></div>
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
