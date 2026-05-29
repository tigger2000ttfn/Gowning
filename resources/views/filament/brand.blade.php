@php
    $svg = file_exists(public_path('images/astellas-logo.svg'));
    $png = file_exists(public_path('images/astellas-logo.png'));
    $hasLogo = $svg || $png;
@endphp
<div style="display:flex;align-items:center;gap:11px;padding:2px 0;">
    @if ($hasLogo)
        <img src="{{ asset($svg ? 'images/astellas-logo.svg' : 'images/astellas-logo.png') }}"
             alt="Astellas" style="height:36px;width:auto;flex:0 0 auto;">
    @else
        <span style="font-weight:800;font-size:17px;letter-spacing:.04em;color:#E8C24A;">ASTELLAS</span>
    @endif
    <span style="font-weight:700;font-size:14px;color:#ECECF0;line-height:1.1;white-space:nowrap;letter-spacing:.02em;">
        Gowning Qualification
    </span>
</div>
