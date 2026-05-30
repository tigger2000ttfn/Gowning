<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Gowning Class Reservations', 'subtitle' => 'Enrollments grouped by class session.', 'icon' => 'heroicon-o-calendar-days'])

    @php $groups = $this->getGroupedBySession(); @endphp

    @forelse ($groups as $group)
        <div class="gqs-panel">
            <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-academic-cap"/> {{ \Illuminate\Support\Str::title($group['title']) }}
                <span style="margin-left:auto;font-size:12px;font-weight:600;opacity:.9;">{{ count($group['rows']) }} enrolled · {{ $group['seats'] }}/{{ $group['capacity'] }} seats left</span>
            </div>
            <div class="gqs-panel-body">
                @if(empty($group['rows']))
                    <div class="gqs-empty">No One Enrolled Yet.</div>
                @else
                    <table class="gqs-tbl">
                        <thead><tr><th>Employee</th><th>Name</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($group['rows'] as $row)
                                <tr>
                                    <td style="font-weight:600;">{{ $row['employee_id'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td><span class="gqs-pill {{ [
                                        'signed_up' => 'gqs-pill-purple', 'attended' => 'gqs-pill-gold',
                                        'completed' => 'gqs-pill-green', 'no_show' => 'gqs-pill-red',
                                    ][$row['status']] ?? 'gqs-pill-purple' }}">{{ \Illuminate\Support\Str::title(str_replace('_',' ',$row['status'])) }}</span></td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        @if($row['status'] === 'signed_up')
                                            <button wire:click="setStatus({{ $row['id'] }}, 'completed')" class="sb-act sb-act-green">Mark Completed</button>
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
