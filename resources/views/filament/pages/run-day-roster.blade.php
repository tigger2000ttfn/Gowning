<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Run Scheduler', 'icon' => 'heroicon-o-calendar-days', 'actions' => '
        <button type="button" wire:click="$set(\'tab\',\'overview\')" class="gqs-tab ' . ($tab==='overview' ? 'active' : '') . '">Overview</button>
        <button type="button" wire:click="$set(\'tab\',\'reservations\')" class="gqs-tab ' . ($tab==='reservations' ? 'active' : '') . '">Reservations</button>
        <button type="button" wire:click="$set(\'tab\',\'schedule\')" class="gqs-tab ' . ($tab==='schedule' ? 'active' : '') . '">Run Days</button>
        <button type="button" wire:click="$set(\'tab\',\'roster\')" class="gqs-tab ' . ($tab==='roster' ? 'active' : '') . '">Attendance</button>
    '])

    @if($tab === 'overview')
        {{-- OVERVIEW TAB: mini-dashboard for the run pipeline --}}
        @php $stats = $this->overviewStats(); $waiting = $this->getWaiting(); @endphp
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
            <div class="gqs-panel-head" style="justify-content:space-between;">
                <span style="display:flex;align-items:center;gap:9px;"><x-filament::icon icon="heroicon-m-clock"/> Awaiting Scheduling</span>
                @if(count($waiting))
                    <button wire:click="bookAllWaiting" wire:confirm="Book everyone waiting into the next available run days?"
                            style="background:#fff;color:#A4123F;border:none;border-radius:7px;padding:5px 12px;font-weight:700;font-size:12px;cursor:pointer;">Book All Waiting</button>
                @endif
            </div>
            <div class="gqs-panel-body" style="padding:0;">
                @if(empty($waiting))
                    <div class="gqs-empty" style="padding:28px;">Nobody is waiting. Everyone class-complete has a run booked.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Employee ID</th><th>Name</th><th>Department</th><th>Runs Needed</th><th>Waiting</th><th>Type</th><th></th></tr></thead>
                        <tbody>
                            @foreach($waiting as $w)
                                <tr>
                                    <td style="font-weight:600;">{{ $w['employee_id'] }}</td>
                                    <td>{{ $w['name'] }}</td>
                                    <td>{{ $w['department'] ?: '—' }}</td>
                                    <td>{{ $w['runs_required'] }}</td>
                                    <td style="color:var(--gqs-text-dim,#6A6A72);">{{ $w['since'] ?? '—' }}</td>
                                    <td>@if($w['is_requal'])<span class="gqs-pill gqs-pill-gold">Requal</span>@else<span class="gqs-pill">Initial</span>@endif</td>
                                    <td style="text-align:right;"><button wire:click="bookWaiting({{ $w['qid'] }})" class="rd-act rd-act-green">Book Next Day</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @elseif($tab === 'schedule')
        {{-- SCHEDULE TAB: manage run days --}}
        <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
            <button type="button" wire:click="$set('showAddSlot', true)"
                    style="display:inline-flex;align-items:center;gap:7px;padding:9px 15px;background:#A4123F;color:#fff;border:none;border-radius:9px;font-weight:700;font-size:13px;cursor:pointer;">
                <x-filament::icon icon="heroicon-m-plus" style="width:16px;height:16px;"/> Add Run Day
            </button>
        </div>

        @php $days = $this->scheduleDays(); @endphp
        <div class="gqs-panel">
            <div class="gqs-panel-body" style="padding:0;">
                @if($days->isEmpty())
                    <div class="gqs-empty" style="padding:28px;">No upcoming run days. Add one to start scheduling.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr>
                            <th wire:click="sortBy('slot_date')" style="cursor:pointer;">Date @if($sortField==='slot_date'){{ $sortDir==='asc'?'▲':'▼' }}@endif</th>
                            <th>Time</th>
                            <th wire:click="sortBy('cleanroom')" style="cursor:pointer;">Cleanroom @if($sortField==='cleanroom'){{ $sortDir==='asc'?'▲':'▼' }}@endif</th>
                            <th>Analyst</th>
                            <th wire:click="sortBy('booked')" style="cursor:pointer;">Booked / Capacity @if($sortField==='booked'){{ $sortDir==='asc'?'▲':'▼' }}@endif</th>
                            <th></th>
                        </tr></thead>
                        <tbody>
                            @foreach($days as $d)
                                <tr>
                                    <td style="font-weight:700;">{{ $d->slot_date->gmpD() }}</td>
                                    <td>{{ $d->start_time ? \Illuminate\Support\Carbon::parse($d->start_time)->format('H:i') : '—' }}@if($d->end_time) – {{ \Illuminate\Support\Carbon::parse($d->end_time)->format('H:i') }}@endif</td>
                                    <td>{{ $d->cleanroom ?: '—' }}</td>
                                    <td>{{ $d->analyst?->name ?? 'Unassigned' }}</td>
                                    <td><span class="gqs-pill {{ $d->seats_left > 0 ? 'gqs-pill-green' : 'gqs-pill-gold' }}">{{ $d->booked }} / {{ $d->capacity }}</span></td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <button wire:click="viewRoster('{{ $d->slot_date->toDateString() }}')" class="rd-act rd-act-magenta">Open Attendance</button>
                                        <button wire:click="openRunDayDetail({{ $d->id }})" class="rd-act" style="background:#C79A2E;">Reschedule</button>
                                        <button wire:click="openRunDayDetail({{ $d->id }})" class="rd-act" style="background:#1C1C21;">Details</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Add run day modal --}}
        @if($showAddSlot)
            <div class="gqs-modal-overlay" wire:click.self="$set('showAddSlot', false)">
                <div class="gqs-modal" style="width:520px;">
                    <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-calendar-days"/></span>Add Run Day</div>
                    <div class="gqs-modal-body">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                            <div><label class="gqs-flbl">Date</label>@include('filament.partials.fp-date',['model'=>'newDate'])</div>
                            <div><label class="gqs-flbl">Cleanroom</label>
                                <select wire:model="newCleanroom" class="gqs-fld"><option value="">Select...</option>
                                    @foreach($this->cleanroomOptions() as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                                </select></div>
                            <div><label class="gqs-flbl">Start</label>@include('filament.partials.fp-date',['model'=>'newStart','isTime'=>true])</div>
                            <div><label class="gqs-flbl">End</label>@include('filament.partials.fp-date',['model'=>'newEnd','isTime'=>true])</div>
                            <div><label class="gqs-flbl">Capacity</label><input type="number" min="1" wire:model="newCapacity" placeholder="default" class="gqs-fld"></div>
                            <div><label class="gqs-flbl">Analyst</label>
                                <select wire:model="newAnalystId" class="gqs-fld"><option value="">Unassigned</option>
                                    @foreach($this->analystOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                </select></div>
                        </div>
                        <div><label class="gqs-flbl">Notes</label><input type="text" wire:model="newNotes" placeholder="Optional" class="gqs-fld"></div>

                        {{-- Recurrence --}}
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;cursor:pointer;color:var(--gqs-text,#1A1A1F);">
                            <input type="checkbox" wire:model.live="repeat"> Repeat this run day
                        </label>
                        @if($repeat)
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:12px;background:var(--gqs-surface-2,#F5F5F7);border-radius:9px;">
                                <div><label class="gqs-flbl">Pattern</label>
                                    <select wire:model="repeatPattern" class="gqs-fld">
                                        <option value="weekly">Weekly</option>
                                        <option value="biweekly">Every 2 Weeks</option>
                                        <option value="monthly">Monthly</option>
                                    </select></div>
                                <div><label class="gqs-flbl">Repeat Until</label>@include('filament.partials.fp-date',['model'=>'repeatUntil'])</div>
                            </div>
                        @endif
                    </div>
                    <div class="gqs-modal-foot">
                        <button type="button" wire:click="$set('showAddSlot', false)" class="gqs-btn gqs-btn-ghost">Cancel</button>
                        <button type="button" wire:click="addSlot" class="gqs-btn gqs-btn-primary">@if($repeat)Generate Run Days @else Add Run Day @endif</button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Run day detail / reschedule modal --}}
        @if($detailSlotId)
            @php $ds = \App\Models\RunSlot::find($detailSlotId); $dlocked = (bool) $ds?->attendance_submitted_at; @endphp
            @if($ds)
                <div class="gqs-modal-overlay" wire:click.self="closeRunDayDetail">
                    <div class="gqs-modal" style="width:520px;">
                        <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-calendar-days"/></span>Run Day · {{ $ds->cleanroom }}</div>
                        <div class="gqs-modal-body">
                            @if($dlocked)
                                <div style="padding:10px 12px;background:#E9F7EF;border:1px solid #BFE6CE;border-radius:8px;font-size:12.5px;color:#1C5E3A;">Attendance Submitted. This Run Day Is Locked From Editing.</div>
                            @else
                                @php $booked = $this->slotBookings($detailSlotId); @endphp
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                    <div><label class="gqs-flbl">Date</label>@include('filament.partials.fp-date',['model'=>'editSlot.slot_date'])</div>
                                    <div><label class="gqs-flbl">Cleanroom</label>
                                        <select wire:model="editSlot.cleanroom" class="gqs-fld">
                                            @foreach($this->cleanroomOptions() as $cr)<option value="{{ $cr }}">{{ $cr }}</option>@endforeach
                                        </select></div>
                                    <div><label class="gqs-flbl">Start</label>@include('filament.partials.fp-date',['model'=>'editSlot.start_time','isTime'=>true])</div>
                                    <div><label class="gqs-flbl">End</label>@include('filament.partials.fp-date',['model'=>'editSlot.end_time','isTime'=>true])</div>
                                    <div><label class="gqs-flbl">Capacity</label><input type="number" min="1" wire:model="editSlot.capacity" class="gqs-fld"></div>
                                    <div><label class="gqs-flbl">Analyst</label>
                                        <select wire:model="editSlot.assigned_analyst_id" class="gqs-fld"><option value="">Unassigned</option>
                                            @foreach($this->analystOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                        </select></div>
                                </div>
                                <div style="margin-top:4px;padding:10px 12px;background:var(--gqs-surface-2,#F4F4F7);border-radius:8px;">
                                    <div style="font-size:12px;font-weight:700;color:var(--gqs-text,#1A1A1F);margin-bottom:4px;">{{ count($booked) }} Booked · Rescheduling This Run Day Moves Them All To The New Date</div>
                                    @if(count($booked))
                                        <div style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">{{ implode(', ', $booked) }}</div>
                                    @else
                                        <div style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">No one booked yet.</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="gqs-modal-foot" style="justify-content:space-between;">
                            <button type="button" wire:click="cancelSlotDay({{ $ds->id }})"
                                    wire:confirm="Cancel this run day? Booked operators will be returned to the queue / rescheduled and notified."
                                    class="gqs-btn" style="background:#C8102E;color:#fff;">Cancel Run Day</button>
                            <span style="display:flex;gap:9px;">
                                <button type="button" wire:click="closeRunDayDetail" class="gqs-btn gqs-btn-ghost">Close</button>
                                @if(! $dlocked)<button type="button" wire:click="saveRunDayDetail" class="gqs-btn gqs-btn-primary">Save / Reschedule</button>@endif
                            </span>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    @elseif($tab === 'reservations')
        {{-- RESERVATIONS TAB --}}
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
            <select wire:model.live="resStatusFilter" class="gqs-fld" style="max-width:200px;">
                <option value="">All Statuses</option>
                <option value="requested">Requested</option>
                <option value="approved">Scheduled</option>
                <option value="completed">Completed</option>
            </select>
            <button type="button" wire:click="$set('showAddRes', true)"
                    style="display:inline-flex;align-items:center;gap:7px;padding:9px 15px;background:#A4123F;color:#fff;border:none;border-radius:9px;font-weight:700;font-size:13px;cursor:pointer;">
                <x-filament::icon icon="heroicon-m-plus" style="width:16px;height:16px;"/> Book A Person
            </button>
        </div>

        @php $groups = $this->reservationsByDay(); @endphp
        @forelse($groups as $g)
            <div class="gqs-panel" style="margin-bottom:14px;">
                <div class="gqs-panel-head" style="justify-content:space-between;">
                    <span style="display:flex;align-items:center;gap:9px;"><x-filament::icon icon="heroicon-m-calendar-days"/> {{ $g['day'] }}</span>
                    <span style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:12px;opacity:.9;">{{ count($g['rows']) }} Booked</span>
                        @if($g['date'] !== 'unscheduled')
                            <button type="button" wire:click="viewRoster('{{ $g['date'] }}')" class="rd-act" style="background:#fff;color:#A4123F;">Attendance Sheet</button>
                        @endif
                    </span>
                </div>
                <div class="gqs-panel-body" style="padding:0;">
                    <table class="gqs-tbl">
                        <thead><tr><th>Employee ID</th><th>Name</th><th>Cleanroom</th><th>Time</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @foreach($g['rows'] as $r)
                                @php
                                    $rsLabel = ['requested'=>'Requested','approved'=>'Scheduled','completed'=>'Completed','no_show'=>'No-Show','rescheduled'=>'Rescheduled','rejected'=>'Cancelled'][$r['status']] ?? ucfirst(str_replace('_',' ',$r['status']));
                                    $rsPill = ['requested'=>'gqs-pill-gold','approved'=>'gqs-pill-purple','completed'=>'gqs-pill-green','no_show'=>'gqs-pill-red','rescheduled'=>'gqs-pill-gold','rejected'=>'gqs-pill-red'][$r['status']] ?? 'gqs-pill-purple';
                                @endphp
                                <tr>
                                    <td style="font-weight:600;">{{ $r['employee_id'] }}</td>
                                    <td>{{ $r['name'] }}</td>
                                    <td>{{ $r['cleanroom'] ?: '—' }}</td>
                                    <td>{{ $r['time'] ?? '—' }}</td>
                                    <td><span class="gqs-pill {{ $rsPill }}">{{ $rsLabel }}</span></td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        @if($r['status'] === 'requested')
                                            <button wire:click="approveReservation({{ $r['id'] }})" class="rd-act rd-act-green">Approve</button>
                                        @endif
                                        @if(in_array($r['status'], ['requested','approved']))
                                            <button wire:click="openMoveRes({{ $r['id'] }})" class="rd-act" style="background:#1F6FB2;">Move</button>
                                            <button wire:click="rosterReschedule({{ $r['id'] }})" wire:confirm="Reschedule to the next available run day?" class="rd-act" style="background:#C79A2E;">Reschedule</button>
                                            <button wire:click="cancelReservation({{ $r['id'] }})" wire:confirm="Cancel this booking? They go back to the unscheduled queue to be rebooked." class="rd-act" style="background:#C8102E;">Cancel</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No reservations match. Book a person or wait for run requests.</div></div>
        @endforelse

        {{-- Book a person modal --}}
        @if($showAddRes)
            <div class="gqs-modal-overlay" wire:click.self="$set('showAddRes', false)">
                <div class="gqs-modal">
                    <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-user-plus"/></span>Book A Person Onto A Run Day</div>
                    <div class="gqs-modal-body">
                        <div>
                            <label class="gqs-flbl">Person</label>
                            <select wire:model="addResPersonnelId" class="gqs-fld">
                                <option value="">Select a person...</option>
                                @foreach($this->bookablePersonnel() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="gqs-flbl">Run Day</label>
                            <select wire:model="addResSlotId" class="gqs-fld">
                                <option value="">Select an open run day...</option>
                                @foreach($this->openSlotsForBooking() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="gqs-modal-foot">
                        <button type="button" wire:click="$set('showAddRes', false)" class="gqs-btn gqs-btn-ghost">Cancel</button>
                        <button type="button" wire:click="addReservation" class="gqs-btn gqs-btn-primary">Book</button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Move reservation modal --}}
        @if($showMoveRes)
            <div class="gqs-modal-overlay" wire:click.self="$set('showMoveRes', false)">
                <div class="gqs-modal">
                    <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-arrows-right-left"/></span>Move Reservation</div>
                    <div class="gqs-modal-body">
                        <p style="font-size:13px;color:var(--gqs-text-dim,#6A6A72);margin:0;">{{ $moveResName }}</p>
                        @if(! $moveSpecial)
                        <div>
                            <label class="gqs-flbl">Move To Run Day</label>
                            <select wire:model="moveResSlotId" class="gqs-fld">
                                <option value="">Select a run day...</option>
                                @foreach($this->openSlotsForBooking() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        @endif
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;margin-top:4px;">
                            <input type="checkbox" wire:model.live="moveSpecial"> Special One-Off Date (VIP)
                        </label>
                        @if($moveSpecial)
                            <div><label class="gqs-flbl">Special Date</label>@include('filament.partials.fp-date',['model'=>'moveSpecialDate'])</div>
                            <div>
                                <label class="gqs-flbl">Cleanroom (Optional)</label>
                                <select wire:model="moveSpecialCleanroom" class="gqs-fld"><option value="">Select...</option>
                                    @foreach($this->cleanroomOptions() as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="gqs-flbl">Assigned Analyst (Optional)</label>
                                <select wire:model="moveSpecialAnalystId" class="gqs-fld"><option value="">Unassigned</option>
                                    @foreach($this->analystOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                    <div class="gqs-modal-foot">
                        <button type="button" wire:click="$set('showMoveRes', false)" class="gqs-btn gqs-btn-ghost">Cancel</button>
                        <button type="button" wire:click="moveReservation" class="gqs-btn gqs-btn-primary">Move</button>
                    </div>
                </div>
            </div>
        @endif
    @else
        {{-- ROSTER TAB --}}
        <div>

        <div style="margin-bottom:18px;max-width:560px;display:flex;gap:14px;align-items:end;">
            <div style="flex:1;max-width:260px;">
                <label class="gqs-flbl">Select Date</label>
                @include('filament.partials.fp-date',['model'=>'date','live'=>true])
            </div>
            <a href="{{ route('print.run-day', ['date' => $date]) }}" target="_blank"
               style="display:inline-flex;align-items:center;gap:7px;padding:10px 16px;background:#A4123F;color:#fff;border-radius:9px;font-weight:700;font-size:13px;text-decoration:none;">
                <x-filament::icon icon="heroicon-m-printer" style="width:16px;height:16px;"/> Print Attendance (PDF)
            </a>
        </div>

        @php $slots = $this->slots(); @endphp

    @if ($slots->isEmpty())
        <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No Qualification Run Slots Scheduled For This Date.</div></div>
    @else
        @foreach ($slots as $slot)
            <div class="gqs-panel">
                <div class="gqs-panel-head" style="justify-content:space-between;">
                    <span style="display:flex;align-items:center;gap:9px;">
                        <x-filament::icon icon="heroicon-m-beaker"/>
                        {{ $slot->cleanroom }}@if($slot->start_time) · {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('H:i') }}@endif
                    </span>
                    <span style="font-size:12px;font-weight:600;opacity:.92;display:flex;align-items:center;gap:10px;">
                        {{ $slot->reservations->count() }} Attending · Cap {{ $slot->capacity }}
                        @if($slot->attendance_submitted_at)
                            <span class="gqs-pill gqs-pill-green">Submitted</span>
                        @endif
                    </span>
                </div>
                <div class="gqs-panel-body" style="padding:14px 16px;">
                    @if ($slot->reservations->isEmpty())<div class="gqs-empty">No one scheduled yet.</div>@else
                        @if(! $slot->attendance_submitted_at)
                            <div class="att-hint" style="margin-bottom:12px;">Enter each person's LIMS worklist and mark Present, No-Show, or Reschedule. Nothing is committed until you Submit the day · the worklist is required for everyone marked Present.</div>
                        @else
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
                                <span class="att-hint">Submitted {{ \Illuminate\Support\Carbon::parse($slot->attendance_submitted_at)->gmpDt() }} · locked.</span>
                                <button type="button" wire:click="reopenRunDay({{ $slot->id }})"
                                        wire:confirm="Reopen this run day to correct attendance?" class="gqs-btn gqs-btn-ghost">Reopen</button>
                            </div>
                        @endif

                        <div class="att-list">
                            @foreach ($slot->reservations as $i => $res)
                                @php
                                    $rq = \App\Models\Qualification::where('personnel_id', $res->personnel_id)->first();
                                    $adv = app(\App\Services\RunCycleAdvancer::class);
                                    $performed = $rq ? $adv->cycleRuns($rq)->count() : 0;
                                    $required = max(1, (int) ($rq->runs_required ?? 1));
                                    $ctx = $this->runContext($rq);
                                    $readyForResults = $rq && $rq->workflow_stage === \App\Enums\WorkflowStage::AwaitingResults;
                                    $st = $res->status instanceof \BackedEnum ? $res->status->value : $res->status;
                                    $runNo = min($performed + 1, $required);
                                    if (! array_key_exists($res->id, $this->worklists)) {
                                        $this->worklists[$res->id] = preg_replace('/^EM-/i', '', $res->lims_worklist_id ?: ($rq?->lims_worklist_id ?: ''));
                                    }
                                    $actionable = ! in_array($st, ['completed','no_show','rescheduled'], true);
                                    $intent = $this->intent[$res->id] ?? null;
                                    $tintKey = $intent ?? ($st === 'completed' ? 'present' : ($st === 'no_show' ? 'no_show' : ($st === 'rescheduled' ? 'rescheduled' : null)));
                                @endphp
                                <div class="att-row {{ $tintKey === 'present' ? 'att-done' : ($tintKey === 'no_show' ? 'att-absent' : ($tintKey === 'rescheduled' ? 'att-resched' : '')) }}">
                                    <div class="att-who">
                                        <div class="att-name">{{ $i + 1 }}. {{ $res->personnel?->full_name }}</div>
                                        <div class="att-eid">
                                            <span>{{ $res->personnel?->employee_id }}</span>
                                            <span class="gqs-pill {{ $ctx['pill'] }}">{{ $ctx['label'] }}@if($ctx['tag']) · {{ $ctx['tag'] }}@endif</span>
                                            <span class="run-pips" title="Run {{ $runNo }} of {{ $required }}">
                                                @for($k = 1; $k <= $required; $k++)
                                                    <span class="run-pip {{ $k <= $performed ? 'done' : ($k == $runNo ? 'cur' : '') }}"></span>
                                                @endfor
                                            </span>
                                            <span style="font-weight:700;">Run {{ $runNo }} of {{ $required }}</span>
                                        </div>
                                    </div>

                                    @if($st === 'completed')
                                        <span class="att-hint" style="min-width:120px;">Worklist {{ $res->lims_worklist_id ?: '—' }}</span>
                                    @elseif($actionable)
                                        <div class="att-wl-wrap">
                                            <span class="att-wl-px">EM-</span>
                                            <input type="text" class="att-wl" placeholder="worklist #"
                                                   wire:model="worklists.{{ $res->id }}" wire:change="saveWorklist({{ $res->id }})">
                                        </div>
                                    @endif

                                    <div class="att-toggles">
                                        @if($actionable)
                                            <button type="button" wire:click="setIntent({{ $res->id }}, 'present')"
                                                    class="att-tog att-att {{ $intent === 'present' ? 'on' : '' }}"><span class="att-box"></span> Present</button>
                                            <button type="button" wire:click="setIntent({{ $res->id }}, 'no_show')"
                                                    class="att-tog att-no {{ $intent === 'no_show' ? 'on' : '' }}"><span class="att-box"></span> No-Show</button>
                                            <button type="button" wire:click="setIntent({{ $res->id }}, 'rescheduled')"
                                                    class="att-tog att-res {{ $intent === 'rescheduled' ? 'on' : '' }}"><span class="att-box"></span> Reschedule</button>
                                            @if($required > 1)
                                                <label style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;color:var(--gqs-text,#1A1A1F);margin-left:8px;{{ $intent === 'present' ? '' : 'opacity:.45;' }}"
                                                       title="Operator performed all remaining runs today (one sample/incubation each)">
                                                    <input type="checkbox" wire:model="performAll.{{ $res->id }}" @if($intent !== 'present') disabled @endif>
                                                    All {{ $required }} Today
                                                </label>
                                            @endif
                                        @elseif($st === 'completed')
                                            <span class="gqs-pill gqs-pill-green">Present</span>
                                            <span class="att-hint">{{ $readyForResults ? 'Ready · evaluate on Lab Review' : ($performed < $required ? 'Incubating · awaiting next run' : 'Incubating · awaiting plates') }}</span>
                                        @else
                                            <span class="gqs-pill {{ $st === 'no_show' ? 'gqs-pill-red' : 'gqs-pill-gold' }}">{{ $st === 'no_show' ? 'No-Show' : 'Rescheduled' }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if(! $slot->attendance_submitted_at)
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:16px;padding-top:14px;border-top:1px solid var(--gqs-border,#E6E6EA);">
                                <button type="button" wire:click="$set('tab', 'reservations')" class="gqs-btn gqs-btn-ghost">Save &amp; Close</button>
                                <button type="button" wire:click="submitRunDay({{ $slot->id }})"
                                        wire:confirm="Submit this run day? Everyone marked Present (with a worklist) is recorded as performed, No-Shows and Reschedules are processed, and the day is locked."
                                        class="gqs-btn gqs-btn-primary">Submit Attendance</button>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    {{-- Result evaluation now lives on the Lab Review page (QCM). --}}

    </div>{{-- end roster tab wrapper --}}
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
        .rd-act-green{background:#2E7D5B;} .rd-act-green:hover{background:#246148;}
        .rd-act-magenta{background:#A4123F;} .rd-act-magenta:hover{background:#85102F;}
    </style>
</x-filament-panels::page>
