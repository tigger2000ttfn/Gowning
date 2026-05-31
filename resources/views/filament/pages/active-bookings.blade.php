<x-filament-panels::page>
    @php $stats = $this->stats(); $gaps = $this->gaps(); $totalGaps = $gaps['past_sessions']['count'] + $gaps['stale_signups']['count']; @endphp

    @include('filament.page-hero', ['title' => 'Active Bookings', 'icon' => 'heroicon-o-user-group', 'actions' => '
        <button type="button" wire:click="setTab(\'roster\')" class="gqs-tab ' . ($tab === 'roster' ? 'active' : '') . '">Roster</button>
        <button type="button" wire:click="setTab(\'dashboard\')" class="gqs-tab ' . ($tab === 'dashboard' ? 'active' : '') . '">Dashboard</button>
    '])

    <div class="gqs-stats">
        <div class="gqs-stat charcoal"><div class="n">{{ $stats['total'] }}</div><div class="l">Active Bookings</div><span class="wm"><x-filament::icon icon="heroicon-o-user-group"/></span></div>
        <div class="gqs-stat blue"><div class="n">{{ $stats['scheduled'] }}</div><div class="l">Scheduled</div><span class="wm"><x-filament::icon icon="heroicon-o-calendar"/></span></div>
        <div class="gqs-stat gold"><div class="n">{{ $stats['attended'] }}</div><div class="l">Attended</div><span class="wm"><x-filament::icon icon="heroicon-o-check"/></span></div>
        <div class="gqs-stat royal"><div class="n">{{ $stats['qcm'] }}</div><div class="l">QCM Reviewed</div><span class="wm"><x-filament::icon icon="heroicon-o-shield-check"/></span></div>
        <div class="gqs-stat purple"><div class="n">{{ $stats['pending_qa'] }}</div><div class="l">Pending QA</div><span class="wm"><x-filament::icon icon="heroicon-o-clock"/></span></div>
    </div>

    @if($tab === 'roster')
        @if($totalGaps > 0)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-bottom:18px;">
                @if($gaps['past_sessions']['count'] > 0)
                    <div class="ar-fix" style="--fix:#C8102E;">
                        <div class="ar-fix-h"><x-filament::icon icon="heroicon-m-calendar-days"/> <span class="ar-fix-n">{{ $gaps['past_sessions']['count'] }}</span> Attendance Not Taken</div>
                        <div class="ar-fix-sub">These sessions are past their date but attendance was never submitted. Take attendance on the Class Scheduler.</div>
                        <div class="ar-fix-list">
                            @foreach($gaps['past_sessions']['items'] as $item)
                                <a href="{{ \App\Filament\Admin\Pages\ClassScheduler::getUrl() }}" class="ar-fix-btn" style="text-decoration:none;">
                                    <span>{{ $item['label'] }}</span><span class="ar-fix-go">Take &rarr;</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if($gaps['stale_signups']['count'] > 0)
                    <div class="ar-fix" style="--fix:#C79A2E;">
                        <div class="ar-fix-h"><x-filament::icon icon="heroicon-m-exclamation-circle"/> <span class="ar-fix-n">{{ $gaps['stale_signups']['count'] }}</span> Past Session, Still Scheduled</div>
                        <div class="ar-fix-sub">Trainees still marked Scheduled for a session that has already passed. Take attendance or reschedule them.</div>
                        <div class="ar-fix-list">
                            @foreach($gaps['stale_signups']['people'] as $person)
                                <button type="button" wire:click="showPersonDetail({{ $person['id'] }})" class="ar-fix-btn">
                                    <span>{{ $person['name'] }} <span style="opacity:.6;">{{ $person['employee_id'] }}</span></span><span class="ar-fix-go">View &rarr;</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div class="gqs-panel">
            <div class="gqs-panel-head" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <x-filament::icon icon="heroicon-m-user-group"/> People In An Active Class
                <span style="margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name or ID" class="gqs-fld" style="width:auto;min-width:170px;padding:5px 10px;">
                    <select wire:model.live="filterStatus" class="gqs-fld" style="width:auto;min-width:150px;padding:5px 10px;">
                        @foreach($this->statusOptions() as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach
                    </select>
                </span>
            </div>
            <div class="gqs-panel-body">
                @php $rows = $this->rows(); @endphp
                @if(empty($rows))
                    <div class="gqs-empty">No active class bookings match. People appear here from sign-up through QA approval, then move to Class Completions.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Employee</th><th>Name</th><th>Department</th><th>Class</th><th>Session Date</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr wire:key="ab-{{ $row['id'] }}-{{ $row['status'] }}">
                                    <td style="font-weight:600;">{{ $row['employee_id'] ?: '-' }}</td>
                                    <td>
                                        @if($row['personnel_id'])
                                            <button type="button" wire:click="showPersonDetail({{ $row['personnel_id'] }})" style="background:none;border:none;padding:0;cursor:pointer;color:var(--gqs-text,#1A1A1F);font-weight:600;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;">{{ $row['name'] }}</button>
                                        @else {{ $row['name'] }} @endif
                                    </td>
                                    <td>{{ $row['department'] ?: '-' }}</td>
                                    <td>{{ $row['class'] }}</td>
                                    <td style="white-space:nowrap;">{{ $row['session_date'] ?: '-' }}@if($row['session_past'] && in_array($row['status'], ['signed_up'])) <span class="gqs-pill gqs-pill-red" style="margin-left:4px;">Past</span>@endif</td>
                                    <td><span class="gqs-pill" style="background:{{ $row['status_color'] }}1A;color:{{ $row['status_color'] }};font-weight:700;">{{ $row['status_label'] }}</span></td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <a href="{{ \App\Filament\Admin\Pages\ClassScheduler::getUrl() }}" class="sb-act" style="background:#A4123F;text-decoration:none;">Scheduler</a>
                                        @if($row['personnel_id'])
                                            <button type="button" wire:click="showPersonDetail({{ $row['personnel_id'] }})" class="sb-act" style="background:#1C1C21;">Details</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @else
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-chart-bar"/> Class Pipeline By Stage</div>
            <div class="gqs-panel-body" style="padding:18px;">
                @php $funnel = $this->stageFunnel(); $maxF = max(1, collect($funnel)->max('count')); @endphp
                <div class="ar-funnel">
                    @foreach($funnel as $f)
                        <div class="ar-fcell">
                            <div class="ar-fbar-wrap">
                                <div class="ar-fbar" style="height:{{ $f['count'] > 0 ? max(8, round($f['count'] / $maxF * 100)) : 3 }}%;background:{{ $f['color'] }};"></div>
                            </div>
                            <div class="ar-fnum" style="color:{{ $f['count'] > 0 ? $f['color'] : 'var(--gqs-text-dim,#9A9AA4)' }};">{{ $f['count'] }}</div>
                            <div class="ar-flbl">{{ $f['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:16px;">
            <div class="gqs-panel"><div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-calendar-days"/> Attendance Not Taken</div><div class="gqs-panel-body" style="padding:22px 18px;text-align:center;"><div style="font-size:42px;font-weight:800;color:{{ $gaps['past_sessions']['count'] > 0 ? '#C8102E' : '#2E7D5B' }};line-height:1;">{{ $gaps['past_sessions']['count'] }}</div><div style="font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);margin-top:7px;">past sessions awaiting attendance</div></div></div>
            <div class="gqs-panel"><div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-clock"/> Pending QA</div><div class="gqs-panel-body" style="padding:22px 18px;text-align:center;"><div style="font-size:42px;font-weight:800;color:#6B2C91;line-height:1;">{{ $stats['pending_qa'] }}</div><div style="font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);margin-top:7px;">awaiting QA approval</div></div></div>
            <div class="gqs-panel"><div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-wrench-screwdriver"/> Needs Attention</div><div class="gqs-panel-body" style="padding:22px 18px;text-align:center;"><div style="font-size:42px;font-weight:800;color:{{ $totalGaps > 0 ? '#C8102E' : '#2E7D5B' }};line-height:1;">{{ $totalGaps }}</div><div style="font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);margin-top:7px;">items need attention</div></div></div>
        </div>
    @endif

    @if($personDetail)
        <div class="gqs-modal-overlay" wire:click.self="closePersonDetail">
            <div class="gqs-modal" style="width:640px;max-width:96vw;">
                <div style="background:linear-gradient(135deg,#1C1C21,#34343D);padding:16px 20px;border-radius:14px 14px 0 0;">
                    <div style="font-weight:800;font-size:18px;color:#fff;">{{ $personDetail['name'] }}</div>
                    <div style="font-size:12px;color:rgba(255,255,255,.9);">{{ $personDetail['employee_id'] }}@if($personDetail['job_title']) · {{ $personDetail['job_title'] }}@endif</div>
                </div>
                <div class="gqs-modal-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div><div class="dm-l">Department</div><div class="dm-v">{{ $personDetail['department'] ?: '-' }}</div></div>
                        <div><div class="dm-l">Stage</div><div class="dm-v">{{ $personDetail['stage'] ?: '-' }}</div></div>
                        <div><div class="dm-l">Status</div><div class="dm-v">{{ $personDetail['status'] ?: '-' }}</div></div>
                        <div><div class="dm-l">Session Type</div><div class="dm-v">{{ $personDetail['type'] ?: '-' }}</div></div>
                        <div><div class="dm-l">Due Date</div><div class="dm-v">{{ $personDetail['due'] ?: '-' }}</div></div>
                        <div><div class="dm-l">Class Completed</div><div class="dm-v">{{ $personDetail['class_date'] ?: '-' }}</div></div>
                        <div><div class="dm-l">Class On File</div><div class="dm-v">@if($personDetail['class_on_file'])<span class="gqs-pill gqs-pill-green">Yes</span>@else<span class="gqs-pill gqs-pill-gray">No</span>@endif</div></div>
                    </div>
                    @if(count($personDetail['enrollments']))
                        <div class="dm-l" style="margin-top:18px;">Class Enrollments</div>
                        <table class="gqs-tbl" style="margin-top:6px;">
                            <thead><tr><th>Class</th><th>Date</th><th>Status</th></tr></thead>
                            <tbody>@foreach($personDetail['enrollments'] as $e)
                                <tr><td>{{ $e['class'] }}</td><td>{{ $e['date'] ?: '-' }}</td><td>{{ $e['status'] }}</td></tr>
                            @endforeach</tbody>
                        </table>
                    @endif
                </div>
                <div class="gqs-modal-foot" style="justify-content:flex-end;">
                    <button wire:click="closePersonDetail" class="gqs-btn gqs-btn-ghost">Close</button>
                    <a href="{{ $personDetail['view_url'] }}" class="gqs-btn gqs-btn-primary" style="text-decoration:none;">Open Record</a>
                </div>
            </div>
        </div>
    @endif

    <style>
        .ar-fix{background:var(--gqs-surface,#fff);border:1px solid var(--gqs-border,#E2E2E8);border-top:3px solid var(--fix);border-radius:12px;padding:14px 16px;}
        .ar-fix-h{display:flex;align-items:center;gap:7px;font-size:13.5px;font-weight:800;color:var(--gqs-text,#1A1A1F);}
        .ar-fix-h svg{width:18px;height:18px;color:var(--fix);}
        .ar-fix-n{color:var(--fix);font-size:18px;}
        .ar-fix-sub{font-size:11.5px;color:var(--gqs-text-dim,#6A6A72);margin:5px 0 10px;line-height:1.4;}
        .ar-fix-list{display:flex;flex-direction:column;gap:5px;max-height:230px;overflow-y:auto;}
        .ar-fix-btn{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:12px;font-weight:600;padding:7px 11px;border-radius:8px;border:1px solid var(--gqs-border,#E2E2E8);background:var(--gqs-surface-2,#F8F8FA);color:var(--gqs-text,#1A1A1F);cursor:pointer;text-align:left;width:100%;}
        .ar-fix-btn:hover{border-color:var(--fix);}
        .ar-fix-go{color:var(--fix);font-weight:800;white-space:nowrap;}
        .ar-funnel{display:flex;align-items:flex-end;gap:10px;min-height:190px;}
        .ar-fcell{flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;min-width:0;}
        .ar-fbar-wrap{width:100%;height:140px;display:flex;align-items:flex-end;justify-content:center;}
        .ar-fbar{width:70%;max-width:48px;border-radius:7px 7px 3px 3px;transition:height .3s ease;min-height:3px;}
        .ar-fnum{font-size:19px;font-weight:800;line-height:1;}
        .ar-flbl{font-size:10.5px;font-weight:600;color:var(--gqs-text-dim,#6A6A72);text-align:center;line-height:1.2;}
    </style>
</x-filament-panels::page>
