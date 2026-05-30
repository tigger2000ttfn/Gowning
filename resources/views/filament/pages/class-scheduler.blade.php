<x-filament-panels::page>
    <div class="sb-headrow">
        <div class="sb-headrow-title">
            <span class="pg-head-ico"><x-filament::icon icon="heroicon-o-academic-cap" /></span>
            <div class="pg-head-tx" style="min-width:0;">
                <h1>Class Scheduler</h1>
                <p>@switch($tab)
                    @case('overview')Who needs the gowning class, and the state of class scheduling.@break
                    @case('classes')Class templates: the reusable definitions you generate dated sessions from.@break
                    @case('sessions')Individual dated class sessions. Generate from a template, cancel one at a time.@break
                @endswitch</p>
            </div>
        </div>
        <div class="sb-headrow-filters">
            <div class="gqs-tabs">
                <button type="button" wire:click="$set('tab','overview')" class="gqs-tab @if($tab==='overview') on @endif">Overview</button>
                <button type="button" wire:click="$set('tab','classes')" class="gqs-tab @if($tab==='classes') on @endif">Classes</button>
                <button type="button" wire:click="$set('tab','sessions')" class="gqs-tab @if($tab==='sessions') on @endif">Sessions</button>
            </div>
        </div>
    </div>

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

    @else
        {{-- SESSIONS --}}
        <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
            <button type="button" wire:click="$set('showAddSession', true)"
                    style="display:inline-flex;align-items:center;gap:7px;padding:9px 15px;background:#A4123F;color:#fff;border:none;border-radius:9px;font-weight:700;font-size:13px;cursor:pointer;">
                <x-filament::icon icon="heroicon-m-plus" style="width:16px;height:16px;"/> Add / Generate Sessions
            </button>
        </div>
        @php $sessions = $this->sessions(); @endphp
        @if($sessions->isEmpty())
            <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No upcoming sessions. Generate some from a class template.</div></div>
        @else
            @foreach($sessions as $s)
                @php $attendees = $this->sessionAttendees($s->id); $submitted = (bool) $s->attendance_submitted_at; @endphp
                <div class="gqs-panel" style="margin-bottom:16px;">
                    <div class="gqs-panel-head" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <x-filament::icon icon="heroicon-m-academic-cap"/>
                        <span>{{ $s->trainingClass?->name }} · {{ $s->session_date->format('D, M j, Y') }}@if($s->start_time) · {{ \Illuminate\Support\Carbon::parse($s->start_time)->format('g:i A') }}@endif</span>
                        <span style="margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span style="font-size:12px;font-weight:600;opacity:.9;">{{ $s->instructorUser?->name ?? $s->instructor ?? 'No Instructor' }} · {{ $s->booked }} / {{ $s->capacity }}</span>
                            @if($submitted)<span class="gqs-pill gqs-pill-green">Submitted</span>@endif
                            <button type="button" wire:click="openAttendanceForm({{ $s->id }})" class="rd-act rd-act-magenta">Print Attendance Form</button>
                            <button wire:click="cancelSession({{ $s->id }})" wire:confirm="Cancel this session?" class="rd-act" style="background:#C8102E;">Cancel Session</button>
                        </span>
                    </div>
                    <div class="gqs-panel-body">
                        @if(empty($attendees))
                            <div class="gqs-empty">No One Enrolled Yet. Enrollments Are Managed On Class Reservations.</div>
                        @else
                            @if(! $submitted)
                                <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
                                    <button type="button" wire:click="submitAttendance({{ $s->id }})"
                                            wire:confirm="Submit this session's attendance to QA? It will be locked and attendees sent to the QA Classroom Approval queue."
                                            class="gqs-btn gqs-btn-primary">Submit Attendance</button>
                                </div>
                            @else
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                                    <span style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">Submitted {{ \Illuminate\Support\Carbon::parse($s->attendance_submitted_at)->format('M j, Y g:i A') }} · awaiting QA classroom approval.</span>
                                    <button type="button" wire:click="reopenAttendance({{ $s->id }})"
                                            wire:confirm="Reopen this session? Attendees not yet QA-approved return to draft." class="gqs-btn gqs-btn-ghost">Reopen</button>
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
            @endforeach
        @endif

        @if($showAddSession)
            <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="$set('showAddSession', false)">
                <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:480px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                    <div style="background:#1C1C21;color:#fff;padding:16px 20px;border-radius:14px 14px 0 0;font-weight:800;font-size:16px;">Add / Generate Class Sessions</div>
                    <div style="padding:18px 20px;">
                        <div style="margin-bottom:12px;"><label class="gqs-flbl">Class Template</label>
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
                        <label style="display:flex;align-items:center;gap:8px;margin-top:14px;font-size:13px;font-weight:600;cursor:pointer;color:var(--gqs-text,#1A1A1F);">
                            <input type="checkbox" wire:model.live="sessRepeat"> Repeat this session
                        </label>
                        @if($sessRepeat)
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;padding:12px;background:var(--gqs-surface-2,#F5F5F7);border-radius:9px;">
                                <div><label class="gqs-flbl">Pattern</label>
                                    <select wire:model="sessPattern" class="gqs-fld">
                                        <option value="weekly">Weekly</option>
                                        <option value="biweekly">Every 2 Weeks</option>
                                        <option value="monthly">Monthly</option>
                                    </select></div>
                                <div><label class="gqs-flbl">Repeat Until</label><input type="date" wire:model="sessUntil" class="gqs-fld"></div>
                            </div>
                        @endif
                        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                            <button type="button" wire:click="$set('showAddSession', false)" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Cancel</button>
                            <button type="button" wire:click="addSession" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;border:none;font-weight:700;cursor:pointer;">@if($sessRepeat)Generate @else Add Session @endif</button>
                        </div>
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
