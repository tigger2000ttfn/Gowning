<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Reports', 'icon' => 'heroicon-o-chart-bar'])

    <div style="margin-bottom:16px;">
        <a href="{{ route('print.report') }}" target="_blank"
           style="display:inline-flex;align-items:center;gap:7px;padding:10px 16px;background:#A4123F;color:#fff;border-radius:9px;font-weight:700;font-size:13px;text-decoration:none;">
            <x-filament::icon icon="heroicon-m-printer" style="width:16px;height:16px;"/> Print Compliance Report (PDF)
        </a>
    </div>

    @php $pf = $this->passFail; @endphp
    <div class="gqs-stats">
        <div class="gqs-stat red">
            <div class="n">{{ $this->overdue->count() }}</div><div class="l">Overdue</div>
            <span class="wm"><x-filament::icon icon="heroicon-o-exclamation-triangle"/></span>
        </div>
        <div class="gqs-stat purple">
            <div class="n">{{ $this->upcoming->count() }}</div><div class="l">Due In 60 Days</div>
            <span class="wm"><x-filament::icon icon="heroicon-o-clock"/></span>
        </div>
        <div class="gqs-stat green">
            <div class="n">{{ $pf['pass'] ?? 0 }}</div><div class="l">Total Passes</div>
            <span class="wm"><x-filament::icon icon="heroicon-o-check-badge"/></span>
        </div>
        <div class="gqs-stat gold">
            <div class="n">{{ $pf['fail'] ?? 0 }}</div><div class="l">Total Fails</div>
            <span class="wm"><x-filament::icon icon="heroicon-o-x-circle"/></span>
        </div>
    </div>

    <div class="gqs-panel">
        <div class="gqs-panel-head" style="background:linear-gradient(135deg,#C8102E,#920B22);">
            <x-filament::icon icon="heroicon-m-exclamation-triangle"/> Overdue Qualifications
        </div>
        <div class="gqs-panel-body">
            @if ($this->overdue->isEmpty())<div class="gqs-empty">None Overdue.</div>@else
                <table class="gqs-tbl">
                    <thead><tr><th>Employee</th><th>Name</th><th>Due</th></tr></thead>
                    <tbody>@foreach ($this->overdue as $q)
                        <tr><td>{{ $q->personnel?->employee_id }}</td><td>{{ $q->personnel?->full_name }}</td>
                            <td><span class="gqs-pill gqs-pill-red">{{ $q->due_date?->format('M j, Y') }}</span></td></tr>
                    @endforeach</tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="gqs-panel">
        <div class="gqs-panel-head" style="background:linear-gradient(135deg,#6B2C91,#4A1E66);">
            <x-filament::icon icon="heroicon-m-clock"/> Upcoming, Next 60 Days
        </div>
        <div class="gqs-panel-body">
            @if ($this->upcoming->isEmpty())<div class="gqs-empty">None Upcoming.</div>@else
                <table class="gqs-tbl">
                    <thead><tr><th>Employee</th><th>Name</th><th>Due</th></tr></thead>
                    <tbody>@foreach ($this->upcoming as $q)
                        <tr><td>{{ $q->personnel?->employee_id }}</td><td>{{ $q->personnel?->full_name }}</td>
                            <td><span class="gqs-pill gqs-pill-purple">{{ $q->due_date?->format('M j, Y') }}</span></td></tr>
                    @endforeach</tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="gqs-panel">
        <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-academic-cap"/> Class Completions By Class</div>
        <div class="gqs-panel-body">
            @forelse ($this->classStats as $row)
                <div style="display:flex;justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);">
                    <span>{{ $row->class_name }}</span><strong>{{ $row->n }}</strong>
                </div>
            @empty<div class="gqs-empty">No Completions Recorded.</div>@endforelse
        </div>
    </div>

    @php $nc = $this->ncTrend(); @endphp
    <div class="gqs-panel">
        <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-chart-bar-square"/> Non-Conformance Trending (Last 12 Months)</div>
        <div class="gqs-panel-body" style="padding:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#9A9AA4);margin-bottom:8px;">By Type</div>
                    @forelse($nc['type'] as $type => $n)
                        <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:13px;border-bottom:1px solid var(--gqs-border,#F2F2F4);">
                            <span>{{ \Illuminate\Support\Str::headline(str_replace('_',' ',$type)) }}</span><strong>{{ $n }}</strong>
                        </div>
                    @empty<div class="gqs-empty">No NCs.</div>@endforelse
                </div>
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#9A9AA4);margin-bottom:8px;">Top Organisms</div>
                    @forelse($nc['organism'] as $org => $n)
                        <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:13px;border-bottom:1px solid var(--gqs-border,#F2F2F4);">
                            <span>{{ $org }}</span><strong>{{ $n }}</strong>
                        </div>
                    @empty<div class="gqs-empty">No organism data.</div>@endforelse
                </div>
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#9A9AA4);margin-bottom:8px;">By Site</div>
                    @forelse($nc['site'] as $site => $n)
                        <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:13px;border-bottom:1px solid var(--gqs-border,#F2F2F4);">
                            <span>{{ $site }}</span><strong>{{ $n }}</strong>
                        </div>
                    @empty<div class="gqs-empty">No site data.</div>@endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="gqs-panel">
        <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-arrow-down-tray"/> LIMS Handoff Export</div>
        <div class="gqs-panel-body" style="padding:16px;">
            <p style="margin:0 0 12px;color:var(--gqs-text-dim,#5A5A62);font-size:13.5px;">Download recent run results as CSV for LIMS / records.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <x-filament::button wire:click="exportRuns" icon="heroicon-m-arrow-down-tray">Export Run Results (CSV)</x-filament::button>
                <x-filament::button wire:click="exportXlsx" color="gray" icon="heroicon-m-table-cells">Export Compliance Workbook (XLSX)</x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
