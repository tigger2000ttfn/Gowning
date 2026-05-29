<div style="display:flex;align-items:center;gap:10px;padding:4px 0;">
    @php
        $svg = file_exists(public_path('images/astellas-logo.svg'));
        $png = file_exists(public_path('images/astellas-logo.png'));
    @endphp
    @if ($svg || $png)
        <img src="{{ asset($svg ? 'images/astellas-logo.svg' : 'images/astellas-logo.png') }}"
             alt="Astellas" style="height:30px;width:auto;">
    @else
        <span style="font-weight:800;font-size:18px;letter-spacing:.04em;color:#A4123F;">ASTELLAS</span>
    @endif
    <span style="font-weight:600;font-size:12px;color:#E5E5EA;line-height:1.1;">
        Gowning<br>Qualification
    </span>
</div>
