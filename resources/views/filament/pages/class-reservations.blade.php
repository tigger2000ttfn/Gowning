<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Class Reservations', 'icon' => 'heroicon-o-calendar-days'])

    @php $groups = $this->getGroupedBySession();
        $totalEnrolled = collect($groups)->sum(fn ($g) => count($g['rows']));
        $sessionCount = count($groups);
    @endphp

    <div class="gqs-stats">
        <div class="gqs-stat green"><div class="n">{{ $totalEnrolled }}</div><div class="l">Total Enrolled</div><span class="wm"><x-filament::icon icon="heroicon-o-user-group"/></span></div>
        <div class="gqs-stat magenta"><div class="n">{{ $sessionCount }}</div><div class="l">Sessions</div><span class="wm"><x-filament::icon icon="heroicon-o-academic-cap"/></span></div>
        <div class="gqs-stat charcoal"><div class="n">{{ $sessionCount ? round($totalEnrolled / max($sessionCount,1), 1) : 0 }}</div><div class="l">Avg Per Session</div><span class="wm"><x-filament::icon icon="heroicon-o-users"/></span></div>
    </div>

    @forelse ($groups as $group)
        <div class="gqs-panel">
            <div class="gqs-panel-head" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;"><x-filament::icon icon="heroicon-m-academic-cap"/> {{ $group['title'] }}
                <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;display:flex;align-items:center;gap:10px;">
                    {{ count($group['rows']) }} Enrolled · {{ $group['seats'] }}/{{ $group['capacity'] }} Seats Left
                    @if($group['submitted'])<span class="gqs-pill gqs-pill-green">Submitted</span>@endif
                    <a href="{{ \App\Filament\Admin\Pages\ClassScheduler::getUrl() }}?attend={{ $group['id'] }}" class="rd-act rd-act-magenta" style="text-decoration:none;">Attendance Sheet</a>
                </span>
            </div>
            <div class="gqs-panel-body" style="padding:0;">
                @if(empty($group['rows']))
                    <div class="gqs-empty" style="padding:20px;">No One Enrolled Yet.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Employee</th><th>Name</th><th>Status</th><th style="text-align:right;">Manage</th></tr></thead>
                        <tbody>
                            @foreach ($group['rows'] as $row)
                                <tr>
                                    <td style="font-weight:600;">{{ $row['employee_id'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td><span class="gqs-pill {{ [
                                        'signed_up' => 'gqs-pill-purple', 'attended' => 'gqs-pill-gold',
                                        'qcm_reviewed' => 'gqs-pill-purple', 'pending_qa' => 'gqs-pill-purple',
                                        'completed' => 'gqs-pill-green', 'no_show' => 'gqs-pill-red',
                                    ][$row['status']] ?? 'gqs-pill-gray' }}">{{ [
                                        'signed_up' => 'Scheduled', 'attended' => 'Attended', 'qcm_reviewed' => 'QCM Reviewed',
                                        'pending_qa' => 'Pending QA', 'completed' => 'QA Approved', 'no_show' => 'No-Show',
                                    ][$row['status']] ?? ucwords(str_replace('_',' ',$row['status'])) }}</span></td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        @if(! $group['submitted'] && in_array($row['status'], ['signed_up']))
                                            <button wire:click="openMove({{ $row['id'] }})" class="sb-act sb-act-magenta">Move</button>
                                            <button wire:click="askConfirm('reschedule', {{ $row['id'] }}, 'Reschedule Booking', 'Move {{ addslashes($row['name']) }} to the next available session with an open seat?', 'Reschedule')" class="sb-act" style="background:#C79A2E;">Reschedule</button>
                                            <button wire:click="askConfirm('cancelBooking', {{ $row['id'] }}, 'Cancel Booking', 'Cancel this class booking for {{ addslashes($row['name']) }}?', 'Cancel Booking', true)" class="sb-act sb-act-red">Cancel</button>
                                        @elseif(! $this->isSuperUser())
                                            <span style="font-size:11.5px;color:var(--gqs-text-dim,#6A6A72);">Locked</span>
                                        @endif
                                        @if($this->isSuperUser())
                                            <button wire:click="askConfirm('deleteEnrollment', {{ $row['id'] }}, 'Delete Enrollment', 'Permanently delete {{ addslashes($row['name']) }}\'s enrollment record? Use this to fix stuck or duplicate entries. This cannot be undone.', 'Delete Record', true)" class="sb-act" style="background:#1C1C21;" title="Super user: hard delete this enrollment record">&times; Delete</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @empty
        <div class="gqs-panel"><div class="gqs-empty" style="padding:28px;">No Class Sessions Scheduled.</div></div>
    @endforelse

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

    {{-- Move booking modal --}}
    @if($showMove)
        <div class="gqs-modal-overlay" wire:click.self="$set('showMove', false)">
            <div class="gqs-modal">
                <div class="gqs-modal-head"><span class="gqs-modal-ico"><x-filament::icon icon="heroicon-m-arrows-right-left"/></span>Move Booking</div>
                <div class="gqs-modal-body">
                    <p style="font-size:13px;color:var(--gqs-text-dim,#6A6A72);margin:0;">{{ $moveName }}</p>
                    <div>
                        <label class="gqs-flbl">Move To Session</label>
                        <select wire:model="moveSessionId" class="gqs-fld">
                            <option value="">Select A Session...</option>
                            @foreach($this->openSessions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
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
