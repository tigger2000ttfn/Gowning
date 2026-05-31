@php
    /** @var \App\Models\QualificationRun $r */
    $p = $r->personnel;
    $res = strtolower((string) ($r->result instanceof \BackedEnum ? $r->result->value : $r->result));
    $isPass = $res === 'pass';
    $isFail = $res === 'fail';
    $accent = $isPass ? '#2E7D5B' : ($isFail ? '#C8102E' : '#C79A2E');
    $bgTint = $isPass ? '#F1F8F4' : ($isFail ? '#FCEEF0' : '#FCF6E9');
    $icon = $isPass ? 'heroicon-m-check-badge' : ($isFail ? 'heroicon-m-x-circle' : 'heroicon-m-clock');
    $resLabel = $isPass ? 'Pass' : ($isFail ? 'Fail' : 'Pending');
@endphp

<div style="padding:4px;">
    <div style="position:relative;border:2px solid {{ $accent }};border-radius:16px;padding:26px 28px;background:linear-gradient(160deg,#FFFFFF,{{ $bgTint }});overflow:hidden;">
        <div style="position:absolute;top:18px;right:22px;width:60px;height:60px;border-radius:50%;background:{{ $accent }};display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,.18);">
            <x-filament::icon :icon="$icon" style="width:34px;height:34px;color:#fff;"/>
        </div>

        <div style="font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:{{ $accent }};">Qualification Run</div>
        <div style="font-size:25px;font-weight:800;color:#1A1A1F;margin-top:8px;line-height:1.12;">{{ $p?->full_name ?? 'Unmatched Personnel' }}</div>
        <div style="font-size:13px;color:#6A6A72;margin-top:3px;">{{ $p?->employee_id ?? 'No ID' }}@if($p?->department) · {{ $p->department }}@endif</div>

        <div style="height:1px;background:rgba(0,0,0,.1);margin:20px 0;"></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div>
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;">Result</div>
                <div style="margin-top:4px;"><span style="display:inline-block;padding:3px 12px;border-radius:999px;font-size:12.5px;font-weight:800;background:{{ $accent }}1A;color:{{ $accent }};">{{ $resLabel }}</span></div>
            </div>
            <div>
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;">Run Date</div>
                <div style="font-weight:700;color:#1A1A1F;margin-top:3px;">{{ $r->run_date?->gmp() ?? '—' }}</div>
            </div>
            <div>
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;">LIMS Worklist</div>
                <div style="font-weight:700;color:#1A1A1F;margin-top:3px;">{{ $r->lims_worklist_id ?: '—' }}</div>
            </div>
            <div>
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;">Cycle</div>
                <div style="font-weight:700;color:#1A1A1F;margin-top:3px;">{{ $r->cycle_type instanceof \BackedEnum ? $r->cycle_type->label() : ($r->cycle_type ?: '—') }}</div>
            </div>
            @if($r->lims_nc_number)
                <div>
                    <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;">NC / TrackWise</div>
                    <div style="font-weight:700;color:#A4123F;margin-top:3px;">@if($r->lims_nc_url)<a href="{{ $r->lims_nc_url }}" target="_blank" rel="noopener" style="color:#A4123F;">{{ $r->lims_nc_number }} ↗</a>@else{{ $r->lims_nc_number }}@endif</div>
                </div>
            @endif
            @if($r->veeva_doc_number)
                <div>
                    <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;">Veeva Report</div>
                    <div style="font-weight:700;color:#A4123F;margin-top:3px;">@if($r->veeva_url)<a href="{{ $r->veeva_url }}" target="_blank" rel="noopener" style="color:#A4123F;">{{ $r->veeva_doc_number }} ↗</a>@else{{ $r->veeva_doc_number }}@endif</div>
                </div>
            @endif
        </div>

        @if($r->qa_signed_at || $r->qaSignedBy)
            <div style="margin-top:18px;padding:11px 14px;border-radius:10px;background:#EAF4EF;border:1px solid #BFE0D0;font-size:12.5px;color:#1F6147;">
                <strong>QA Approved</strong> by {{ $r->qaSignedBy?->name ?? 'QA' }}@if($r->qa_signed_at) on {{ \Illuminate\Support\Carbon::parse($r->qa_signed_at)->gmp() }}@endif.
            </div>
        @endif
    </div>
</div>
