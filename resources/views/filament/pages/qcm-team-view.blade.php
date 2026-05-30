<x-filament-panels::page>
    @php
        $analysts = $this->getAnalysts();
        $unassigned = $this->getUnassignedRunDays();
        $manager = $this->manager();
        $tabActions = '';
        foreach (['overview' => 'Overview', 'table' => 'Table', 'cards' => 'Cards', 'calendar' => 'Calendar'] as $k => $lbl) {
            $tabActions .= '<button type="button" wire:click="$set(\'tab\', \'' . $k . '\')" class="gqs-tab ' . ($tab === $k ? 'active' : '') . '">' . $lbl . '</button>';
        }
    @endphp

    @include('filament.page-hero', ['title' => 'QC Micro Team & Assignments', 'icon' => 'heroicon-o-user-group', 'actions' => $tabActions])

    {{-- Manager + summary stats --}}
    <div class="gqs-stats">
        <div class="gqs-stat charcoal"><div class="n" style="font-size:18px;">{{ $manager?->name ?? 'Unassigned' }}</div><div class="l">Team Manager</div><span class="wm"><x-filament::icon icon="heroicon-o-user-circle"/></span></div>
        <div class="gqs-stat magenta"><div class="n">{{ $analysts->count() }}</div><div class="l">Analysts</div><span class="wm"><x-filament::icon icon="heroicon-o-users"/></span></div>
        <div class="gqs-stat gold"><div class="n">{{ $unassigned->count() }}</div><div class="l">Unassigned Run Days</div><span class="wm"><x-filament::icon icon="heroicon-o-exclamation-triangle"/></span></div>
        <div class="gqs-stat purple"><div class="n">{{ $analysts->sum('load') }}</div><div class="l">Total Assignments</div><span class="wm"><x-filament::icon icon="heroicon-o-clipboard-document-list"/></span></div>
    </div>

    {{-- Unassigned alert (all tabs) --}}
    @if($unassigned->isNotEmpty())
        <div class="gqs-panel">
            <div class="gqs-panel-head" style="background:linear-gradient(135deg,#C79A2E,#9E7818);"><x-filament::icon icon="heroicon-m-exclamation-triangle"/> Unassigned Run Days</div>
            <div class="gqs-panel-body">
                @foreach($unassigned as $s)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);font-size:13.5px;">
                        <span>{{ $s->slot_date?->format('l, d M') }} · {{ $s->cleanroom }}</span>
                        <button wire:click="openAssign({{ $s->id }})" class="gqs-mini-btn">Assign</button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- OVERVIEW: cards + per-analyst tables --}}
    @if($tab === 'overview' || $tab === 'cards')
        @forelse($analysts as $a)
            <div class="gqs-panel">
                <div class="gqs-panel-head" style="justify-content:space-between;">
                    <span style="display:flex;align-items:center;gap:9px;"><x-filament::icon icon="heroicon-m-user"/> {{ $a->name }}@if($a->is_manager)<span class="gqs-pill gqs-pill-purple" style="margin-left:6px;">Manager</span>@endif</span>
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
                                    <tr><td><span class="gqs-pill gqs-pill-purple">Run Day</span></td><td>{{ $s->slot_date?->gmp() }}</td><td>{{ $s->cleanroom }}@if($s->start_time) · {{ \Illuminate\Support\Carbon::parse($s->start_time)->format('H:i') }}@endif</td></tr>
                                @endforeach
                                @foreach($a->classes as $cs)
                                    <tr><td><span class="gqs-pill gqs-pill-green">Class</span></td><td>{{ $cs->session_date?->gmp() }}</td><td>{{ $cs->trainingClass?->name }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @empty
            <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No QCM team members. Set a person's Team to "QC Micro" on their user record.</div></div>
        @endforelse
    @endif

    {{-- TABLE: one workload row per analyst --}}
    @if($tab === 'table')
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-table-cells"/> Analyst Workload</div>
            <div class="gqs-panel-body">
                <table class="gqs-tbl">
                    <thead><tr><th>Analyst</th><th>Run Days</th><th>Classes</th><th>Total Load</th></tr></thead>
                    <tbody>
                        @forelse($analysts as $a)
                            <tr>
                                <td style="font-weight:600;">{{ $a->name }}@if($a->is_manager)<span class="gqs-pill gqs-pill-purple" style="margin-left:6px;">Mgr</span>@endif</td>
                                <td>{{ $a->run_days->count() }}</td>
                                <td>{{ $a->classes->count() }}</td>
                                <td><span class="gqs-pill {{ $a->load > 5 ? 'gqs-pill-red' : 'gqs-pill-green' }}">{{ $a->load }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="gqs-empty">No team members.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- CALENDAR: upcoming run days w/ assigned analyst --}}
    @if($tab === 'calendar')
        @php $cal = $this->getCalendar(); @endphp
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-calendar-days"/> Next 6 Weeks</div>
            <div class="gqs-panel-body">
                @forelse($cal as $day)
                    <div style="padding:10px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);">
                        <div style="font-weight:700;font-size:13px;color:var(--gqs-text,#1A1A1F);margin-bottom:5px;">{{ $day['date'] }}</div>
                        @foreach($day['rows'] as $r)
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:12.5px;">
                                <span>{{ $r['cleanroom'] }}@if($r['time']) · {{ $r['time'] }}@endif</span>
                                <span style="display:flex;align-items:center;gap:8px;">
                                    @if($r['analyst'])<span class="gqs-pill gqs-pill-green">{{ $r['analyst'] }}</span>@else<span class="gqs-pill gqs-pill-gold">Unassigned</span>@endif
                                    <button wire:click="openAssign({{ $r['slot_id'] }})" class="gqs-mini-btn">Assign</button>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <div class="gqs-empty">No upcoming run days.</div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Unassigned classes alert --}}
    @php $unClasses = $this->getUnassignedClasses(); @endphp
    @if($unClasses->isNotEmpty())
        <div class="gqs-panel">
            <div class="gqs-panel-head" style="background:linear-gradient(135deg,#6B2C91,#4E2069);"><x-filament::icon icon="heroicon-m-academic-cap"/> Classes Without An Instructor</div>
            <div class="gqs-panel-body">
                @foreach($unClasses as $cs)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 16px;border-bottom:1px solid var(--gqs-border,#F2F2F4);font-size:13.5px;">
                        <span>{{ $cs->session_date?->format('l, d M') }} · {{ $cs->trainingClass?->name }}</span>
                        <button wire:click="openAssignInstructor({{ $cs->id }})" class="gqs-mini-btn">Assign Instructor</button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Assign instructor modal --}}
    @if($showAssignInstructor)
        <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="$set('showAssignInstructor', false)">
            <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:400px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <div style="background:#1C1C21;color:#fff;padding:16px 20px;border-radius:14px 14px 0 0;font-weight:800;font-size:16px;">Assign Instructor</div>
                <div style="padding:18px 20px;">
                    <label class="gqs-flbl">Instructor</label>
                    <select wire:model="assignInstructorId" class="gqs-fld">
                        <option value="">Unassigned</option>
                        @foreach($this->analystOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                        <button type="button" wire:click="$set('showAssignInstructor', false)" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Cancel</button>
                        <button type="button" wire:click="saveAssignInstructor" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;border:none;font-weight:700;cursor:pointer;">Save</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Assign analyst modal --}}
    @if($showAssign)
        <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="$set('showAssign', false)">
            <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:400px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <div style="background:#1C1C21;color:#fff;padding:16px 20px;border-radius:14px 14px 0 0;font-weight:800;font-size:16px;">Assign Analyst</div>
                <div style="padding:18px 20px;">
                    <label class="gqs-flbl">Analyst</label>
                    <select wire:model="assignAnalystId" class="gqs-fld">
                        <option value="">Unassigned</option>
                        @foreach($this->analystOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                        <button type="button" wire:click="$set('showAssign', false)" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Cancel</button>
                        <button type="button" wire:click="saveAssign" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;border:none;font-weight:700;cursor:pointer;">Save</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <style>
        .gqs-tabs{display:flex;gap:4px;margin-bottom:16px;border-bottom:2px solid var(--gqs-border,#E2E2E6);}
        .gqs-tab{background:none;border:none;padding:9px 16px;font-size:13.5px;font-weight:600;color:var(--gqs-text-dim,#6A6A72);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;}
        .gqs-tab.on{color:#A4123F;border-bottom-color:#A4123F;}
        .gqs-mini-btn{font-size:11.5px;font-weight:700;padding:4px 11px;border-radius:6px;border:none;background:#A4123F;color:#fff;cursor:pointer;}
        .gqs-mini-btn:hover{background:#85102F;}
    </style>
</x-filament-panels::page>
