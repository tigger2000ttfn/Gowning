<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Run Reservation Board', 'icon' => 'heroicon-o-calendar-days'])

    {{-- Filter / search bar + manual add --}}
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:16px;">
        <div style="flex:1;min-width:200px;max-width:320px;">
            <label class="gqs-flbl">Search Person</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Name or employee ID"
                   class="gqs-fld">
        </div>
        <div style="min-width:160px;">
            <label class="gqs-flbl">Status</label>
            <select wire:model.live="statusFilter" class="gqs-fld">
                <option value="">All Statuses</option>
                <option value="requested">Requested</option>
                <option value="approved">Approved</option>
                <option value="completed">Completed</option>
                <option value="no_show">No-Show</option>
            </select>
        </div>
        <button type="button" wire:click="$set('showAdd', true)"
                style="display:inline-flex;align-items:center;gap:7px;padding:10px 16px;background:#A4123F;color:#fff;border:none;border-radius:9px;font-weight:700;font-size:13px;cursor:pointer;">
            <x-filament::icon icon="heroicon-m-plus" style="width:16px;height:16px;"/> Add Reservation
        </button>
    </div>

    @php $groups = $this->getGroupedByDay(); @endphp

    {{-- Manual add-reservation modal --}}
    @if($showAdd)
        <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.5);" wire:click.self="$set('showAdd', false)">
            <div style="background:var(--gqs-surface,#fff);border-radius:14px;width:440px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <div style="background:#1C1C21;color:#fff;padding:16px 20px;border-radius:14px 14px 0 0;font-weight:800;font-size:16px;">Add Run Reservation</div>
                <div style="padding:18px 20px;">
                    <label class="gqs-flbl">Person</label>
                    <select wire:model="addPersonnelId" class="gqs-fld" style="margin-bottom:14px;">
                        <option value="">Select a person...</option>
                        @foreach($this->bookablePersonnel() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                    <label class="gqs-flbl">Run Day</label>
                    <select wire:model="addSlotId" class="gqs-fld">
                        <option value="">Select an open run day...</option>
                        @foreach($this->openSlots() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
                        <button type="button" wire:click="$set('showAdd', false)" style="padding:9px 16px;border-radius:8px;border:1px solid var(--gqs-border,#C4C4CC);background:transparent;color:var(--gqs-text,#1A1A1F);font-weight:600;cursor:pointer;">Cancel</button>
                        <button type="button" wire:click="addReservation" style="padding:9px 18px;border-radius:8px;background:#A4123F;color:#fff;border:none;font-weight:700;cursor:pointer;">Add Reservation</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @forelse ($groups as $group)
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-calendar-days"/> {{ $group['day'] }}
                <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;">{{ count($group['rows']) }} reserved</span>
            </div>
            <div class="gqs-panel-body">
                <table class="gqs-tbl">
                    <thead><tr><th>Employee</th><th>Name</th><th>Cleanroom</th><th>Time</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($group['rows'] as $row)
                            <tr>
                                <td style="font-weight:600;">{{ $row['employee_id'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['cleanroom'] }}</td>
                                <td>{{ $row['time'] ?? '-' }}</td>
                                <td>
                                    <span class="gqs-pill {{ [
                                        'requested' => 'gqs-pill-gold',
                                        'approved'  => 'gqs-pill-green',
                                        'completed' => 'gqs-pill-purple',
                                        'no_show'   => 'gqs-pill-red',
                                        'rejected'  => 'gqs-pill-red',
                                    ][$row['status']] ?? 'gqs-pill-gold' }}">{{ str_replace('_',' ',$row['status']) }}</span>
                                </td>
                                <td style="text-align:right;white-space:nowrap;">
                                    @if($row['status'] === 'requested')
                                        <button wire:click="moveCard({{ $row['id'] }}, 'approved')" class="sb-act sb-act-green">Approve</button>
                                        <button wire:click="moveCard({{ $row['id'] }}, 'rejected')" class="sb-act sb-act-red">Reject</button>
                                    @elseif($row['status'] === 'approved')
                                        <button wire:click="moveCard({{ $row['id'] }}, 'completed')" class="sb-act sb-act-green">Complete</button>
                                        <button wire:click="moveCard({{ $row['id'] }}, 'no_show')" class="sb-act sb-act-red">No-Show</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No Run Reservations Yet.</div></div>
    @endforelse

    <style>
        .sb-act{font-size:12px;font-weight:700;padding:4px 11px;border-radius:7px;border:none;cursor:pointer;margin-left:5px;color:#fff;}
        .sb-act-green{background:#2E7D5B;} .sb-act-green:hover{background:#246148;}
        .sb-act-red{background:#C8102E;} .sb-act-red:hover{background:#9A0C23;}
    </style>
</x-filament-panels::page>
