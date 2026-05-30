<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Class Scheduler', 'icon' => 'heroicon-o-academic-cap', 'actions' => '
        <button type="button" wire:click="$set(\'tab\',\'overview\')" class="gqs-tab ' . ($tab==='overview' ? 'active' : '') . '">Overview</button>
        <button type="button" wire:click="$set(\'tab\',\'classes\')" class="gqs-tab ' . ($tab==='classes' ? 'active' : '') . '">Classes</button>
        <button type="button" wire:click="$set(\'tab\',\'sessions\')" class="gqs-tab ' . ($tab==='sessions' ? 'active' : '') . '">Sessions</button>
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
                        <thead><tr><th>Employee ID</th><th>Name</th><th>Department</th><th>Waiting</th></tr></thead>
                        <tbody>
                            @foreach($need as $n)
                                <tr>
                                    <td style="font-weight:600;">{{ $n['employee_id'] }}</td>
                                    <td>{{ $n['name'] }}</td>
                                    <td>{{ $n['department'] ?: '—' }}</td>
                                    <td style="color:var(--gqs-text-dim,#6A6A72);">{{ $n['since'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

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
                    <div class="gqs-empty" style="padding:28px;">No class templates yet. Create one, then generate sessions from it.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Class</th><th>Code</th><th>Default Capacity</th><th>Validity</th><th>Prerequisite</th><th>Sessions</th><th></th></tr></thead>
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
                                        <button wire:click="$set('sessClassId', {{ $t->id }}); $set('tab','sessions'); $set('showAddSession', true)" class="rd-act rd-act-magenta">Generate Sessions</button>
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
            <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No upcoming sessions. Generate some from a class template.</div></div>
        @else
            <div class="gqs-panel">
                <div class="gqs-panel-body" style="padding:0;">
                    <table class="gqs-tbl">
                        <thead><tr><th>Date</th><th>Time</th><th>Class</th><th>Location</th><th>Instructor</th><th>Booked / Cap</th><th>Status</th><th style="text-align:right;">Manage</th></tr></thead>
                        <tbody>
                            @foreach($sessions as $s)
                                <tr style="cursor:pointer;" wire:click="openSessionDetail({{ $s->id }})">
                                    <td style="font-weight:700;">{{ $s->session_date->format('D, M j, Y') }}</td>
                                    <td>{{ $s->start_time ? \Illuminate\Support\Carbon::parse($s->start_time)->format('g:i A') : '—' }}</td>
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
                    <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-plus"/></span>Add / Generate Class Sessions</div>
                    <div class="gqs-modal-body">
                        <div><label class="gqs-flbl">Class Template</label>
                            <select wire:model="sessClassId" class="gqs-fld"><option value="">Select a class...</option>
                                @foreach($this->classOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                            </select></div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div><label class="gqs-flbl">First Date</label><input type="date" wire:model="sessDate" class="gqs-fld"></div>
                            <div><label class="gqs-flbl">Location</label><input type="text" wire:model="sessLocation" placeholder="Template default" class="gqs-fld"></div>
                            <div><label class="gqs-flbl">Start</label><input type="time" wire:model="sessStart" class="gqs-fld"></div>
                            <div><label class="gqs-flbl">End</label><input type="time" wire:model="sessEnd" class="gqs-fld"></div>
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
                                <div><label class="gqs-flbl">Repeat Until</label><input type="date" wire:model="sessUntil" class="gqs-fld"></div>
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
                                <div style="padding:10px 12px;background:#E9F7EF;border:1px solid #BFE6CE;border-radius:8px;font-size:12.5px;color:#1C5E3A;">Attendance Submitted · {{ count($this->sessionAttendees($detailSessionId)) }} enrolled. This session is locked from editing.</div>
                            @else
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                    <div><label class="gqs-flbl">Date</label><input type="date" wire:model="editSession.session_date" class="gqs-fld"></div>
                                    <div><label class="gqs-flbl">Location</label><input type="text" wire:model="editSession.location" class="gqs-fld"></div>
                                    <div><label class="gqs-flbl">Start</label><input type="time" wire:model="editSession.start_time" class="gqs-fld"></div>
                                    <div><label class="gqs-flbl">End</label><input type="time" wire:model="editSession.end_time" class="gqs-fld"></div>
                                    <div><label class="gqs-flbl">Capacity</label><input type="number" min="1" wire:model="editSession.capacity" class="gqs-fld"></div>
                                    <div><label class="gqs-flbl">Instructor</label>
                                        <select wire:model="editSession.assigned_instructor_id" class="gqs-fld"><option value="">Unassigned</option>
                                            @foreach($this->instructorOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                        </select></div>
                                </div>
                            @endif
                            <div style="display:flex;gap:8px;flex-wrap:wrap;padding-top:4px;">
                                <a href="{{ route('print.class-attendance', $ds->id) }}@if($ds->instructorUser)?trainer={{ urlencode($ds->instructorUser->name) }}@endif" target="_blank" class="gqs-btn gqs-btn-ghost" style="text-decoration:none;">Print Attendance Form</a>
                                <a href="{{ \App\Filament\Admin\Pages\ClassScheduler::getUrl() }}?attend={{ $ds->id }}" class="gqs-btn gqs-btn-ghost" style="text-decoration:none;">Take Attendance</a>
                            </div>
                        </div>
                        <div class="gqs-modal-foot" style="justify-content:space-between;">
                            <button type="button" wire:click="askConfirm('cancelSession', {{ $ds->id }}, 'Cancel Session', 'Cancel this class session? Enrollees will need to be rebooked.', 'Cancel Session', true)" class="gqs-btn" style="background:#C8102E;color:#fff;">Cancel Session</button>
                            <span style="display:flex;gap:9px;">
                                <button type="button" wire:click="closeSessionDetail" class="gqs-btn gqs-btn-ghost">Close</button>
                                @if(! $dlocked)<button type="button" wire:click="saveSessionDetail" class="gqs-btn gqs-btn-primary">Save Changes</button>@endif
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
                        <a href="{{ route('print.class-attendance', $s->id) }}@if($s->instructorUser)?trainer={{ urlencode($s->instructorUser->name) }}@endif" target="_blank" class="rd-act rd-act-magenta" style="text-decoration:none;">Print Attendance Form</a>
                    </div>
                </div>
                <div class="gqs-panel">
                    <div class="gqs-panel-head" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <x-filament::icon icon="heroicon-m-academic-cap"/>
                        <span>{{ $s->trainingClass?->name }} · {{ $s->session_date->format('l, M j, Y') }}@if($s->start_time) · {{ \Illuminate\Support\Carbon::parse($s->start_time)->format('g:i A') }}@endif</span>
                        <span style="margin-left:auto;display:flex;align-items:center;gap:8px;">
                            <span style="font-size:12px;font-weight:600;opacity:.9;">{{ $s->instructorUser?->name ?? $s->instructor ?? 'No Instructor' }}</span>
                            @if($submitted)<span class="gqs-pill gqs-pill-green">Submitted</span>@endif
                        </span>
                    </div>
                    <div class="gqs-panel-body">
                        @if(empty($attendees))
                            <div class="gqs-empty">No One Enrolled Yet. Enrollments Are Managed On Class Reservations.</div>
                        @else
                            @if(! $submitted)
                                <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
                                    <button type="button" class="gqs-btn gqs-btn-primary"
                                            wire:click="askConfirm('submitAttendance', {{ $s->id }}, 'Submit Attendance', 'Submit this session attendance to QA? It will be locked and everyone marked Attended will be sent to the QA Classroom Approval queue.', 'Submit To QA')">Submit Attendance</button>
                                </div>
                            @else
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                                    <span style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">Submitted {{ \Illuminate\Support\Carbon::parse($s->attendance_submitted_at)->format('M j, Y g:i A') }} · awaiting QA classroom approval.</span>
                                    <button type="button" class="gqs-btn gqs-btn-ghost"
                                            wire:click="askConfirm('reopenAttendance', {{ $s->id }}, 'Reopen Session', 'Reopen this session? Attendees not yet QA-approved return to draft.', 'Reopen')">Reopen</button>
                                </div>
                            @endif
                            <table class="gqs-tbl">
                                <thead><tr><th>Employee</th><th>Name</th><th>Status</th><th style="text-align:right;">Attendance</th></tr></thead>
                                <tbody>
                                    @foreach($attendees as $row)
                                        <tr>
                                            <td style="font-weight:600;">{{ $row['employee_id'] }}</td>
                                            <td>{{ $row['name'] }}</td>
                                            <td><span class="gqs-pill {{ [
                                                'signed_up' => 'gqs-pill-purple', 'attended' => 'gqs-pill-gold',
                                                'pending_qa' => 'gqs-pill-purple', 'completed' => 'gqs-pill-green', 'no_show' => 'gqs-pill-red',
                                            ][$row['status']] ?? 'gqs-pill-purple' }}">{{ ucwords(str_replace('_',' ',$row['status'])) }}</span></td>
                                            <td style="text-align:right;white-space:nowrap;">
                                                @if(! $submitted && in_array($row['status'], ['signed_up','attended','no_show']))
                                                    <button wire:click="markAttendance({{ $row['id'] }}, 'attended')" class="sb-act sb-act-green">Attended</button>
                                                    <button wire:click="markAttendance({{ $row['id'] }}, 'no_show')" class="sb-act sb-act-red">No-Show</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
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
                                        <td style="font-weight:700;">{{ $s->session_date->format('D, M j, Y') }}</td>
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
</x-filament-panels::page>
