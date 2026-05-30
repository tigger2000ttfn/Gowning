<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Qualification Run Day', 'subtitle' => "Who's scheduled for each run slot.", 'icon' => 'heroicon-o-clipboard-document-list'])

    <div x-data="{
            showResults: false, resId: null, resName: '', worklist: '', overall: 'pass',
            open(id, name) { this.resId = id; this.resName = name; this.worklist = ''; this.overall = 'pass'; this.showResults = true; },
            submit() { $wire.enterResults(this.resId, this.overall, this.worklist); this.showResults = false; }
        }">

    <div style="margin-bottom:18px;max-width:560px;display:flex;gap:14px;align-items:end;">
        <div style="flex:1;max-width:260px;">
            <label style="font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--gqs-text-dim,#6A6A72);display:block;margin-bottom:6px;">Select Date</label>
            <input type="date" wire:model.live="date"
                   style="width:100%;padding:10px 12px;border:1px solid var(--gqs-border,#C4C4CC);border-radius:9px;background:var(--gqs-surface,#fff);color:var(--gqs-text,#1A1A1F);">
        </div>
        <a href="{{ route('print.run-day', ['date' => $date]) }}" target="_blank"
           style="display:inline-flex;align-items:center;gap:7px;padding:10px 16px;background:#A4123F;color:#fff;border-radius:9px;font-weight:700;font-size:13px;text-decoration:none;">
            <x-filament::icon icon="heroicon-m-printer" style="width:16px;height:16px;"/> Print Roster (PDF)
        </a>
    </div>

    @php $slots = $this->slots; @endphp

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

    <style>
        .rd-act{font-size:12px;font-weight:700;padding:5px 12px;border-radius:7px;border:none;cursor:pointer;color:#fff;}
        .rd-act-green{background:#2E7D5B;} .rd-act-green:hover{background:#246148;}
        .rd-act-magenta{background:#A4123F;} .rd-act-magenta:hover{background:#85102F;}
    </style>
</x-filament-panels::page>
