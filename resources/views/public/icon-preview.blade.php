@extends('public.layout')
@section('title', 'Icon Options')
@section('content')
    <section class="section">
        <h2>Hero Icon Options</h2>
        <p class="tab-sub">Both shown with the subtle glow animation. Tell me which: labcoat, glove, or keep mask.</p>
        <style>
            .glowdemo{filter:drop-shadow(0 0 6px rgba(232,194,74,.5));animation:glow 3.5s ease-in-out infinite;}
            @keyframes glow{0%,100%{filter:drop-shadow(0 0 5px rgba(232,194,74,.35));}50%{filter:drop-shadow(0 0 16px rgba(200,16,46,.65));}}
        </style>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-top:20px;">
            <div style="background:var(--charcoal);border-radius:14px;padding:32px;text-align:center;">
                <img src="{{ asset('images/options/labcoat.svg') }}" class="glowdemo" style="width:140px;height:140px;">
                <div style="color:#fff;margin-top:14px;font-weight:600;">Lab Coat</div>
                <div style="color:#9A9AA2;font-size:13px;">name: labcoat</div>
            </div>
            <div style="background:var(--charcoal);border-radius:14px;padding:32px;text-align:center;">
                <img src="{{ asset('images/options/glove.svg') }}" class="glowdemo" style="width:140px;height:140px;">
                <div style="color:#fff;margin-top:14px;font-weight:600;">Glove</div>
                <div style="color:#9A9AA2;font-size:13px;">name: glove</div>
            </div>
            <div style="background:var(--charcoal);border-radius:14px;padding:32px;text-align:center;">
                <img src="{{ asset('images/hero-icon.svg') }}" class="glowdemo" style="width:140px;height:140px;">
                <div style="color:#fff;margin-top:14px;font-weight:600;">Current (Mask)</div>
                <div style="color:#9A9AA2;font-size:13px;">name: mask</div>
            </div>
        </div>
    </section>
@endsection
