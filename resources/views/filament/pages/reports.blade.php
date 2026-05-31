<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Reports', 'icon' => 'heroicon-o-chart-bar'])

    {!! '<div class="gqs-tabs">
        <button type="button" wire:click="setTab(\'metrics\')" class="gqs-tab ' . ($tab === 'metrics' ? 'active' : '') . '">Metrics Dashboard</button>
        <button type="button" wire:click="setTab(\'reports\')" class="gqs-tab ' . ($tab === 'reports' ? 'active' : '') . '">Reports</button>
    </div>' !!}

    @if($tab === 'metrics')
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
                        <tr><td>{{ $q->personnel?->employee_id }}</td><td><button type="button" wire:click="$dispatch('open-qual-modal', { id: {{ $q->id }} })" style="background:none;border:none;padding:0;cursor:pointer;color:var(--gqs-text,#1A1A1F);font-weight:600;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;" title="Open record">{{ $q->personnel?->full_name }}</button></td>
                            <td><span class="gqs-pill gqs-pill-red">{{ $q->due_date?->gmp() }}</span></td></tr>
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
                        <tr><td>{{ $q->personnel?->employee_id }}</td><td><button type="button" wire:click="$dispatch('open-qual-modal', { id: {{ $q->id }} })" style="background:none;border:none;padding:0;cursor:pointer;color:var(--gqs-text,#1A1A1F);font-weight:600;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;" title="Open record">{{ $q->personnel?->full_name }}</button></td>
                            <td><span class="gqs-pill gqs-pill-purple">{{ $q->due_date?->gmp() }}</span></td></tr>
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

    {{-- Pipeline aging: who has been sitting in a stage too long --}}
    <div class="gqs-panel">
        <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-clock"/> Run Pipeline Aging (Days In Current Stage)</div>
        <div class="gqs-panel-body" style="padding:0;">
            @if($this->pipelineAging->isEmpty())
                <div class="gqs-empty" style="padding:20px;">Nobody is currently in the run pipeline.</div>
            @else
                <table class="gqs-tbl">
                    <thead><tr><th>Name</th><th>Employee</th><th>Stage</th><th>Type</th><th>Days In Stage</th></tr></thead>
                    <tbody>
                        @foreach($this->pipelineAging as $row)
                            @php $q = $row->qualification; $stale = $row->days >= 14; @endphp
                            <tr>
                                <td><button type="button" wire:click="$dispatch('open-qual-modal', { id: {{ $q->id }} })" style="background:none;border:none;padding:0;cursor:pointer;color:var(--gqs-text,#1A1A1F);font-weight:600;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;" title="Open record">{{ $q->personnel?->full_name ?? 'Unknown' }}</button></td>
                                <td>{{ $q->personnel?->employee_id ?: '—' }}</td>
                                <td>{{ \App\Models\WorkflowStatus::labelFor('run', $q->workflow_stage?->value, $q->workflow_stage?->label() ?? '—') }}</td>
                                <td>{{ $q->sessionLabel() }}</td>
                                <td><span class="gqs-pill {{ $stale ? 'gqs-pill-red' : 'gqs-pill-gray' }}">{{ $row->days }} day{{ $row->days === 1 ? '' : 's' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Full status roster --}}
    <div class="gqs-panel">
        <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-table-cells"/> Qualification Status Roster</div>
        <div class="gqs-panel-body" style="padding:0;">
            @if($this->statusRoster->isEmpty())
                <div class="gqs-empty" style="padding:20px;">No active qualification records.</div>
            @else
                <table class="gqs-tbl">
                    <thead><tr><th>Name</th><th>Employee</th><th>Department</th><th>Type</th><th>Stage</th><th>Runs</th><th>Due Date</th><th>Qualified</th></tr></thead>
                    <tbody>
                        @foreach($this->statusRoster as $q)
                            @php $pd = $q->isPastDue(); @endphp
                            <tr>
                                <td><button type="button" wire:click="$dispatch('open-qual-modal', { id: {{ $q->id }} })" style="background:none;border:none;padding:0;cursor:pointer;color:var(--gqs-text,#1A1A1F);font-weight:600;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;" title="Open record">{{ $q->personnel?->full_name ?? 'Unknown' }}</button></td>
                                <td>{{ $q->personnel?->employee_id ?: '—' }}</td>
                                <td>{{ $q->personnel?->department ?: '—' }}</td>
                                <td>{{ $q->sessionLabel() }}</td>
                                <td>{{ \App\Models\WorkflowStatus::labelFor('run', $q->workflow_stage?->value, $q->workflow_stage?->label() ?? '—') }}</td>
                                <td>{{ (int) $q->runs_completed }}/{{ (int) $q->runs_required }}</td>
                                <td style="{{ $pd ? 'color:#C8102E;font-weight:700;' : '' }}">{{ $q->due_date?->gmp() ?? '—' }}</td>
                                <td>{{ $q->qualified_date?->gmp() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
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
    @endif

    @if($tab === 'reports')
        <p style="margin:0 0 16px;color:var(--gqs-text-dim,#5A5A62);font-size:13.5px;">Run a prebuilt report to PDF. Each opens in a new tab ready to print or save.</p>
        @foreach($this->reportCatalog() as $group => $items)
            <div class="gqs-panel">
                <div class="gqs-panel-head">
                    @if($group === 'QA')<x-filament::icon icon="heroicon-m-shield-check"/>@elseif($group === 'QCM')<x-filament::icon icon="heroicon-m-beaker"/>@else<x-filament::icon icon="heroicon-m-document-text"/>@endif
                    {{ $group }} Reports
                </div>
                <div class="gqs-panel-body" style="padding:14px;">
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
                        @foreach($items as $r)
                            <a href="{{ $r['key'] === 'compliance' ? route('print.report') : route('print.report.key', $r['key']) }}" target="_blank" rel="noopener"
                               style="display:flex;gap:12px;align-items:flex-start;padding:14px;border:1px solid var(--gqs-border,#E2E2E8);border-radius:11px;background:var(--gqs-surface,#fff);text-decoration:none;transition:border-color .12s,box-shadow .12s;"
                               onmouseover="this.style.borderColor='#A4123F';this.style.boxShadow='0 3px 12px rgba(164,18,63,.12)';"
                               onmouseout="this.style.borderColor='';this.style.boxShadow='';">
                                <span style="flex:0 0 38px;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#A4123F,#7A0E2F);color:#fff;">
                                    <x-filament::icon :icon="$r['icon']" style="width:19px;height:19px;"/>
                                </span>
                                <span style="min-width:0;">
                                    <span style="display:block;font-weight:700;font-size:14px;color:var(--gqs-text,#1A1A1F);">{{ $r['name'] }}</span>
                                    <span style="display:block;font-size:12px;color:var(--gqs-text-dim,#6A6A72);margin-top:3px;line-height:1.4;">{{ $r['desc'] }}</span>
                                    <span style="display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;color:#A4123F;margin-top:7px;"><x-filament::icon icon="heroicon-m-printer" style="width:13px;height:13px;"/> Run PDF</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</x-filament-panels::page>
