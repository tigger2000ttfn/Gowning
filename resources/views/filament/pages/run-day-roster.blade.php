<x-filament-panels::page>
    <div class="sb-headrow">
        <div class="sb-headrow-title">
            <span class="pg-head-ico"><x-filament::icon icon="heroicon-o-calendar-days" /></span>
            <div class="pg-head-tx" style="min-width:0;">
                <h1>Run Scheduler</h1>
                <p>Schedule cleanroom run days and run the roster on the day.</p>
            </div>
        </div>
        <div class="sb-headrow-filters">
            <div class="gqs-tabs">
                <button type="button" wire:click="$set('tab','schedule')" class="gqs-tab @if($tab==='schedule') on @endif">Schedule</button>
                <button type="button" wire:click="$set('tab','roster')" class="gqs-tab @if($tab==='roster') on @endif">Roster</button>
            </div>
        </div>
    </div>

    @if($tab === 'schedule')
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
                        <thead><tr><th>Date</th><th>Time</th><th>Cleanroom</th><th>Analyst</th><th>Booked / Capacity</th><th></th></tr></thead>
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
                        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                            <button type="button" wire:click="$set('showAddSlot', false)" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Cancel</button>
                            <button type="button" wire:click="addSlot" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;border:none;font-weight:700;cursor:pointer;">Add Run Day</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @else
        {{-- ROSTER TAB --}}
        <div x-data="{
                showResults: false, resId: null, resName: '', worklist: '', overall: 'pass',
                open(id, name) { this.resId = id; this.resName = name; this.worklist = ''; this.overall = 'pass'; this.showResults = true; },
                submit() { $wire.enterResults(this.resId, this.overall, this.worklist); this.showResults = false; }
            }">

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
                    @if ($slot->reservations->isEmpty())<div class="gqs-empty">No One Scheduled Yet.</div>@else
                        <table class="gqs-tbl">
                            <thead><tr><th>#</th><th>Employee ID</th><th>Name</th><th>Status</th><th>Run</th><th>Results</th></tr></thead>
                            <tbody>@foreach ($slot->reservations as $i => $res)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td style="font-weight:600;">{{ $res->personnel?->employee_id }}</td>
                                    <td>{{ $res->personnel?->full_name }}</td>
                                    <td><span class="gqs-pill {{ $res->status === 'completed' ? 'gqs-pill-green' : 'gqs-pill-gold' }}">{{ ucfirst($res->status) }}</span></td>
                                    <td>
                                        @if($res->status !== 'completed')
                                            <button wire:click="markPerformed({{ $res->id }})" class="rd-act rd-act-green">Mark Performed</button>
                                        @else
                                            <span style="color:#2E7D5B;font-weight:600;font-size:12.5px;">Performed</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" @click="open({{ $res->id }}, '{{ addslashes($res->personnel?->full_name ?? 'Operator') }}')" class="rd-act rd-act-magenta">Enter Results</button>
                                    </td>
                                </tr>
                            @endforeach</tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    {{-- Results entry modal (Alpine) --}}
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

    </div>
    @endif

    <style>
        .rd-act{font-size:12px;font-weight:700;padding:5px 12px;border-radius:7px;border:none;cursor:pointer;color:#fff;}
        .rd-act-green{background:#2E7D5B;} .rd-act-green:hover{background:#246148;}
        .rd-act-magenta{background:#A4123F;} .rd-act-magenta:hover{background:#85102F;}
    </style>
</x-filament-panels::page>
