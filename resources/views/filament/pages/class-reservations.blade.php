<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Gowning Class Reservations', 'subtitle' => 'Enrollments grouped by class session.', 'icon' => 'heroicon-o-calendar-days'])

    @php $groups = $this->getGroupedBySession();
        $totalEnrolled = collect($groups)->sum(fn ($g) => count($g['rows']));
        $sessionCount = count($groups);
    @endphp

    <div class="gqs-stats">
        <div class="gqs-stat green"><div class="n">{{ $totalEnrolled }}</div><div class="l">Total Enrolled</div><span class="wm"><x-filament::icon icon="heroicon-o-user-group"/></span></div>
        <div class="gqs-stat magenta"><div class="n">{{ $sessionCount }}</div><div class="l">Upcoming Sessions</div><span class="wm"><x-filament::icon icon="heroicon-o-academic-cap"/></span></div>
        <div class="gqs-stat charcoal"><div class="n">{{ $sessionCount ? round($totalEnrolled / max($sessionCount,1), 1) : 0 }}</div><div class="l">Avg Per Session</div><span class="wm"><x-filament::icon icon="heroicon-o-users"/></span></div>
    </div>

    @forelse ($groups as $group)
        <div class="gqs-panel">
            <div class="gqs-panel-head" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;"><x-filament::icon icon="heroicon-m-academic-cap"/> {{ $group['title'] }}
                <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;display:flex;align-items:center;gap:8px;">
                    {{ count($group['rows']) }} enrolled · {{ $group['seats'] }}/{{ $group['capacity'] }} seats left
                    @if($group['submitted'])<span class="gqs-pill gqs-pill-green">Submitted</span>@endif
                </span>
            </div>
            <div class="gqs-panel-body">
                @if(empty($group['rows']))
                    <div class="gqs-empty">No One Enrolled Yet.</div>
                @else
                    @if(! $group['submitted'])
                        <div style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                            <a href="{{ route('print.class-attendance', $group['id']) }}" target="_blank" class="gqs-btn gqs-btn-ghost" style="text-decoration:none;">Print Attendance Form</a>
                            <button type="button" wire:click="submitAttendance({{ $group['id'] }})"
                                    wire:confirm="Submit this session's attendance to QA? It will be locked and attendees sent to the QA Classroom Approval queue."
                                    class="gqs-btn gqs-btn-primary">Submit Attendance</button>
                        </div>
                    @else
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                            <span style="font-size:12px;color:var(--gqs-text-dim,#6A6A72);">Submitted {{ $group['submitted_at'] }} · awaiting QA classroom approval.</span>
                            <button type="button" wire:click="reopenAttendance({{ $group['id'] }})"
                                    wire:confirm="Reopen this session? Attendees not yet QA-approved return to draft." class="gqs-btn gqs-btn-ghost">Reopen</button>
                        </div>
                    @endif
                    <table class="gqs-tbl">
                        <thead><tr><th>Employee</th><th>Name</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($group['rows'] as $row)
                                <tr>
                                    <td style="font-weight:600;">{{ $row['employee_id'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td><span class="gqs-pill {{ [
                                        'signed_up' => 'gqs-pill-purple', 'attended' => 'gqs-pill-gold',
                                        'pending_qa' => 'gqs-pill-purple', 'completed' => 'gqs-pill-green', 'no_show' => 'gqs-pill-red',
                                    ][$row['status']] ?? 'gqs-pill-purple' }}">{{ str_replace('_',' ',$row['status']) }}</span></td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        @if(! $group['submitted'] && in_array($row['status'], ['signed_up','attended','no_show']))
                                            <button wire:click="setStatus({{ $row['id'] }}, 'attended')" class="sb-act sb-act-green">Attended</button>
                                            <button wire:click="setStatus({{ $row['id'] }}, 'no_show')" class="sb-act sb-act-red">No-Show</button>
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

    <style>
        .sb-act{font-size:12px;font-weight:700;padding:4px 11px;border-radius:7px;border:none;cursor:pointer;margin-left:5px;color:#fff;}
        .sb-act-green{background:#2E7D5B;} .sb-act-green:hover{background:#246148;}
        .sb-act-red{background:#C8102E;} .sb-act-red:hover{background:#9A0C23;}
    </style>
</x-filament-panels::page>
