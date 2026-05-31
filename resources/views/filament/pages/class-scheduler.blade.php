<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Class Scheduler', 'icon' => 'heroicon-o-academic-cap', 'actions' => '
        <button type="button" wire:click="$set(\'tab\',\'overview\')" class="gqs-tab ' . ($tab==='overview' ? 'active' : '') . '">Overview</button>
        <button type="button" wire:click="$set(\'tab\',\'classes\')" class="gqs-tab ' . ($tab==='classes' ? 'active' : '') . '">Class Templates</button>
        <button type="button" wire:click="$set(\'tab\',\'sessions\')" class="gqs-tab ' . ($tab==='sessions' ? 'active' : '') . '">Classes</button>
        <button type="button" wire:click="$set(\'tab\',\'attendance\')" class="gqs-tab ' . ($tab==='attendance' ? 'active' : '') . '">Attendance</button>
    '])

    @if($tab === 'overview')
        @php $stats = $this->overviewStats(); $need = $this->needingClass(); @endphp
        <div class="gqs-stats">
            @foreach($stats as [$label, $value, $icon, $color])
                <div class="gqs-stat {{ $color }}">
                    <div class="n">{{ $value }}</div>
                    <div class="l">{{ $label }}</div>
                    <span class="wm"><x-filament::icon :icon="$icon"/></span>
                </div>
            @endforeach
        </div>
        <div class="gqs-panel" style="margin-top:16px;">
            <div class="gqs-panel-head"><span style="display:flex;align-items:center;gap:9px;"><x-filament::icon icon="heroicon-m-exclamation-circle"/> Need The Gowning Class</span></div>
            <div class="gqs-panel-body" style="padding:0;">
                @if(empty($need))
                    <div class="gqs-empty" style="padding:28px;">Nobody is waiting. Everyone who needs the class is signed up.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Employee ID</th><th>Name</th><th>Department</th><th>Waiting</th><th style="text-align:right;">Action</th></tr></thead>
                        <tbody>
                            @foreach($need as $n)
                                <tr>
                                    <td style="font-weight:600;">{{ $n['employee_id'] }}</td>
                                    <td>{{ $n['name'] }}</td>
                                    <td>{{ $n['department'] ?: '—' }}</td>
                                    <td style="color:var(--gqs-text-dim,#6A6A72);">{{ $n['since'] ?? '—' }}</td>
                                    <td style="text-align:right;">
                                        <button type="button" wire:click="openSchedule({{ $n['personnel_id'] }})" class="rd-act rd-act-magenta">Schedule</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Schedule a waiting person into a class session --}}
        @if($showSchedule)
            <div class="gqs-modal-overlay" wire:click.self="$set('showSchedule', false)">
                <div class="gqs-modal">
                    <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-calendar-days"/></span>Schedule Gowning Class</div>
                    <div class="gqs-modal-body">
                        <p style="font-size:13px;color:var(--gqs-text-dim,#6A6A72);margin:0;">{{ $scheduleName }}</p>
                        <div>
                            <label class="gqs-flbl">Class Date</label>
                            <select wire:model="scheduleSessionId" class="gqs-fld">
                                <option value="">Select A Class Date...</option>
                                @foreach($this->openSessionOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;color:var(--gqs-text-dim,#9A9AA2);font-size:11.5px;font-weight:600;">
                            <span style="flex:1;height:1px;background:var(--gqs-border,#E2E2E6);"></span> OR <span style="flex:1;height:1px;background:var(--gqs-border,#E2E2E6);"></span>
                        </div>
                        <button type="button" wire:click="scheduleNextAvailable" class="gqs-btn gqs-btn-ghost" style="width:100%;justify-content:center;">Book Next Available</button>
                    </div>
                    <div class="gqs-modal-foot">
                        <button type="button" wire:click="$set('showSchedule', false)" class="gqs-btn gqs-btn-ghost">Cancel</button>
                        <button type="button" wire:click="saveSchedule" class="gqs-btn gqs-btn-primary">Schedule Selected Date</button>
                    </div>
                </div>
            </div>
        @endif

    @elseif($tab === 'classes')
        <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
            <button type="button" wire:click="$set('showAddClass', true)"
                    style="display:inline-flex;align-items:center;gap:7px;padding:9px 15px;background:#A4123F;color:#fff;border:none;border-radius:9px;font-weight:700;font-size:13px;cursor:pointer;">
                <x-filament::icon icon="heroicon-m-plus" style="width:16px;height:16px;"/> New Class Template
            </button>
        </div>
        <div class="gqs-panel">
            <div class="gqs-panel-body" style="padding:0;">
                @php $tpls = $this->templates(); @endphp
                @if($tpls->isEmpty())
                    <div class="gqs-empty" style="padding:28px;">No class templates yet. Create one, then generate classes from it.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Class Template</th><th>Code</th><th>Default Capacity</th><th>Validity</th><th>Prerequisite</th><th>Classes</th><th></th></tr></thead>
                        <tbody>
                            @foreach($tpls as $t)
                                <tr>
                                    <td style="font-weight:700;">{{ $t->name }}</td>
                                    <td>{{ $t->code ?: '—' }}</td>
                                    <td>{{ $t->default_capacity ?: '—' }}</td>
                                    <td>{{ $t->validity_months ? $t->validity_months . ' mo' : '—' }}</td>
                                    <td>@if($t->is_gowning_prerequisite)<span class="gqs-pill gqs-pill-green">Gowning Prereq</span>@else<span class="gqs-pill">No</span>@endif</td>
                                    <td>{{ $t->sessions_count }}</td>
                                    <td style="text-align:right;">
                                        <button wire:click="$set('sessClassId', {{ $t->id }}); $set('tab','sessions'); $set('showAddSession', true)" class="rd-act rd-act-magenta">Generate Classes</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        @if($showAddClass)
            <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="$set('showAddClass', false)">
                <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:460px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                    <div style="background:#1C1C21;color:#fff;padding:16px 20px;border-radius:14px 14px 0 0;font-weight:800;font-size:16px;">New Class Template</div>
                    <div style="padding:18px 20px;">
                        <div style="margin-bottom:12px;"><label class="gqs-flbl">Class Name</label><input type="text" wire:model="clsName" class="gqs-fld"></div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div><label class="gqs-flbl">Code</label><input type="text" wire:model="clsCode" placeholder="Optional" class="gqs-fld"></div>
                            <div><label class="gqs-flbl">Default Location</label><input type="text" wire:model="clsLocation" placeholder="Optional" class="gqs-fld"></div>
                            <div><label class="gqs-flbl">Default Capacity</label><input type="number" min="1" wire:model="clsCapacity" class="gqs-fld"></div>
                            <div><label class="gqs-flbl">Validity (months)</label><input type="number" min="1" wire:model="clsValidity" class="gqs-fld"></div>
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:13px;font-weight:600;cursor:pointer;color:var(--gqs-text,#1A1A1F);">
                            <input type="checkbox" wire:model="clsPrereq"> Gowning prerequisite (required before initial runs)
                        </label>
                        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                            <button type="button" wire:click="$set('showAddClass', false)" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Cancel</button>
                            <button type="button" wire:click="addClass" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;border:none;font-weight:700;cursor:pointer;">Create</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    @elseif($tab === 'sessions')
        {{-- SESSIONS SETUP (manage the dated sessions; take attendance on the Attendance tab) --}}
        @php $sessions = $this->sessions(); @endphp
        <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
            <button type="button" wire:click="$set('showAddSession', true)"
                    style="display:inline-flex;align-items:center;gap:7px;padding:9px 15px;background:#A4123F;color:#fff;border:none;border-radius:9px;font-weight:700;font-size:13px;cursor:pointer;">
                <x-filament::icon icon="heroicon-m-plus" style="width:16px;height:16px;"/> Add / Generate Sessions
            </button>
        </div>
        @if($sessions->isEmpty())
            <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No upcoming classes. Generate some from a class template.</div></div>
        @else
            <div class="gqs-panel">
                <div class="gqs-panel-body" style="padding:0;">
                    <table class="gqs-tbl">
                        <thead><tr><th>Date</th><th>Time</th><th>Class Template</th><th>Location</th><th>Instructor</th><th>Booked / Cap</th><th>Status</th><th style="text-align:right;">Manage</th></tr></thead>
                        <tbody>
                            @foreach($sessions as $s)
                                <tr style="cursor:pointer;" wire:click="openSessionDetail({{ $s->id }})">
                                    <td style="font-weight:700;">{{ strtoupper($s->session_date->format('D')) }} · {{ $s->session_date->gmp() }}@if($s->session_uid)<div style="font-size:11px;font-weight:600;color:#A4123F;">{{ $s->session_uid }}</div>@endif</td>
                                    <td>{{ $s->start_time ? \Illuminate\Support\Carbon::parse($s->start_time)->format('H:i') : '—' }}@if($s->end_time) – {{ \Illuminate\Support\Carbon::parse($s->end_time)->format('H:i') }}@endif</td>
                                    <td>{{ $s->trainingClass?->name }}</td>
                                    <td>{{ $s->location ?: '—' }}</td>
                                    <td>{{ $s->instructorUser?->name ?? $s->instructor ?? 'Unassigned' }}</td>
                                    <td><span class="gqs-pill {{ $s->seats_left > 0 ? 'gqs-pill-green' : 'gqs-pill-gold' }}">{{ $s->booked }} / {{ $s->capacity }}</span></td>
                                    <td>@if($s->attendance_submitted_at)<span class="gqs-pill gqs-pill-green">Submitted</span>@else<span class="gqs-pill gqs-pill-purple">Open</span>@endif</td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <button type="button" wire:click.stop="openSessionDetail({{ $s->id }})" class="rd-act rd-act-magenta">Details</button>
                                        <a href="{{ \App\Filament\Admin\Pages\ClassScheduler::getUrl() }}?attend={{ $s->id }}" wire:click.stop class="rd-act rd-act-green" style="text-decoration:none;">Attendance</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($showAddSession)
            <div class="gqs-modal-overlay" wire:click.self="$set('showAddSession', false)">
                <div class="gqs-modal" style="width:500px;">
                    <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-plus"/></span>Add / Generate Classes</div>
                    <div class="gqs-modal-body">
                        <div><label class="gqs-flbl">Class Template</label>
                            <select wire:model="sessClassId" class="gqs-fld"><option value="">Select a class template...</option>
                                @foreach($this->classOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                            </select></div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div><label class="gqs-flbl">First Date</label>@include('filament.partials.fp-date',['model'=>'sessDate'])</div>
                            <div><label class="gqs-flbl">Location</label>
                                <select wire:model="sessLocation" class="gqs-fld">
                                    <option value="">Template Default</option>
                                    @foreach($this->roomLocationOptions() as $loc)<option value="{{ $loc }}">{{ $loc }}</option>@endforeach
                                </select></div>
                            <div><label class="gqs-flbl">Start</label>@include('filament.partials.fp-date',['model'=>'sessStart','isTime'=>true])</div>
                            <div><label class="gqs-flbl">End</label>@include('filament.partials.fp-date',['model'=>'sessEnd','isTime'=>true])</div>
                            <div><label class="gqs-flbl">Capacity</label><input type="number" min="1" wire:model="sessCapacity" placeholder="default" class="gqs-fld"></div>
                            <div><label class="gqs-flbl">Instructor</label>
                                <select wire:model="sessInstructorId" class="gqs-fld"><option value="">Unassigned</option>
                                    @foreach($this->instructorOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                </select></div>
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;cursor:pointer;color:var(--gqs-text,#1A1A1F);">
                            <input type="checkbox" wire:model.live="sessRepeat"> Repeat this session
                        </label>
                        @if($sessRepeat)
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:12px;background:var(--gqs-surface-2,#F5F5F7);border-radius:9px;">
                                <div><label class="gqs-flbl">Pattern</label>
                                    <select wire:model="sessPattern" class="gqs-fld">
                                        <option value="weekly">Weekly</option>
                                        <option value="biweekly">Every 2 Weeks</option>
                                        <option value="monthly">Monthly</option>
                                    </select></div>
                                <div><label class="gqs-flbl">Repeat Until</label>@include('filament.partials.fp-date',['model'=>'sessUntil'])</div>
                            </div>
                        @endif
                    </div>
                    <div class="gqs-modal-foot">
                        <button type="button" wire:click="$set('showAddSession', false)" class="gqs-btn gqs-btn-ghost">Cancel</button>
                        <button type="button" wire:click="addSession" class="gqs-btn gqs-btn-primary">@if($sessRepeat)Generate @else Add Session @endif</button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Session detail / reschedule modal --}}
        @if($detailSessionId)
            @php $ds = \App\Models\ClassSession::with('trainingClass')->find($detailSessionId); $dlocked = (bool) $ds?->attendance_submitted_at; @endphp
            @if($ds)
                <div class="gqs-modal-overlay" wire:click.self="closeSessionDetail">
                    <div class="gqs-modal" style="width:520px;">
                        <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-academic-cap"/></span>{{ $ds->trainingClass?->name }} · Session</div>
                        <div class="gqs-modal-body">
                            @if($dlocked)
                                <div style="padding:10px 12px;background:#E9F7EF;border:1px solid #BFE6CE;border-radius:8px;font-size:12.5px;color:#1C5E3A;">Attendance Submitted · {{ count($this->sessionAttendees($detailSessionId)) }} Enrolled. This Session Is Locked From Editing.</div>
                            @else
                                @php $enrolled = $this->sessionAttendees($detailSessionId); @endphp
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                    <div><label class="gqs-flbl">Date</label>@include('filament.partials.fp-date',['model'=>'editSession.session_date'])</div>
                                    <div><label class="gqs-flbl">Location</label>
                                        <select wire:model="editSession.location" class="gqs-fld">
                                            <option value="">None</option>
                                            @foreach($this->roomLocationOptions() as $loc)<option value="{{ $loc }}">{{ $loc }}</option>@endforeach
                                        </select></div>
                                    <div><label class="gqs-flbl">Start</label>@include('filament.partials.fp-date',['model'=>'editSession.start_time','isTime'=>true])</div>
                                    <div><label class="gqs-flbl">End</label>@include('filament.partials.fp-date',['model'=>'editSession.end_time','isTime'=>true])</div>
                                    <div><label class="gqs-flbl">Capacity</label><input type="number" min="1" wire:model="editSession.capacity" class="gqs-fld"></div>
                                    <div><label class="gqs-flbl">Instructor</label>
                                        <select wire:model="editSession.assigned_instructor_id" class="gqs-fld"><option value="">Unassigned</option>
                                            @foreach($this->instructorOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                        </select></div>
                                </div>
                                <div style="margin-top:4px;padding:10px 12px;background:var(--gqs-surface-2,#F4F4F7);border-radius:8px;">
                                    <div style="font-size:12px;font-weight:700;color:var(--gqs-text,#1A1A1F);margin-bottom:4px;">{{ count($enrolled) }} Enrolled · Rescheduling This Session Moves Them All To The New Date</div>
                                    @if(count($enrolled))
                                        <div style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">{{ collect($enrolled)->pluck('name')->implode(', ') }}</div>
                                    @else
                                        <div style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">No one enrolled yet.</div>
                                    @endif
                                </div>
                            @endif
                            <div style="display:flex;gap:8px;flex-wrap:wrap;padding-top:4px;">
                                <a href="{{ route('print.class-attendance', [$ds->id, 'FORM-AST-36513-' . ($ds->session_uid ?: 'Class') . '.pdf']) }}@if($ds->instructorUser)?trainer={{ urlencode($ds->instructorUser->name) }}@endif" target="_blank" class="gqs-btn gqs-btn-ghost" style="text-decoration:none;">Print Attendance Form</a>
                                <a href="{{ \App\Filament\Admin\Pages\ClassScheduler::getUrl() }}?attend={{ $ds->id }}" class="gqs-btn gqs-btn-ghost" style="text-decoration:none;">Take Attendance</a>
                            </div>
                        </div>
                        <div class="gqs-modal-foot" style="justify-content:space-between;">
                            <button type="button" wire:click="askConfirm('cancelSession', {{ $ds->id }}, 'Cancel Session', 'Cancel this class session? Enrollees will need to be rebooked.', 'Cancel Session', true)" class="gqs-btn" style="background:#C8102E;color:#fff;">Cancel Session</button>
                            <span style="display:flex;gap:9px;">
                                <button type="button" wire:click="closeSessionDetail" class="gqs-btn gqs-btn-ghost">Close</button>
                                @if(! $dlocked)<button type="button" wire:click="saveSessionDetail" class="gqs-btn gqs-btn-primary">Save / Reschedule</button>@endif
                            </span>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        {{-- in-app confirmation modal (replaces native confirm prompts) --}}
        @if(! empty($confirm))
            <div class="gqs-modal-overlay" wire:click.self="cancelConfirm">
                <div class="gqs-modal" style="width:440px;">
                    <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-exclamation-triangle"/></span>{{ $confirm['title'] }}</div>
                    <div class="gqs-modal-body"><p style="margin:0;font-size:13.5px;color:var(--gqs-text,#1A1A1F);line-height:1.5;">{{ $confirm['body'] }}</p></div>
                    <div class="gqs-modal-foot">
                        <button type="button" wire:click="cancelConfirm" class="gqs-btn gqs-btn-ghost">Cancel</button>
                        <button type="button" wire:click="runConfirm" class="gqs-btn {{ ($confirm['danger'] ?? false) ? '' : 'gqs-btn-primary' }}" @if($confirm['danger'] ?? false) style="background:#C8102E;color:#fff;" @endif>{{ $confirm['label'] ?? 'Confirm' }}</button>
                    </div>
                </div>
            </div>
        @endif

    @elseif($tab === 'attendance')
        {{-- ATTENDANCE: pick a session, then take attendance on one focused sheet --}}
        @php $sessions = $this->sessions(); @endphp
        @if($focusSessionId)
            @php $s = $sessions->firstWhere('id', $focusSessionId) ?? \App\Models\ClassSession::with(['trainingClass','instructorUser'])->find($focusSessionId);
                $attendees = $this->sessionAttendees($focusSessionId); $submitted = (bool) $s?->attendance_submitted_at; @endphp
            @if(! $s)
                <div class="gqs-panel"><div class="gqs-empty" style="padding:24px;">Session Not Found. <button wire:click="unfocusSession" class="rd-act rd-act-magenta">Back To Sessions</button></div></div>
            @else
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
                    <button type="button" wire:click="unfocusSession" class="gqs-btn gqs-btn-ghost">&larr; Back To Sessions</button>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a href="{{ route('print.class-attendance', [$s->id, 'FORM-AST-36513-' . ($s->session_uid ?: 'Class') . '.pdf']) }}@if($s->instructorUser)?trainer={{ urlencode($s->instructorUser->name) }}@endif" target="_blank" class="gqs-btn gqs-btn-ghost" style="text-decoration:none;">Print Attendance Form</a>
                    </div>
                </div>
                <div class="gqs-panel">
                    <div class="gqs-panel-head" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <x-filament::icon icon="heroicon-m-academic-cap"/>
                        <span>{{ $s->trainingClass?->name }} · {{ $s->session_date->gmpL() }}@if($s->start_time) · {{ \Illuminate\Support\Carbon::parse($s->start_time)->format('H:i') }}@endif</span>
                        <span style="margin-left:auto;display:flex;align-items:center;gap:8px;">
                            @if($submitted)
                                <span style="font-size:12px;font-weight:600;opacity:.9;">Trainer: {{ $s->instructorUser?->name ?? $s->instructor ?? 'None' }}</span>
                                <span class="gqs-pill gqs-pill-green">Submitted</span>
                            @else
                                <label style="font-size:12px;font-weight:600;opacity:.9;">Trainer</label>
                                <select wire:change="setAttendanceTrainer({{ $s->id }}, $event.target.value)"
                                        style="font-size:12px;padding:5px 8px;border:1px solid var(--gqs-border,#C4C4CC);border-radius:7px;background:var(--gqs-surface,#fff);color:var(--gqs-text,#1A1A1F);">
                                    <option value="">No Trainer</option>
                                    @foreach($this->instructorOptions() as $tid => $tname)
                                        <option value="{{ $tid }}" @selected($s->assigned_instructor_id == $tid)>{{ $tname }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </span>
                    </div>
                    <div class="gqs-panel-body" style="padding:14px 16px;">
                        @if(empty($attendees))
                            <div class="gqs-empty">No One Enrolled Yet. Enrollments Are Managed On Class Reservations.</div>
                        @else
                            @if($submitted)
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
                                    <span style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">Submitted {{ \Illuminate\Support\Carbon::parse($s->attendance_submitted_at)->gmpDt() }} · awaiting QA classroom approval.</span>
                                    <button type="button" class="gqs-btn gqs-btn-ghost"
                                            wire:click="askConfirm('reopenAttendance', {{ $s->id }}, 'Reopen Session', 'Reopen this session? Attendees not yet QA-approved return to draft.', 'Reopen')">Reopen</button>
                                </div>
                            @else
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
                                    <span style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">Tap a status to mark each person. Nothing is saved until you Sign &amp; Submit at the bottom · enrollees stay Signed Up until then.</span>
                                    <button type="button" wire:click="markAllAttended({{ $s->id }})" class="att-tog att-att">&check; Mark All Attended</button>
                                </div>
                            @endif

                            <div class="att-list">
                                @foreach($attendees as $row)
                                    @php $aIntent = $row['draft'] ?? null;
                                         $tintKey = $submitted ? (in_array($row['status'], ['attended','completed','pending_qa']) ? 'attended' : ($row['status'] === 'no_show' ? 'no_show' : ($row['status'] === 'rescheduled' ? 'rescheduled' : null))) : $aIntent; @endphp
                                    <div class="att-row {{ $tintKey === 'attended' ? 'att-done' : ($tintKey === 'no_show' ? 'att-absent' : ($tintKey === 'rescheduled' ? 'att-resched' : '')) }}">
                                        <div class="att-who">
                                            @if($row['personnel_id'])
                                                <button type="button" wire:click="showPersonDetail({{ $row['personnel_id'] }})" class="att-name" style="background:none;border:none;padding:0;cursor:pointer;text-align:left;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px;" title="View details">{{ $row['name'] }}</button>
                                            @else
                                                <div class="att-name">{{ $row['name'] }}</div>
                                            @endif
                                            <div class="att-eid">{{ $row['employee_id'] }}</div>
                                        </div>
                                        @if($submitted)
                                            <div class="att-state">
                                                <span class="gqs-pill {{ [
                                                    'attended' => 'gqs-pill-green', 'pending_qa' => 'gqs-pill-purple',
                                                    'completed' => 'gqs-pill-green', 'no_show' => 'gqs-pill-red',
                                                ][$row['status']] ?? 'gqs-pill-purple' }}">{{ ucwords(str_replace('_',' ',$row['status'])) }}</span>
                                            </div>
                                            @if(! empty($row['note']))<div class="att-note-ro">{{ $row['note'] }}</div>@endif
                                        @else
                                            <div class="att-toggles">
                                                <button type="button" wire:click="markAttendance({{ $row['id'] }}, 'attended')"
                                                        class="att-tog att-att {{ $aIntent === 'attended' ? 'on' : '' }}">
                                                    <span class="att-box"></span> Attended
                                                </button>
                                                <button type="button" wire:click="markAttendance({{ $row['id'] }}, 'no_show')"
                                                        class="att-tog att-no {{ $aIntent === 'no_show' ? 'on' : '' }}">
                                                    <span class="att-box"></span> No-Show
                                                </button>
                                                <button type="button" class="att-tog att-res"
                                                        wire:click="openReschedule({{ $row['id'] }})">Reschedule</button>
                                                <button type="button" class="att-tog att-cancel"
                                                        wire:click="askConfirm('cancelEnrollment', {{ $row['id'] }}, 'Cancel Enrollment', 'Cancel this person\'s class enrollment and free their seat?', 'Cancel Enrollment', true)">Cancel</button>
                                            </div>
                                            <input type="text" class="att-note" placeholder="Notes (optional)"
                                                   value="{{ $row['note'] }}"
                                                   wire:change="saveAttendanceNote({{ $row['id'] }}, $event.target.value)">
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            @if(! $submitted)
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:16px;padding-top:14px;border-top:1px solid var(--gqs-border,#E6E6EA);">
                                    <button type="button" wire:click="unfocusSession" class="gqs-btn gqs-btn-ghost">Save &amp; Close</button>
                                    <button type="button" class="gqs-btn gqs-btn-primary"
                                            wire:click="openSubmitSign({{ $s->id }})">Submit Attendance To QA</button>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endif
        @else
            @php $sessions = $this->sessions(); @endphp
            @if($sessions->isEmpty())
                <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No sessions yet. Create them on the Sessions tab.</div></div>
            @else
                <div class="gqs-panel">
                    <div class="gqs-panel-body" style="padding:0;">
                        <table class="gqs-tbl">
                            <thead><tr><th>Date</th><th>Class</th><th>Instructor</th><th>Booked</th><th>Status</th><th style="text-align:right;">Attendance</th></tr></thead>
                            <tbody>
                                @foreach($sessions as $s)
                                    <tr style="cursor:pointer;" wire:click="focusSession({{ $s->id }})">
                                        <td style="font-weight:700;">{{ $s->session_date->gmpD() }}</td>
                                        <td>{{ $s->trainingClass?->name }}</td>
                                        <td>{{ $s->instructorUser?->name ?? $s->instructor ?? 'Unassigned' }}</td>
                                        <td>{{ $s->booked }}</td>
                                        <td>@if($s->attendance_submitted_at)<span class="gqs-pill gqs-pill-green">Submitted</span>@else<span class="gqs-pill gqs-pill-purple">Open</span>@endif</td>
                                        <td style="text-align:right;white-space:nowrap;">
                                            <button type="button" wire:click.stop="focusSession({{ $s->id }})" class="rd-act rd-act-magenta">Take Attendance</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif

        {{-- Reschedule modal: pick a date OR book next available --}}
        @if($showReschedule)
            <div class="gqs-modal-overlay" wire:click.self="$set('showReschedule', false)">
                <div class="gqs-modal">
                    <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-calendar-days"/></span>Reschedule</div>
                    <div class="gqs-modal-body">
                        <p style="font-size:13px;color:var(--gqs-text-dim,#6A6A72);margin:0;">{{ $rescheduleName }}</p>
                        <div>
                            <label class="gqs-flbl">Pick A Class Date</label>
                            <select wire:model="rescheduleSessionId" class="gqs-fld">
                                <option value="">Select A Class Date...</option>
                                @foreach($this->openSessionOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;color:var(--gqs-text-dim,#9A9AA2);font-size:11.5px;font-weight:600;">
                            <span style="flex:1;height:1px;background:var(--gqs-border,#E2E2E6);"></span> OR <span style="flex:1;height:1px;background:var(--gqs-border,#E2E2E6);"></span>
                        </div>
                        <button type="button" wire:click="rescheduleNextAvailable" class="gqs-btn gqs-btn-ghost" style="width:100%;justify-content:center;">Book Next Available</button>
                    </div>
                    <div class="gqs-modal-foot">
                        <button type="button" wire:click="$set('showReschedule', false)" class="gqs-btn gqs-btn-ghost">Cancel</button>
                        <button type="button" wire:click="rescheduleToSelected" class="gqs-btn gqs-btn-primary">Move To Selected Date</button>
                    </div>
                </div>
            </div>
        @endif

        {{-- in-app confirmation modal (replaces native confirm prompts) --}}
        @if(! empty($confirm))
            <div class="gqs-modal-overlay" wire:click.self="cancelConfirm">
                <div class="gqs-modal" style="width:440px;">
                    <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-exclamation-triangle"/></span>{{ $confirm['title'] }}</div>
                    <div class="gqs-modal-body"><p style="margin:0;font-size:13.5px;color:var(--gqs-text,#1A1A1F);line-height:1.5;">{{ $confirm['body'] }}</p></div>
                    <div class="gqs-modal-foot">
                        <button type="button" wire:click="cancelConfirm" class="gqs-btn gqs-btn-ghost">Cancel</button>
                        <button type="button" wire:click="runConfirm" class="gqs-btn {{ ($confirm['danger'] ?? false) ? '' : 'gqs-btn-primary' }}" @if($confirm['danger'] ?? false) style="background:#C8102E;color:#fff;" @endif>{{ $confirm['label'] ?? 'Confirm' }}</button>
                    </div>
                </div>
            </div>
        @endif

    @endif


    {{-- Attendance Form: pick trainer (teach-qualified staff), then open prefilled PDF --}}
    @if($showAttendanceForm)
        <div class="gqs-modal-overlay" wire:click.self="$set('showAttendanceForm', false)">
            <div class="gqs-modal">
                <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-document-text"/></span>Attendance Form Trainer</div>
                <div class="gqs-modal-body">
                    <div>
                        <label class="gqs-flbl">Trainer (Qualified To Teach)</label>
                        <select wire:model="attendanceTrainerId" class="gqs-fld">
                            <option value="">Select a trainer...</option>
                            @foreach($this->instructorOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                        <p style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);margin:8px 0 0;">Only staff designated as qualified for classroom training appear here. Set designations on the user's profile.</p>
                    </div>
                </div>
                <div class="gqs-modal-foot">
                    <button type="button" wire:click="$set('showAttendanceForm', false)" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button type="button" wire:click="generateAttendanceForm" class="gqs-btn gqs-btn-primary">Open Form</button>
                </div>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('open-url', (e) => {
                const url = Array.isArray(e) ? e[0]?.url : e?.url;
                if (url) window.open(url, '_blank');
            });
        });
    </script>

    {{-- Trainer e-signature on attendance submit --}}
    @if($signSubmitSid)
        @php $ss = \App\Models\ClassSession::with('instructorUser')->find($signSubmitSid);
             $trainerName = $ss?->instructorUser?->name ?? (auth()->user()->name . ' (You, The Signer)'); @endphp
        <div class="gqs-modal-overlay" wire:click.self="closeSubmitSign">
            <div class="gqs-modal" style="width:460px;">
                <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-pencil-square"/></span>Sign &amp; Submit Attendance</div>
                <div class="gqs-modal-body">
                    <p style="margin:0 0 10px;font-size:13px;color:var(--gqs-text,#1A1A1F);line-height:1.5;">
                        Trainer Of Record: <strong>{{ $trainerName }}</strong>. Your electronic signature submits this attendance to QA, locks the session, and records your name as the trainer on FORM-AST-36513. If no trainer is set, you are recorded as the trainer.
                    </p>
                    <label class="gqs-flbl">Veeva Report Number</label>
                    <div style="display:flex;align-items:stretch;border:1px solid var(--gqs-border,#C4C4CC);border-radius:9px;overflow:hidden;">
                        <span style="display:flex;align-items:center;padding:0 12px;background:var(--gqs-surface-2,#F1F1F4);font-weight:800;color:var(--gqs-text-dim,#6A6A72);border-right:1px solid var(--gqs-border,#C4C4CC);">RPT-AST-</span>
                        <input type="text" wire:model.live.debounce.400ms="signVeeva" class="gqs-fld" style="border:none;border-radius:0;flex:1;" placeholder="type the numbers">
                    </div>
                    <div style="font-size:11px;color:var(--gqs-text-dim,#6A6A72);margin-top:4px;">Type only the numbers - RPT-AST- is added automatically. The Veeva link fills once the report is in the catalog.</div>
                    @php $vvFull = trim($signVeeva) !== '' ? 'RPT-AST-' . ltrim(preg_replace('/^RPT-AST[-\s]*/i', '', trim($signVeeva)), '-') : ''; @endphp
                    @if($vvFull !== '')
                        @php $vd = \App\Models\VeevaDocument::findByNumber($vvFull); @endphp
                        @if($vd && strcasecmp(trim($vd->status), 'Approved') === 0)
                            <p style="margin:6px 0 0;font-size:12px;color:#1E7A52;font-weight:600;">Veeva Approved ✓@if($vd->title) · {{ \Illuminate\Support\Str::limit($vd->title, 48) }}@endif</p>
                        @elseif($vd)
                            <p style="margin:6px 0 0;font-size:12px;color:#8A6D0B;font-weight:600;">In Veeva as {{ $vd->status ?: 'not Approved' }} (not Approved yet)</p>
                        @else
                            <p style="margin:6px 0 0;font-size:12px;color:var(--gqs-text-dim,#6A6A72);">Not in the Veeva catalog yet; the link will fill once it is.</p>
                        @endif
                    @endif
                    <label class="gqs-flbl" style="margin-top:12px;">Password</label>
                    <input type="password" wire:model="signPassword" wire:keydown.enter="confirmSubmitSign" class="gqs-fld" autocomplete="off" placeholder="Enter your password to sign">
                    <p style="margin:8px 0 0;font-size:11.5px;color:var(--gqs-text-dim,#6A6A72);">Signed By {{ auth()->user()->name }} · {{ now()->gmpDt() }}</p>
                </div>
                <div class="gqs-modal-foot">
                    <button type="button" wire:click="closeSubmitSign" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button type="button" wire:click="confirmSubmitSign" class="gqs-btn gqs-btn-primary">Sign &amp; Submit</button>
                </div>
            </div>
        </div>
    @endif

    <style>
        .rs-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;}
        .rs-stat{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid var(--gqs-border,#E2E2E6);border-radius:12px;padding:14px 16px;box-shadow:0 1px 3px rgba(0,0,0,.05);}
        .dark .rs-stat{background:#1A1A20;border-color:#2A2A32;}
        .rs-stat-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
        .rs-stat-val{font-size:24px;font-weight:800;line-height:1;color:var(--gqs-text,#1A1A1F);}
        .dark .rs-stat-val{color:#fff;}
        .rs-stat-lbl{font-size:11.5px;font-weight:600;color:var(--gqs-text-dim,#6A6A72);margin-top:4px;text-transform:uppercase;letter-spacing:.03em;}
        .rd-act{font-size:12px;font-weight:700;padding:5px 12px;border-radius:7px;border:none;cursor:pointer;color:#fff;}
        .rd-act-magenta{background:#A4123F;} .rd-act-magenta:hover{background:#85102F;}
    </style>
    @if($personDetail)
        <div class="gqs-modal-overlay" wire:click.self="closePersonDetail">
            <div class="gqs-modal" style="width:640px;max-width:96vw;">
                <div style="background:linear-gradient(135deg,#A4123F,#7A0E2F);padding:16px 20px;border-radius:14px 14px 0 0;">
                    <div style="font-weight:800;font-size:18px;color:#fff;">{{ $personDetail['name'] }}</div>
                    <div style="font-size:12px;color:rgba(255,255,255,.9);">{{ $personDetail['employee_id'] }}@if($personDetail['job_title']) · {{ $personDetail['job_title'] }}@endif</div>
                </div>
                <div class="gqs-modal-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div><div class="dm-l">Department</div><div class="dm-v">{{ $personDetail['department'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Stage</div><div class="dm-v">{{ $personDetail['stage'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Status</div><div class="dm-v">{{ $personDetail['status'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Session Type</div><div class="dm-v">{{ $personDetail['type'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Due Date</div><div class="dm-v">{{ $personDetail['due'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Class Completed</div><div class="dm-v">{{ $personDetail['class_date'] ?: '—' }}</div></div>
                        <div><div class="dm-l">Class On File</div><div class="dm-v">@if($personDetail['class_on_file'])<span class="gqs-pill gqs-pill-green">Yes</span>@else<span class="gqs-pill gqs-pill-gray">No</span>@endif</div></div>
                    </div>
                    @if(count($personDetail['enrollments']))
                        <div class="dm-l" style="margin-top:18px;">Class Enrollments</div>
                        <table class="gqs-tbl" style="margin-top:6px;">
                            <thead><tr><th>Class</th><th>Date</th><th>Status</th></tr></thead>
                            <tbody>@foreach($personDetail['enrollments'] as $e)
                                <tr><td>{{ $e['class'] }}</td><td>{{ $e['date'] ?: '—' }}</td><td>{{ $e['status'] }}</td></tr>
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
</x-filament-panels::page>
