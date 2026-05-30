@extends('print.layout', ['title' => 'Run Day Roster', 'org' => $org, 'site' => $site])
@section('body')
    <div class="doc-title">Qualification Run Day Roster, {{ $date->format('l, d F Y') }}</div>

    @forelse($slots as $slot)
        <div class="sec">
            <div class="sec-h">{{ $slot->cleanroom }}@if($slot->start_time) · {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('H:i') }}@endif
                @if($slot->analyst) · Analyst: {{ $slot->analyst->name }} @endif
                · {{ $slot->reservations->count() }} of {{ $slot->capacity }}</div>
            @if($slot->reservations->isEmpty())
                <div class="empty">No one scheduled.</div>
            @else
                <table class="data">
                    <thead><tr><th style="width:26px;">#</th><th>Employee ID</th><th>Name</th><th>Department</th><th>Status</th><th>Worklist</th><th>Veeva Doc</th><th>Result</th><th style="width:90px;">Time In</th><th style="width:90px;">Time Out</th><th style="width:110px;">Initials</th></tr></thead>
                    <tbody>
                        @foreach($slot->reservations as $i => $res)
                            @php $lr = \App\Models\QualificationRun::where('personnel_id', $res->personnel_id)->latest('id')->first(); @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $res->personnel?->employee_id }}</td>
                                <td>{{ $res->personnel?->full_name }}</td>
                                <td>{{ $res->personnel?->department }}</td>
                                <td>{{ ucfirst($res->status) }}</td>
                                <td>{{ $res->lims_worklist_id ?? '' }}</td>
                                <td>{{ $lr?->veeva_doc_number ?? '' }}</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
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

    <table class="sign-tbl"><tr>
        <td style="width:50%;"><div class="sign-line"></div><div class="sign-lbl">QC Micro Analyst, Signature / Date</div></td>
        <td style="width:50%;"><div class="sign-line"></div><div class="sign-lbl">Reviewed By, Signature / Date</div></td>
    </tr></table>
@endsection
