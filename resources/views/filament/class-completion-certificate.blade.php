@php
    /** @var \App\Models\ClassCompletion $c */
    $p = $c->personnel;
    $src = strtolower((string) $c->source);
    $srcLabel = match ($src) {
        'lms' => 'LMS Import', 'manual' => 'Manually Recorded', 'self' => 'Self-Service',
        'import' => 'Bulk Import', 'inferred' => 'Inferred From Backfill', default => ucwords(str_replace(['_','-'],' ', $src)),
    };
    $inferred = $src === 'inferred';
@endphp

<div style="padding:4px;">
    <div style="position:relative;border:2px solid {{ $inferred ? '#C79A2E' : '#2E7D5B' }};border-radius:16px;padding:26px 28px;background:linear-gradient(160deg,#FFFFFF,{{ $inferred ? '#FCF6E9' : '#F1F8F4' }});overflow:hidden;">
        {{-- seal --}}
        <div style="position:absolute;top:18px;right:22px;width:62px;height:62px;border-radius:50%;background:{{ $inferred ? 'linear-gradient(135deg,#C79A2E,#9E7818)' : 'linear-gradient(135deg,#2E7D5B,#1F6147)' }};display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,.18);">
            <x-filament::icon icon="heroicon-m-check-badge" style="width:34px;height:34px;color:#fff;"/>
        </div>

        <div style="font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:{{ $inferred ? '#9E7818' : '#1F6147' }};">Gowning Class Completion</div>
        <div style="font-size:26px;font-weight:800;color:#1A1A1F;margin-top:8px;line-height:1.1;">{{ $p?->full_name ?? 'Unmatched Personnel' }}</div>
        <div style="font-size:13px;color:#6A6A72;margin-top:3px;">{{ $c->employee_id ?: ($p?->employee_id ?? 'No ID') }}@if($p?->job_title) · {{ $p->job_title }}@endif@if($p?->department) · {{ $p->department }}@endif</div>

        <div style="height:1px;background:rgba(0,0,0,.1);margin:20px 0;"></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div>
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;">Class</div>
                <div style="font-weight:700;color:#1A1A1F;margin-top:3px;">{{ $c->class_name ?: 'Gowning Qualification Class' }}</div>
            </div>
            <div>
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;">Completion Date</div>
                <div style="font-weight:700;color:#1A1A1F;margin-top:3px;">{{ $c->completion_date?->gmp() ?? '—' }}</div>
            </div>
            <div>
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;">Source</div>
                <div style="margin-top:4px;"><span style="display:inline-block;padding:3px 11px;border-radius:999px;font-size:12px;font-weight:700;background:{{ $inferred ? '#F6E7C2' : '#D7EFE4' }};color:{{ $inferred ? '#7A5C12' : '#1F6147' }};">{{ $srcLabel }}</span></div>
            </div>
            <div>
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;">Recorded</div>
                <div style="font-weight:700;color:#1A1A1F;margin-top:3px;">{{ $c->created_at?->gmp() ?? '—' }}</div>
            </div>
        </div>

        @if($inferred)
            <div style="margin-top:20px;padding:11px 14px;border-radius:10px;background:#FBF0D6;border:1px solid #E9D08A;font-size:12px;color:#7A5C12;line-height:1.45;">
                <strong>Inferred entry.</strong> This was auto-created from a historic LIMS backfill (the requalification implies a prior class). A reviewer with the Classes role should confirm it and change the source to Manual once verified.
            </div>
        @endif
    </div>
</div>
