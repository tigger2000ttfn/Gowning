<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'QC Micro Team & Assignments', 'subtitle' => 'Analyst workload across run days and class sessions.', 'icon' => 'heroicon-o-user-group'])

    @php $analysts = $this->getAnalysts(); $unassigned = $this->getUnassignedRunDays(); @endphp

    <div class="gqs-stats">
        <div class="gqs-stat magenta"><div class="n">{{ $analysts->count() }}</div><div class="l">Analysts</div><span class="wm"><x-filament::icon icon="heroicon-o-users"/></span></div>
        <div class="gqs-stat gold"><div class="n">{{ $unassigned->count() }}</div><div class="l">Unassigned Run Days</div><span class="wm"><x-filament::icon icon="heroicon-o-exclamation-triangle"/></span></div>
        <div class="gqs-stat charcoal"><div class="n">{{ $analysts->sum('load') }}</div><div class="l">Total Assignments</div><span class="wm"><x-filament::icon icon="heroicon-o-clipboard-document-list"/></span></div>
    </div>

    @if($unassigned->isNotEmpty())
        <div class="gqs-panel">
            <div class="gqs-panel-head" style="background:linear-gradient(135deg,#C79A2E,#9E7818);"><x-filament::icon icon="heroicon-m-exclamation-triangle"/> Unassigned Run Days</div>
            <div class="gqs-panel-body">
                @foreach($unassigned as $s)
                    <div style="display:flex;justify-content:space-between;padding:9px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);font-size:13.5px;">
                        <span>{{ $s->slot_date?->format('l, M j') }} · {{ $s->cleanroom }}</span>
                        <a href="{{ \App\Filament\Admin\Resources\RunSlotResource::getUrl('edit', ['record' => $s->id]) }}" style="color:#A4123F;font-weight:700;">Assign →</a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @forelse($analysts as $a)
        <div class="gqs-panel">
            <div class="gqs-panel-head" style="justify-content:space-between;">
                <span style="display:flex;align-items:center;gap:9px;"><x-filament::icon icon="heroicon-m-user"/> {{ $a->name }}</span>
                <span style="font-size:12px;font-weight:600;opacity:.92;">{{ $a->load }} {{ \Illuminate\Support\Str::plural('assignment', $a->load) }}</span>
            </div>
            <div class="gqs-panel-body">
                @if($a->run_days->isEmpty() && $a->classes->isEmpty())
                    <div class="gqs-empty">No Upcoming Assignments.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Type</th><th>Date</th><th>Detail</th></tr></thead>
                        <tbody>
                            @foreach($a->run_days as $s)
                                <tr><td><span class="gqs-pill gqs-pill-purple">Run Day</span></td><td>{{ $s->slot_date?->format('M j, Y') }}</td><td>{{ $s->cleanroom }}@if($s->start_time) · {{ \Illuminate\Support\Carbon::parse($s->start_time)->format('g:i A') }}@endif</td></tr>
                            @endforeach
                            @foreach($a->classes as $cs)
                                <tr><td><span class="gqs-pill gqs-pill-green">Class</span></td><td>{{ $cs->session_date?->format('M j, Y') }}</td><td>{{ $cs->trainingClass?->name }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @empty
        <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No Analysts Found. Assign the Record Runs or Manage Scheduling capability to staff.</div></div>
    @endforelse
</x-filament-panels::page>
