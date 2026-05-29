@extends('public.layout')
@section('title', 'Icon Options')
@section('content')
    <section class="section">
        <h2>Hero Icon Options</h2>
        <p class="tab-sub">Pick the one you like and tell me the name.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-top:20px;">
            <div style="background:var(--charcoal);border-radius:14px;padding:28px;text-align:center;">
                <img src="{{ asset('images/options/hands.svg') }}" style="width:130px;height:130px;">
                <div style="color:#fff;margin-top:12px;font-weight:600;">Gloved Hands</div>
                <div style="color:#9A9AA2;font-size:13px;">name: hands</div>
            </div>
            <div style="background:var(--charcoal);border-radius:14px;padding:28px;text-align:center;">
                <img src="{{ asset('images/options/mask.svg') }}" style="width:130px;height:130px;">
                <div style="color:#fff;margin-top:12px;font-weight:600;">Face Mask</div>
                <div style="color:#9A9AA2;font-size:13px;">name: mask</div>
            </div>
            <div style="background:var(--charcoal);border-radius:14px;padding:28px;text-align:center;">
                <img src="{{ asset('images/options/mask-glasses.svg') }}" style="width:130px;height:130px;">
                <div style="color:#fff;margin-top:12px;font-weight:600;">Mask + Safety Glasses</div>
                <div style="color:#9A9AA2;font-size:13px;">name: mask-glasses</div>
            </div>
            <div style="background:var(--charcoal);border-radius:14px;padding:28px;text-align:center;">
                <img src="{{ asset('images/options/coverall.svg') }}" style="width:130px;height:130px;">
                <div style="color:#fff;margin-top:12px;font-weight:600;">Flatter Coverall</div>
                <div style="color:#9A9AA2;font-size:13px;">name: coverall</div>
            </div>
            <div style="background:var(--charcoal);border-radius:14px;padding:28px;text-align:center;">
                <img src="{{ asset('images/gowned-figure.svg') }}" style="width:130px;height:130px;">
                <div style="color:#fff;margin-top:12px;font-weight:600;">Current (Tyvek)</div>
                <div style="color:#9A9AA2;font-size:13px;">name: current</div>
            </div>
            <div style="background:var(--charcoal);border-radius:14px;padding:28px;text-align:center;">
                <img src="{{ asset('images/flying-star.svg') }}" style="width:130px;height:130px;">
                <div style="color:#fff;margin-top:12px;font-weight:600;">Flying Star</div>
                <div style="color:#9A9AA2;font-size:13px;">name: star</div>
            </div>
        </div>
    </section>
@endsection
