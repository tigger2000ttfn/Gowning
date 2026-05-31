@extends('print.layout', ['title' => $title, 'org' => $org, 'site' => $site, 'generated' => $generated])
@section('body')
    <div class="doc-title">{{ $title }}</div>

    <div class="sec">
        @if(empty($rows))
            <div class="empty">No records for this report.</div>
        @else
            <table class="data">
                <thead><tr>@foreach($columns as $col)<th>{{ $col }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
            <div class="empty" style="text-align:right;border:none;color:#6A6A72;margin-top:6px;">{{ count($rows) }} record(s)</div>
        @endif
    </div>
@endsection
