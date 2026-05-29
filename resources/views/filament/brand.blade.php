@php
    $svg = file_exists(public_path('images/astellas-logo.svg'));
    $png = file_exists(public_path('images/astellas-logo.png'));
    $hasLogo = $svg || $png;
@endphp
<div style="display:flex;align-items:center;gap:10px;padding:2px 0;">
    @if ($hasLogo)
        <img src="{{ asset($svg ? 'images/astellas-logo.svg' : 'images/astellas-logo.png') }}"
             alt="Astellas Gowning Qualification" style="height:32px;width:auto;">
    @else
        <span style="font-weight:800;font-size:17px;letter-spacing:.04em;color:#E8C24A;">ASTELLAS</span>
        <span style="font-weight:600;font-size:12px;color:#E5E5EA;line-height:1.15;">
            Gowning<br>Qualification
        </span>
    @endif
</div>
