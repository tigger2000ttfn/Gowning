@extends('print.layout', ['title' => 'Run Day Roster', 'org' => $org, 'site' => $site])
@section('body')
    <div class="doc-title">Qualification Run Day Roster, {{ $date->format('l, F j, Y') }}</div>

    @forelse($slots as $slot)
        <div class="sec">
            <div class="sec-h">{{ $slot->cleanroom }}@if($slot->start_time) · {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }}@endif
                @if($slot->analyst) · Analyst: {{ $slot->analyst->name }} @endif
                · {{ $slot->reservations->count() }} of {{ $slot->capacity }}</div>
            @if($slot->reservations->isEmpty())
                <div class="empty">No one scheduled.</div>
            @else
                <table>
                    <thead><tr><th style="width:30px;">#</th><th>Employee ID</th><th>Name</th><th>Status</th><th>Worklist</th><th>Result</th><th style="width:120px;">Initials</th></tr></thead>
                    <tbody>
                        @foreach($slot->reservations as $i => $res)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $res->personnel?->employee_id }}</td>
                                <td>{{ $res->personnel?->full_name }}</td>
                                <td>{{ ucfirst($res->status) }}</td>
                                <td>{{ $res->lims_worklist_id ?? '' }}</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <div class="empty">No run slots scheduled for this date.</div>
    @endforelse

    <div class="sign">
        <div class="sign-box"><div class="sign-line"></div><div class="sign-lbl">QC Micro Analyst, Signature / Date</div></div>
        <div class="sign-box"><div class="sign-line"></div><div class="sign-lbl">Reviewed By, Signature / Date</div></div>
    </div>
@endsection
