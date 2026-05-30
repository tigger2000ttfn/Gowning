@extends('print.layout', ['title' => 'Run Qualification Attendance', 'org' => $org, 'site' => $site])
@section('body')
    <div class="doc-title">Gowning Qualification Run Attendance, {{ $date->gmpL() }}</div>

    @forelse($slots as $slot)
        <div class="sec">
            <div class="sec-h">{{ $slot->cleanroom ?: 'Run Day' }}@if($slot->start_time) · {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('H:i') }}@endif
                @if($slot->analyst) · Analyst: {{ $slot->analyst->name }} @endif
                · {{ $slot->reservations->count() }} of {{ $slot->capacity }}</div>
            @if($slot->reservations->isEmpty())
                <div class="empty">No one scheduled.</div>
            @else
                <table class="data">
                    <thead><tr>
                        <th style="width:24px;">#</th>
                        <th>Name (Printed)</th>
                        <th style="width:78px;">Employee ID</th>
                        <th>Department</th>
                        <th style="width:118px;">Runs Performed Today</th>
                        <th style="width:110px;">LIMS Worklist</th>
                        <th style="width:150px;">Signature</th>
                        <th style="width:78px;">Date</th>
                    </tr></thead>
                    <tbody>
                        @foreach($slot->reservations as $i => $res)
                            @php
                                $q = \App\Models\Qualification::where('personnel_id', $res->personnel_id)->first();
                                $required = max(1, (int) ($q->runs_required ?? 1));
                                $todayRuns = \App\Models\QualificationRun::where('personnel_id', $res->personnel_id)
                                    ->whereDate('run_date', $date->toDateString())->get();
                                $doneToday = $todayRuns->count();
                                $wls = $todayRuns->pluck('lims_worklist_id')->filter()->unique()->implode(', ');
                                $runsLbl = $doneToday
                                    ? ($doneToday . ' of ' . $required . ($doneToday >= $required ? ' · all' : ''))
                                    : ('0 of ' . $required);
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $res->personnel?->full_name }}</td>
                                <td>{{ $res->personnel?->employee_id }}</td>
                                <td>{{ $res->personnel?->department }}</td>
                                <td>{{ $runsLbl }}</td>
                                <td>{{ $wls ?: ($res->lims_worklist_id ?? '') }}</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <div class="empty">No run days scheduled for this date.</div>
    @endforelse

    <table class="sign-tbl"><tr>
        <td style="width:50%;"><div class="sign-line"></div><div class="sign-lbl">QC Micro Analyst, Signature / Date</div></td>
        <td style="width:50%;"><div class="sign-line"></div><div class="sign-lbl">Reviewed By, Signature / Date</div></td>
    </tr></table>
@endsection
