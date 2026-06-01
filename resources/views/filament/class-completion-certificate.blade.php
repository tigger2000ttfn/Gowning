@php
    /** @var \App\Models\ClassCompletion $c */
    $p = $c->personnel;
@endphp

<div style="padding:4px;">
    <div style="position:relative;border:2px solid #C79A2E;border-radius:16px;padding:30px 28px 26px;background:linear-gradient(160deg,#FFFFFF,#FCF6E9);overflow:hidden;text-align:center;">
        {{-- gold award seal --}}
        <div style="width:78px;height:78px;margin:0 auto 14px;border-radius:50%;background:linear-gradient(135deg,#E8C24A,#B8860B);display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(184,134,11,.35);">
            <x-filament::icon icon="heroicon-m-trophy" style="width:42px;height:42px;color:#fff;"/>
        </div>

        <div style="font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#9E7818;">Gowning Class Completion</div>
        <div style="font-size:26px;font-weight:800;color:#1A1A1F;margin-top:8px;line-height:1.15;">{{ $p?->full_name ?? 'Unmatched Personnel' }}</div>
        <div style="font-size:13px;color:#6A6A72;margin-top:4px;">{{ $c->employee_id ?: ($p?->employee_id ?? 'No ID') }}</div>

        <div style="height:1px;background:rgba(0,0,0,.1);margin:20px auto;max-width:280px;"></div>

        <div style="display:inline-flex;gap:40px;justify-content:center;flex-wrap:wrap;text-align:center;">
            <div>
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;">Class</div>
                <div style="font-weight:700;color:#1A1A1F;margin-top:3px;">{{ $c->class_name ?: 'Gowning Qualification Class' }}</div>
            </div>
            <div>
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;">Completion Date</div>
                <div style="font-weight:700;color:#1A1A1F;margin-top:3px;">{{ $c->completion_date?->gmp() ?? '—' }}</div>
            </div>
            @if($c->lms_number)
            <div>
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9A9AA4;">LMS Number</div>
                <div style="font-weight:700;color:#1A1A1F;margin-top:3px;">{{ $c->lms_number }}</div>
            </div>
            @endif
        </div>
    </div>
</div>
