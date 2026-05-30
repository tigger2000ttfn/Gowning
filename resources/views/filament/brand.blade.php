@php
    $svg = file_exists(public_path('images/astellas-logo.svg'));
    $png = file_exists(public_path('images/astellas-logo.png'));
    $hasLogo = $svg || $png;
@endphp
{{-- Same approach as the login bar: center the row, nudge only the text down a touch --}}
<div style="display:flex;align-items:center;gap:12px;height:100%;">
    @if ($hasLogo)
        <img src="{{ asset($svg ? 'images/astellas-logo.svg' : 'images/astellas-logo.png') }}"
             alt="Astellas" style="height:34px;width:auto;flex:0 0 auto;display:block;">
    @else
        <span style="font-weight:800;font-size:17px;letter-spacing:.04em;color:#E8C24A;">ASTELLAS</span>
    @endif
    <span class="gqs-brand-text" style="font-weight:700;font-size:15px;color:#444;white-space:nowrap;letter-spacing:.03em;padding-top:18px;">
        Gowning Qualification
    </span>
</div>
