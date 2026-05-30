<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Qualification Run Reservations', 'subtitle' => 'Reservations grouped by run day. Click status to advance.', 'icon' => 'heroicon-o-calendar-days'])

    @php $groups = $this->getGroupedByDay(); @endphp

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
                                <td>{{ $row['time'] ?? '—' }}</td>
                                <td>
                                    <span class="gqs-pill {{ [
                                        'requested' => 'gqs-pill-gold',
                                        'approved'  => 'gqs-pill-green',
                                        'completed' => 'gqs-pill-purple',
                                        'no_show'   => 'gqs-pill-red',
                                        'rejected'  => 'gqs-pill-red',
                                    ][$row['status']] ?? 'gqs-pill-gold' }}">{{ \Illuminate\Support\Str::title(str_replace('_',' ',$row['status'])) }}</span>
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
