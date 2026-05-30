@extends('print.layout', ['title' => 'Compliance Report', 'org' => $org, 'site' => $site, 'generated' => $generated])
@section('body')
    <div class="doc-title">Gowning Qualification Compliance Report</div>

    <div class="stats">
        <div class="stat"><div class="n" style="color:#C8102E;">{{ $overdue->count() }}</div><div class="l">Overdue</div></div>
        <div class="stat"><div class="n" style="color:#6B2C91;">{{ $upcoming->count() }}</div><div class="l">Due in 60 Days</div></div>
        <div class="stat"><div class="n" style="color:#2E7D5B;">{{ $passes }}</div><div class="l">Total Passes</div></div>
        <div class="stat"><div class="n" style="color:#8A6D0B;">{{ $fails }}</div><div class="l">Total Fails</div></div>
    </div>

    <div class="sec">
        <div class="sec-h">Overdue Qualifications</div>
        @if($overdue->isEmpty())<div class="empty">None overdue.</div>@else
            <table><thead><tr><th>Employee</th><th>Name</th><th>Due Date</th></tr></thead><tbody>
                @foreach($overdue as $q)<tr><td>{{ $q->personnel?->employee_id }}</td><td>{{ $q->personnel?->full_name }}</td><td><span class="pill pill-red">{{ $q->due_date?->format('M j, Y') }}</span></td></tr>@endforeach
            </tbody></table>
        @endif
    </div>

    <div class="sec">
        <div class="sec-h">Upcoming, Next 60 Days</div>
        @if($upcoming->isEmpty())<div class="empty">None upcoming.</div>@else
            <table><thead><tr><th>Employee</th><th>Name</th><th>Due Date</th></tr></thead><tbody>
                @foreach($upcoming as $q)<tr><td>{{ $q->personnel?->employee_id }}</td><td>{{ $q->personnel?->full_name }}</td><td><span class="pill pill-gold">{{ $q->due_date?->format('M j, Y') }}</span></td></tr>@endforeach
            </tbody></table>
        @endif
    </div>

    <div class="sign">
        <div class="sign-box"><div class="sign-line"></div><div class="sign-lbl">Prepared By, Signature / Date</div></div>
        <div class="sign-box"><div class="sign-line"></div><div class="sign-lbl">QA Reviewed By, Signature / Date</div></div>
    </div>
@endsection
