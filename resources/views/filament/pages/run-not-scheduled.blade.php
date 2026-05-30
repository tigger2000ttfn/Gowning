<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Awaiting Run Booking', 'icon' => 'heroicon-o-calendar'])

    @php $waiting = $this->getWaiting(); @endphp

    <div class="gqs-stats">
        <div class="gqs-stat magenta"><div class="n">{{ $waiting->count() }}</div><div class="l">Awaiting Booking</div><span class="wm"><x-filament::icon icon="heroicon-o-calendar"/></span></div>
        <div class="gqs-stat purple"><div class="n">{{ $waiting->where('is_requal', true)->count() }}</div><div class="l">Requalifications</div><span class="wm"><x-filament::icon icon="heroicon-o-arrow-path"/></span></div>
    </div>

    <div class="gqs-panel">
        <div class="gqs-panel-head"><x-filament::icon icon="heroicon-m-calendar"/> Ready, Not Yet Booked</div>
        <div class="gqs-panel-body">
            @if($waiting->isEmpty())
                <div class="gqs-empty">Everyone Who Is Class Complete Has A Run Booked.</div>
            @else
                <table class="gqs-tbl">
                    <thead><tr><th>Employee</th><th>Name</th><th>Department</th><th>Runs Needed</th><th>Type</th><th>Class On File</th><th>Waiting Since</th></tr></thead>
                    <tbody>
                        @foreach ($waiting as $row)
                            <tr>
                                <td style="font-weight:600;">{{ $row->employee_id }}</td>
                                <td>{{ $row->name }}</td>
                                <td>{{ $row->department ?? '-' }}</td>
                                <td><span class="gqs-pill {{ $row->runs_required >= 3 ? 'gqs-pill-gold' : 'gqs-pill-green' }}">{{ $row->runs_required }} {{ \Illuminate\Support\Str::plural('run', $row->runs_required) }}</span></td>
                                <td>{{ $row->is_requal ? 'Requalification' : 'Initial' }}</td>
                                <td>{{ $row->class_date?->format('M j, Y') ?? '-' }}</td>
                                <td style="font-size:12.5px;color:var(--gqs-text-dim,#6A6A72);">{{ $row->since?->setTimezone('America/New_York')?->diffForHumans() ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-filament-panels::page>
