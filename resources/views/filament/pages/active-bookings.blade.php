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
                                <a href="{{ \App\Filament\Admin\Pages\ClassScheduler::getUrl() }}" class="ar-fix-btn" style="text-decoration:none;">
                                    <span>{{ $person['name'] }} <span style="opacity:.6;">{{ $person['employee_id'] }}</span></span><span class="ar-fix-go">Attendance &rarr;</span>
                                </a>
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
                                        <button type="button" wire:click="openBooking({{ $row['id'] }})" style="background:none;border:none;padding:0;cursor:pointer;color:var(--gqs-text,#1A1A1F);font-weight:600;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;">{{ $row['name'] }}</button>
                                    </td>
                                    <td>{{ $row['department'] ?: '-' }}</td>
                                    <td>{{ $row['class'] }}</td>
                                    <td style="white-space:nowrap;">{{ $row['session_date'] ?: '-' }}@if($row['session_past'] && in_array($row['status'], ['signed_up'])) <span class="gqs-pill gqs-pill-red" style="margin-left:4px;">Past</span>@endif</td>
                                    <td><span class="gqs-pill" style="background:{{ $row['status_color'] }}1A;color:{{ $row['status_color'] }};font-weight:700;">{{ $row['status_label'] }}</span></td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <div style="display:inline-flex;gap:4px;align-items:center;">
                                            <button type="button" wire:click="openBooking({{ $row['id'] }})" class="ab-iconbtn" title="View details"><x-filament::icon icon="heroicon-m-eye"/></button>
                                            @if($row['session_past'] && ! $row['submitted'])
                                                <a href="{{ \App\Filament\Admin\Pages\ClassScheduler::getUrl() }}" class="ab-iconbtn ab-iconbtn-go" title="Take attendance on the Class Scheduler"><x-filament::icon icon="heroicon-m-clipboard-document-check"/></a>
                                            @endif
                                            @if($this->isSuperUser())
                                                <button type="button" wire:click="deleteBooking({{ $row['id'] }})" wire:confirm="Permanently delete {{ addslashes($row['name']) }}'s enrollment record? Use this to fix stuck or duplicate entries. This cannot be undone." class="ab-iconbtn ab-iconbtn-danger" title="Super user: delete this enrollment"><x-filament::icon icon="heroicon-m-trash"/></button>
                                            @endif
                                        </div>
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

    {{-- Booking detail modal: class booking info + qualification snapshot --}}
    @if($bookingDetail)
        <div class="gqs-modal-overlay" wire:click.self="closeBooking">
            <div class="gqs-modal" style="width:660px;max-width:96vw;">
                <div style="background:linear-gradient(135deg,{{ $bookingDetail['status_color'] }},{{ $bookingDetail['status_color'] }}CC);padding:18px 20px;border-radius:14px 14px 0 0;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                    <div>
                        <div style="font-weight:800;font-size:19px;color:#fff;">{{ $bookingDetail['name'] }}</div>
                        <div style="font-size:12px;color:rgba(255,255,255,.92);">{{ $bookingDetail['employee_id'] }}@if($bookingDetail['job_title']) · {{ $bookingDetail['job_title'] }}@endif@if($bookingDetail['department']) · {{ $bookingDetail['department'] }}@endif</div>
                    </div>
                    <span style="background:rgba(255,255,255,.22);color:#fff;font-weight:700;font-size:12px;padding:5px 12px;border-radius:999px;white-space:nowrap;">{{ $bookingDetail['status_label'] }}</span>
                </div>
                <div class="gqs-modal-body">
                    <div class="dm-l">Class Booking</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:6px;">
                        <div><div class="dm-l">Class</div><div class="dm-v">{{ $bookingDetail['class'] }}</div></div>
                        <div><div class="dm-l">Session Date</div><div class="dm-v">{{ $bookingDetail['session_date'] ?: '-' }}@if($bookingDetail['session_past'] && in_array($bookingDetail['status'], ['signed_up'])) <span class="gqs-pill gqs-pill-red">Past</span>@endif</div></div>
                        <div><div class="dm-l">Time</div><div class="dm-v">{{ $bookingDetail['start_time'] ?: '-' }}{{ $bookingDetail['end_time'] ? ' - '.$bookingDetail['end_time'] : '' }}</div></div>
                        <div><div class="dm-l">Instructor</div><div class="dm-v">{{ $bookingDetail['instructor'] ?: '-' }}</div></div>
                        <div><div class="dm-l">Location</div><div class="dm-v">{{ $bookingDetail['location'] ?: '-' }}</div></div>
                        <div><div class="dm-l">Attendance</div><div class="dm-v">@if($bookingDetail['submitted'])<span class="gqs-pill gqs-pill-green">Submitted</span>@else<span class="gqs-pill gqs-pill-gray">Not Taken</span>@endif</div></div>
                        <div><div class="dm-l">Signed Up</div><div class="dm-v">{{ $bookingDetail['signed_up_at'] ?: '-' }}</div></div>
                        <div><div class="dm-l">Attended</div><div class="dm-v">{{ $bookingDetail['attended_at'] ?: '-' }}</div></div>
                    </div>

                    @if($bookingDetail['qual'])
                        <div class="dm-l" style="margin-top:18px;">Qualification Snapshot</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:6px;">
                            <div><div class="dm-l">Type</div><div class="dm-v">{{ $bookingDetail['qual']['type'] }}</div></div>
                            <div><div class="dm-l">Stage</div><div class="dm-v">{{ $bookingDetail['qual']['stage'] }}</div></div>
                            <div><div class="dm-l">Status</div><div class="dm-v">{{ $bookingDetail['qual']['status'] }}</div></div>
                            <div><div class="dm-l">{{ $bookingDetail['qual']['due_label'] }}</div><div class="dm-v" style="{{ $bookingDetail['qual']['past_due'] ? 'color:#C8102E;font-weight:700;' : '' }}">{{ $bookingDetail['qual']['due'] ?: '-' }} <span class="gqs-pill {{ $bookingDetail['qual']['due_tag'] === 'Lapsed' ? 'gqs-pill-red' : 'gqs-pill-gray' }}">{{ $bookingDetail['qual']['due_tag'] }}</span></div></div>
                            <div><div class="dm-l">Class On File</div><div class="dm-v">@if($bookingDetail['qual']['class_on_file'])<span class="gqs-pill gqs-pill-green">Yes</span>@else<span class="gqs-pill gqs-pill-gray">No</span>@endif</div></div>
                        </div>
                    @else
                        <div style="margin-top:16px;padding:11px 14px;border-radius:10px;background:var(--gqs-surface-2,#F1F1F4);font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);">No active qualification record for this person.</div>
                    @endif
                </div>
                <div class="gqs-modal-foot" style="justify-content:space-between;">
                    <span style="display:flex;gap:8px;">
                        <button wire:click="closeBooking" class="gqs-btn gqs-btn-ghost">Close</button>
                        @if($this->isSuperUser())
                            <button wire:click="deleteBooking({{ $bookingDetail['id'] }})" wire:confirm="Permanently delete this enrollment record? This cannot be undone." class="gqs-btn" style="background:#C8102E;color:#fff;">Delete Enrollment</button>
                        @endif
                    </span>
                    <span style="display:flex;gap:8px;">
                        @if($bookingDetail['session_past'] && ! $bookingDetail['submitted'])
                            <a href="{{ $bookingDetail['scheduler_url'] }}" class="gqs-btn" style="background:#C79A2E;color:#fff;text-decoration:none;">Take Attendance</a>
                        @endif
                        @if($bookingDetail['qual'])
                            <button type="button" wire:click="closeBooking" x-on:click="$dispatch('open-qual-modal', { id: {{ $bookingDetail['qual']['qid'] }} })" class="gqs-btn gqs-btn-primary">Open Qualification</button>
                        @endif
                    </span>
                </div>
            </div>
        </div>
    @endif

    <style>
        .ab-iconbtn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:1px solid var(--gqs-border,#E2E2E8);background:var(--gqs-surface-2,#F4F4F6);color:var(--gqs-text-dim,#5A5A62);cursor:pointer;}
        .ab-iconbtn svg{width:16px;height:16px;}
        .ab-iconbtn:hover{background:#E7E7EC;color:var(--gqs-text,#1A1A1F);}
        .ab-iconbtn-go{background:#1F6FB2;border-color:#1F6FB2;color:#fff;text-decoration:none;}
        .ab-iconbtn-go:hover{background:#185A92;color:#fff;}
        .ab-iconbtn-danger:hover{background:#FCEEF0;border-color:#F2B8C0;color:#C8102E;}
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
