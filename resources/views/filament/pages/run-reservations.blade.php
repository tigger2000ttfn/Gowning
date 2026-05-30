<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Run Reservations', 'icon' => 'heroicon-o-calendar-days'])

    @php $groups = $this->getGroupedByDay();
        $total = collect($groups)->sum(fn ($g) => count($g['rows']));
        $dayCount = count($groups);
    @endphp

    <div class="gqs-stats">
        <div class="gqs-stat green"><div class="n">{{ $total }}</div><div class="l">Booked Runs</div><span class="wm"><x-filament::icon icon="heroicon-o-user-group"/></span></div>
        <div class="gqs-stat magenta"><div class="n">{{ $dayCount }}</div><div class="l">Run Days</div><span class="wm"><x-filament::icon icon="heroicon-o-calendar-days"/></span></div>
        <div class="gqs-stat charcoal"><div class="n">{{ $dayCount ? round($total / max($dayCount,1), 1) : 0 }}</div><div class="l">Avg Per Day</div><span class="wm"><x-filament::icon icon="heroicon-o-users"/></span></div>
    </div>

    <div style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);margin-bottom:12px;">Manage who is booked onto each run day. Move, reschedule, or cancel a booking here. Taking attendance and recording results is done on the Run Scheduler.</div>

    @forelse ($groups as $group)
        <div class="gqs-panel">
            <div class="gqs-panel-head" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;"><x-filament::icon icon="heroicon-m-calendar-days"/> {{ $group['title'] }}
                <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;">{{ count($group['rows']) }} booked</span>
            </div>
            <div class="gqs-panel-body">
                @if(empty($group['rows']))
                    <div class="gqs-empty">No One Booked Yet.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Employee</th><th>Name</th><th>Status</th><th style="text-align:right;">Manage</th></tr></thead>
                        <tbody>
                            @foreach ($group['rows'] as $row)
                                <tr>
                                    <td style="font-weight:600;">{{ $row['employee_id'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td><span class="gqs-pill {{ [
                                        'requested' => 'gqs-pill-purple', 'approved' => 'gqs-pill-green',
                                    ][$row['status']] ?? 'gqs-pill-purple' }}">{{ ucwords(str_replace('_',' ',$row['status'])) }}</span></td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <button wire:click="openMove({{ $row['id'] }})" class="sb-act sb-act-magenta">Move</button>
                                        <button wire:click="askConfirm('reschedule', {{ $row['id'] }}, 'Reschedule Booking', 'Move {{ addslashes($row['name']) }} to the next available run day with an open seat?', 'Reschedule')" class="sb-act" style="background:#C79A2E;">Reschedule</button>
                                        <button wire:click="askConfirm('cancelBooking', {{ $row['id'] }}, 'Cancel Booking', 'Cancel this run booking for {{ addslashes($row['name']) }}?', 'Cancel Booking', true)" class="sb-act sb-act-red">Cancel</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @empty
        <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No Run Bookings Yet.</div></div>
    @endforelse

    {{-- in-app confirmation modal --}}
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

    {{-- Move booking modal --}}
    @if($showMove)
        <div class="gqs-modal-overlay" wire:click.self="$set('showMove', false)">
            <div class="gqs-modal">
                <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-arrows-right-left"/></span>Move Booking</div>
                <div class="gqs-modal-body">
                    <p style="font-size:13px;color:var(--gqs-text-dim,#6A6A72);margin:0;">{{ $moveName }}</p>
                    <div>
                        <label class="gqs-flbl">Move To Run Day</label>
                        <select wire:model="moveSlotId" class="gqs-fld">
                            <option value="">Select A Run Day...</option>
                            @foreach($this->openSlots() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="gqs-modal-foot">
                    <button type="button" wire:click="$set('showMove', false)" class="gqs-btn gqs-btn-ghost">Cancel</button>
                    <button type="button" wire:click="move" class="gqs-btn gqs-btn-primary">Move</button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
