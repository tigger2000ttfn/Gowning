<x-filament-panels::page>
    @php $tab = $this->tab ?? 'upload'; @endphp

    @include('filament.page-hero', ['title' => 'NC Catalog', 'icon' => 'heroicon-o-exclamation-triangle', 'actions' => '
        <button type="button" wire:click="setTab(\'upload\')" class="gqs-tab ' . ($tab === 'upload' ? 'active' : '') . '">Upload</button>
        <button type="button" wire:click="setTab(\'catalog\')" class="gqs-tab ' . ($tab === 'catalog' ? 'active' : '') . '">Catalog</button>
    '])

    @if ($tab === 'upload')
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-arrow-up-tray"/> Load Weekly NC Export</div>
            <div class="gqs-panel-body" style="padding:16px;">
                <p style="margin:0 0 14px;font-size:13px;color:var(--gqs-text-dim,#6A6A72);">Upload the weekly TrackWise NC export, map the columns, and import. NC links are built automatically.</p>
                <form wire:submit.prevent>{{ $this->form }}</form>
                <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <button type="button" wire:click="parse" class="gqs-btn gqs-btn-primary">Parse</button>
                    <button type="button" wire:click="runBackfill" class="gqs-btn gqs-btn-ghost">Backfill Links Now</button>
                    <span style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);margin-left:auto;">{{ $this->catalogCount() }} in catalog</span>
                </div>
            </div>
        </div>

        @if ($parsed)
            <div class="gqs-panel">
                <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-link"/> Map Columns</div>
                <div class="gqs-panel-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
                        @php $fields = [
                            'map_number'   => 'Nonconformance Number *',
                            'map_recordid' => 'Record ID (builds link)',
                            'map_url'      => 'Link / URL',
                            'map_status'   => 'Workflow Status',
                            'map_created'  => 'Created Date',
                            'map_closed'   => 'Date Closed',
                            'map_approver' => 'QA Approver',
                            'map_dept'     => 'Department',
                            'map_refs'     => 'Reference Number(s)',
                            'map_site'     => 'Site',
                            'map_subgroup' => 'Sub-Group',
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
            <div class="gqs-panel"><div class="gqs-panel-body" style="color:#1E7A52;">NC catalog updated: added {{ $lastCreated }}, updated {{ $lastUpdated }}. Switch to the Catalog tab to browse.</div></div>
        @endif
    @else
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-exclamation-triangle"/> NC Catalog
                <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;">{{ $this->catalogCount() }} NCs</span>
            </div>
            <div class="gqs-panel-body">
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px;">
                    <input type="text" wire:model.live.debounce.300ms="search" class="gqs-fld" placeholder="Search NC number, status, or approver..." style="max-width:420px;">
                    <button type="button" wire:click="runBackfill" class="gqs-btn gqs-btn-ghost">Backfill Links Now</button>
                </div>
                <div style="overflow-x:auto;">
                    <table class="gqs-tbl">
                        <thead><tr><th>NC Number</th><th>Status</th><th>Created</th><th>Closed</th><th>QA Approver</th><th>Link</th><th>Synced</th></tr></thead>
                        <tbody>
                            @forelse ($this->catalogRows() as $d)
                                <tr>
                                    <td style="font-weight:700;">{{ $d['nc_number'] }}</td>
                                    <td>@if($d['closed'])<span class="gqs-pill gqs-pill-gray">{{ $d['status'] ?: 'Closed' }}</span>@else<span class="gqs-pill gqs-pill-gold">{{ $d['status'] ?: 'Open' }}</span>@endif</td>
                                    <td>{{ $d['created'] ?: '—' }}</td>
                                    <td>{{ $d['date_closed'] ?: '—' }}</td>
                                    <td>{{ $d['approver'] ?: '—' }}</td>
                                    <td>@if($d['url'])<a href="{{ $d['url'] }}" target="_blank" rel="noopener" style="color:#A4123F;font-weight:700;">Open ↗</a>@else <span style="color:var(--gqs-text-dim,#9A9AA4);">—</span>@endif</td>
                                    <td>{{ $d['synced'] ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" style="text-align:center;padding:18px;color:var(--gqs-text-dim,#6A6A72);">No NCs in the catalog yet. Load a TrackWise export on the Upload tab.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
