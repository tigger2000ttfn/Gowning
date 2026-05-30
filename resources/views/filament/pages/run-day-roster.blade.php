<x-filament-panels::page>
    <div class="sb-headrow">
        <div class="sb-headrow-title">
            <span class="pg-head-ico"><x-filament::icon icon="heroicon-o-calendar-days" /></span>
            <div class="pg-head-tx" style="min-width:0;">
                <h1>Run Scheduler</h1>
                <p>@switch($tab)
                    @case('overview')Who needs a run, and the state of the pipeline. Book waiting people here.@break
                    @case('schedule')Create and manage cleanroom run days. Set capacity, analyst, and repeat dates.@break
                    @case('reservations')Approve requests and book people onto run days.@break
                    @case('roster')Take attendance on the day: mark Present, No-Show, or Reschedule, then enter results.@break
                @endswitch</p>
            </div>
        </div>
        <div class="sb-headrow-filters">
            <div class="gqs-tabs">
                <button type="button" wire:click="$set('tab','overview')" class="gqs-tab @if($tab==='overview') on @endif">Overview</button>
                <button type="button" wire:click="$set('tab','schedule')" class="gqs-tab @if($tab==='schedule') on @endif">Run Days</button>
                <button type="button" wire:click="$set('tab','reservations')" class="gqs-tab @if($tab==='reservations') on @endif">Reservations</button>
                <button type="button" wire:click="$set('tab','roster')" class="gqs-tab @if($tab==='roster') on @endif">Attendance</button>
            </div>
        </div>
    </div>

    @if($tab === 'overview')
        {{-- OVERVIEW TAB: mini-dashboard for the run pipeline --}}
        @php $stats = $this->overviewStats(); $waiting = $this->getWaiting(); @endphp
        <div class="rs-stats">
            @foreach($stats as [$label, $value, $icon, $color])
                <div class="rs-stat">
                    <div class="rs-stat-ico" style="background:{{ $color }}1a;color:{{ $color }};"><x-filament::icon :icon="$icon" style="width:20px;height:20px;"/></div>
                    <div>
                        <div class="rs-stat-val">{{ $value }}</div>
                        <div class="rs-stat-lbl">{{ $label }}</div>
                    </div>
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
                                    <td style="font-weight:700;">{{ $d->slot_date->format('D, M j, Y') }}</td>
                                    <td>{{ $d->start_time ? \Illuminate\Support\Carbon::parse($d->start_time)->format('g:i A') : '—' }}@if($d->end_time) – {{ \Illuminate\Support\Carbon::parse($d->end_time)->format('g:i A') }}@endif</td>
                                    <td>{{ $d->cleanroom ?: '—' }}</td>
                                    <td>{{ $d->analyst?->name ?? 'Unassigned' }}</td>
                                    <td><span class="gqs-pill {{ $d->seats_left > 0 ? 'gqs-pill-green' : 'gqs-pill-gold' }}">{{ $d->booked }} / {{ $d->capacity }}</span></td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <button wire:click="viewRoster('{{ $d->slot_date->toDateString() }}')" class="rd-act rd-act-magenta">Open Roster</button>
                                        <button wire:click="cancelSlotDay({{ $d->id }})" wire:confirm="Cancel this run day? Booked operators will be rescheduled or flagged." class="rd-act" style="background:#C8102E;">Cancel</button>
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
            <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="$set('showAddSlot', false)">
                <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:460px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                    <div style="background:#1C1C21;color:#fff;padding:16px 20px;border-radius:14px 14px 0 0;font-weight:800;font-size:16px;">Add Run Day</div>
                    <div style="padding:18px 20px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div><label class="gqs-flbl">Date</label><input type="date" wire:model="newDate" class="gqs-fld"></div>
                            <div><label class="gqs-flbl">Cleanroom</label>
                                <select wire:model="newCleanroom" class="gqs-fld"><option value="">Select...</option>
                                    @foreach($this->cleanroomOptions() as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                                </select></div>
                            <div><label class="gqs-flbl">Start</label><input type="time" wire:model="newStart" class="gqs-fld"></div>
                            <div><label class="gqs-flbl">End</label><input type="time" wire:model="newEnd" class="gqs-fld"></div>
                            <div><label class="gqs-flbl">Capacity</label><input type="number" min="1" wire:model="newCapacity" placeholder="default" class="gqs-fld"></div>
                            <div><label class="gqs-flbl">Analyst</label>
                                <select wire:model="newAnalystId" class="gqs-fld"><option value="">Unassigned</option>
                                    @foreach($this->analystOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                </select></div>
                        </div>
                        <div style="margin-top:12px;"><label class="gqs-flbl">Notes</label><input type="text" wire:model="newNotes" placeholder="Optional" class="gqs-fld"></div>

                        {{-- Recurrence --}}
                        <label style="display:flex;align-items:center;gap:8px;margin-top:14px;font-size:13px;font-weight:600;cursor:pointer;color:var(--gqs-text,#1A1A1F);">
                            <input type="checkbox" wire:model.live="repeat"> Repeat this run day
                        </label>
                        @if($repeat)
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;padding:12px;background:var(--gqs-surface-2,#F5F5F7);border-radius:9px;">
                                <div><label class="gqs-flbl">Pattern</label>
                                    <select wire:model="repeatPattern" class="gqs-fld">
                                        <option value="weekly">Weekly</option>
                                        <option value="biweekly">Every 2 Weeks</option>
                                        <option value="monthly">Monthly</option>
                                    </select></div>
                                <div><label class="gqs-flbl">Repeat Until</label><input type="date" wire:model="repeatUntil" class="gqs-fld"></div>
                            </div>
                        @endif
                        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                            <button type="button" wire:click="$set('showAddSlot', false)" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Cancel</button>
                            <button type="button" wire:click="addSlot" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;border:none;font-weight:700;cursor:pointer;">@if($repeat)Generate Run Days @else Add Run Day @endif</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @elseif($tab === 'reservations')
        {{-- RESERVATIONS TAB --}}
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
            <select wire:model.live="resStatusFilter" class="gqs-fld" style="max-width:200px;">
                <option value="">All Statuses</option>
                <option value="requested">Requested</option>
                <option value="approved">Approved</option>
                <option value="completed">Completed</option>
                <option value="no_show">No-Show</option>
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
                    <span style="font-size:12px;opacity:.9;">{{ count($g['rows']) }} booked</span>
                </div>
                <div class="gqs-panel-body" style="padding:0;">
                    <table class="gqs-tbl">
                        <thead><tr><th>Employee ID</th><th>Name</th><th>Cleanroom</th><th>Time</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @foreach($g['rows'] as $r)
                                <tr>
                                    <td style="font-weight:600;">{{ $r['employee_id'] }}</td>
                                    <td>{{ $r['name'] }}</td>
                                    <td>{{ $r['cleanroom'] ?: '—' }}</td>
                                    <td>{{ $r['time'] ?? '—' }}</td>
                                    <td><span class="gqs-pill {{ $r['status'] === 'completed' ? 'gqs-pill-green' : ($r['status'] === 'no_show' ? 'gqs-pill-red' : ($r['status'] === 'approved' ? 'gqs-pill-gold' : '')) }}">{{ ucfirst(str_replace('_',' ',$r['status'])) }}</span></td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        @if($r['status'] === 'requested')
                                            <button wire:click="approveReservation({{ $r['id'] }})" class="rd-act rd-act-green">Approve</button>
                                        @endif
                                        @if(in_array($r['status'], ['requested','approved']))
                                            <button wire:click="markNoShow({{ $r['id'] }})" wire:confirm="Mark as no-show? They will be returned for rebooking." class="rd-act" style="background:#C8102E;">No-Show</button>
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
            <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="$set('showAddRes', false)">
                <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:440px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                    <div style="background:#1C1C21;color:#fff;padding:16px 20px;border-radius:14px 14px 0 0;font-weight:800;font-size:16px;">Book A Person Onto A Run Day</div>
                    <div style="padding:18px 20px;">
                        <label class="gqs-flbl">Person</label>
                        <select wire:model="addResPersonnelId" class="gqs-fld" style="margin-bottom:14px;">
                            <option value="">Select a person...</option>
                            @foreach($this->bookablePersonnel() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                        <label class="gqs-flbl">Run Day</label>
                        <select wire:model="addResSlotId" class="gqs-fld">
                            <option value="">Select an open run day...</option>
                            @foreach($this->openSlotsForBooking() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                            <button type="button" wire:click="$set('showAddRes', false)" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Cancel</button>
                            <button type="button" wire:click="addReservation" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;border:none;font-weight:700;cursor:pointer;">Book</button>
                        </div>
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
                <input type="date" wire:model.live="date" class="gqs-fld">
            </div>
            <a href="{{ route('print.run-day', ['date' => $date]) }}" target="_blank"
               style="display:inline-flex;align-items:center;gap:7px;padding:10px 16px;background:#A4123F;color:#fff;border-radius:9px;font-weight:700;font-size:13px;text-decoration:none;">
                <x-filament::icon icon="heroicon-m-printer" style="width:16px;height:16px;"/> Print Roster (PDF)
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
                        {{ $slot->cleanroom }}@if($slot->start_time) · {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }}@endif
                    </span>
                    <span style="font-size:12px;font-weight:600;opacity:.92;">{{ $slot->reservations->count() }} attending · cap {{ $slot->capacity }}</span>
                </div>
                <div class="gqs-panel-body">
                    @if ($slot->reservations->isEmpty())<div class="gqs-empty">No one scheduled yet.</div>@else
                        <table class="gqs-tbl">
                            <thead><tr><th>#</th><th>Employee ID</th><th>Name</th><th>Attendance</th><th>Runs</th><th>Actions</th></tr></thead>
                            <tbody>@foreach ($slot->reservations as $i => $res)
                                @php
                                    $rq = \App\Models\Qualification::where('personnel_id', $res->personnel_id)->first();
                                    $adv = app(\App\Services\RunCycleAdvancer::class);
                                    $performed = $rq ? $adv->cycleRuns($rq)->count() : 0;
                                    $required = max(1, (int) ($rq->runs_required ?? 1));
                                    $readyForResults = $rq && $rq->workflow_stage === \App\Enums\WorkflowStage::AwaitingResults;
                                    $st = $res->status instanceof \BackedEnum ? $res->status->value : $res->status;
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td style="font-weight:600;">{{ $res->personnel?->employee_id }}</td>
                                    <td>{{ $res->personnel?->full_name }}</td>
                                    <td>
                                        <span class="gqs-pill {{ $st === 'completed' ? 'gqs-pill-green' : ($st === 'no_show' ? 'gqs-pill-red' : ($st === 'rescheduled' ? 'gqs-pill-gold' : '')) }}">
                                            {{ $st === 'completed' ? 'Present' : ($st === 'no_show' ? 'No-Show' : ($st === 'rescheduled' ? 'Rescheduled' : 'Scheduled')) }}
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap;">{{ $performed }} / {{ $required }}</td>
                                    <td style="white-space:nowrap;">
                                        @if($st !== 'completed' && $st !== 'no_show' && $st !== 'rescheduled')
                                            <button wire:click="markPerformed({{ $res->id }})" class="rd-act rd-act-green">Present</button>
                                            <button wire:click="rosterNoShow({{ $res->id }})" wire:confirm="Mark as no-show? They will be returned for rebooking." class="rd-act" style="background:#C8102E;">No-Show</button>
                                            <button wire:click="rosterReschedule({{ $res->id }})" wire:confirm="Reschedule to the next available run day?" class="rd-act" style="background:#C79A2E;">Reschedule</button>
                                        @elseif($readyForResults)
                                            <button type="button" @click="$dispatch('open-results', { id: {{ $res->id }}, name: '{{ addslashes($res->personnel?->full_name ?? 'Operator') }}' })" class="rd-act rd-act-magenta">Enter Results</button>
                                        @elseif($st === 'completed')
                                            <span style="color:var(--gqs-text-dim,#6A6A72);font-size:12px;">{{ $performed < $required ? 'Incubating · awaiting next run' : 'Incubating · awaiting plates' }}</span>
                                        @else
                                            <span style="color:var(--gqs-text-dim,#6A6A72);font-size:12px;">{{ $st === 'no_show' ? 'No-show' : 'Rescheduled' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach</tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    {{-- Results entry modal (Alpine, self-contained, listens for open-results event) --}}
    <div x-data="{ showResults: false, resId: null, resName: '', worklist: '', overall: 'pass',
            submit() { $wire.enterResults(this.resId, this.overall, this.worklist); this.showResults = false; } }"
         @open-results.window="resId = $event.detail.id; resName = $event.detail.name; worklist = ''; overall = 'pass'; showResults = true">
    <div x-show="showResults" x-cloak style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" @click.self="showResults=false">
        <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:420px;max-width:92vw;padding:22px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
            <h3 style="font-weight:800;font-size:17px;margin:0 0 4px;color:var(--gqs-text,#1A1A1F);">Enter LIMS Results</h3>
            <p style="font-size:13px;color:var(--gqs-text-dim,#6A6A72);margin:0 0 16px;" x-text="resName"></p>

            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#6A6A72);display:block;margin-bottom:5px;">LIMS Worklist ID</label>
            <input type="text" x-model="worklist" placeholder="Worklist / batch reference"
                   style="width:100%;padding:9px 11px;border:1px solid var(--gqs-border,#C4C4CC);border-radius:8px;margin-bottom:14px;background:var(--gqs-surface,#fff);color:var(--gqs-text,#1A1A1F);">

            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#6A6A72);display:block;margin-bottom:7px;">Overall Result</label>
            <div style="display:flex;gap:8px;margin-bottom:20px;">
                <button type="button" @click="overall='pass'" :style="overall==='pass' ? 'background:#2E7D5B;color:#fff;' : 'background:transparent;color:#2E7D5B;border:1px solid #2E7D5B;'" style="flex:1;padding:10px;border-radius:8px;font-weight:700;cursor:pointer;border:1px solid transparent;">Pass</button>
                <button type="button" @click="overall='fail'" :style="overall==='fail' ? 'background:#C8102E;color:#fff;' : 'background:transparent;color:#C8102E;border:1px solid #C8102E;'" style="flex:1;padding:10px;border-radius:8px;font-weight:700;cursor:pointer;border:1px solid transparent;">Fail</button>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" @click="showResults=false" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Cancel</button>
                <button type="button" @click="submit()" style="padding:9px 16px;border-radius:8px;border:none;background:#A4123F;color:#fff;font-weight:700;cursor:pointer;">Release Results</button>
            </div>
        </div>
    </div>
    </div>{{-- end results-modal x-data --}}

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
