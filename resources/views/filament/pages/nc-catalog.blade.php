<x-filament-panels::page>
    @php $tab = $this->tab ?? 'upload'; @endphp

    @include('filament.page-hero', ['title' => 'NC Catalog', 'icon' => 'heroicon-o-exclamation-triangle', 'actions' => '
        <button type="button" wire:click="setTab(\'upload\')" class="gqs-tab ' . ($tab === 'upload' ? 'active' : '') . '">Upload</button>
        <button type="button" wire:click="setTab(\'catalog\')" class="gqs-tab ' . ($tab === 'catalog' ? 'active' : '') . '">Catalog</button>
    '])

    @if ($tab === 'upload')
        <div style="display:flex;gap:8px;margin-bottom:16px;align-items:center;">
            <span class="gqs-pill {{ ! $parsed && ! $imported ? 'gqs-pill-purple' : 'gqs-pill-gray' }}">1 · Upload</span>
            <span style="color:var(--gqs-text-dim,#9A9AA4);">→</span>
            <span class="gqs-pill {{ $parsed ? 'gqs-pill-purple' : 'gqs-pill-gray' }}">2 · Review &amp; Import</span>
            <span style="color:var(--gqs-text-dim,#9A9AA4);">→</span>
            <span class="gqs-pill {{ $imported ? 'gqs-pill-green' : 'gqs-pill-gray' }}">3 · Done</span>
        </div>

        @if (! $parsed && ! $imported)
            <div class="gqs-panel">
                <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-arrow-up-tray"/> Upload Weekly NC Export</div>
                <div class="gqs-panel-body" style="padding:16px;">
                    <div style="display:flex;flex-direction:column;align-items:center;text-align:center;padding:10px 0 18px;">
                        <span style="width:72px;height:72px;border-radius:20px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#A4123F,#6E0C2A);box-shadow:0 10px 28px rgba(164,18,63,.32);margin-bottom:14px;">
                            <x-filament::icon icon="heroicon-o-document-arrow-up" style="width:38px;height:38px;color:#fff;"/>
                        </span>
                        <div style="font-size:16px;font-weight:800;color:var(--gqs-text,#1A1A1F);">Drop Your TrackWise NC Export Here</div>
                        <p style="margin:6px 0 0;font-size:13px;color:var(--gqs-text-dim,#6A6A72);max-width:440px;">Upload the weekly NC export (.xlsx). Columns are detected automatically and NC links are built for you.</p>
                    </div>
                    <form wire:submit.prevent>{{ $this->form }}</form>
                    <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <button type="button" wire:click="parse" class="gqs-btn gqs-btn-primary">Parse</button>
                        <span style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);margin-left:auto;">{{ $this->catalogCount() }} in catalog</span>
                    </div>
                </div>
            </div>
        @endif

        @if ($parsed)
            <div class="gqs-panel">
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
                    <div style="padding:14px 16px;display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" wire:click="import" class="gqs-btn gqs-btn-primary">Import {{ count($rows) }} Rows</button>
                        <button type="button" wire:click="resetUpload" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($imported)
            <div class="gqs-panel">
                <div class="gqs-panel-head" style="background:linear-gradient(135deg,#2E7D5B,#225F46);"><x-filament::icon icon="heroicon-m-check-circle"/> Import Complete</div>
                <div class="gqs-panel-body" style="padding:16px;">
                    <p style="margin:0 0 14px;color:#1E7A52;font-size:13.5px;">NC catalog updated: added {{ $lastCreated }}, updated {{ $lastUpdated }}.</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" wire:click="setTab('catalog')" class="gqs-btn gqs-btn-primary">View Catalog</button>
                        <button type="button" wire:click="runBackfill" class="gqs-btn gqs-btn-ghost">Backfill Links Now</button>
                        <button type="button" wire:click="resetUpload" class="gqs-btn gqs-btn-ghost">Upload Another</button>
                    </div>
                </div>
            </div>
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
