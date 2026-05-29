@php
    $onLogin = request()->routeIs('filament.admin.auth.*')
        || str_contains(request()->path(), 'admin/login')
        || str_ends_with(request()->path(), 'admin/login');
    $stars = [];
    for ($i=0;$i<90;$i++){
        $r = rand(0,9);
        $cls = $r < 5 ? '' : ($r < 7 ? 'g' : ($r < 9 ? 'p' : 'r'));
        // size buckets: lots of small, several medium, a few BIG
        $b = rand(0,9);
        $sz = $b < 5 ? rand(2,4) : ($b < 8 ? rand(5,8) : rand(9,14));
        $stars[] = [
            't'=>rand(1,97), 'l'=>rand(1,98), 'sz'=>$sz, 'cls'=>$cls,
            'd'=>rand(0,400)/100, 'u'=>rand(180,520)/100,
        ];
    }
@endphp
@if ($onLogin)
<style>
    /* COSMIC BACKDROP behind the whole login (mirrors landing hero) */
    .fi-simple-layout{position:relative;background:#15151A !important;overflow:hidden;padding-top:96px;}
    .gqs-neb{content:'';position:fixed;inset:-20%;z-index:0;pointer-events:none;background:
        radial-gradient(45% 50% at 26% 38%,rgba(126,60,168,.30),transparent 70%),
        radial-gradient(42% 48% at 78% 60%,rgba(164,18,63,.24),transparent 72%),
        radial-gradient(38% 42% at 58% 80%,rgba(80,40,140,.22),transparent 70%);
        filter:blur(10px);animation:gqsNeb 26s ease-in-out infinite;}
    @keyframes gqsNeb{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(3%,-3%) scale(1.07)}}
    .gqs-stars{position:fixed;inset:0;z-index:0;pointer-events:none;}
    .gqs-stars i{position:absolute;border-radius:50%;background:#fff;
        box-shadow:0 0 6px 1px rgba(255,255,255,.6);opacity:.5;animation:gqsTw 3s ease-in-out infinite;}
    .gqs-stars i.g{background:#E8C24A;box-shadow:0 0 8px 2px rgba(232,194,74,.75);}
    .gqs-stars i.p{background:#B98CE0;box-shadow:0 0 8px 2px rgba(185,140,224,.75);}
    .gqs-stars i.r{background:#E8657F;box-shadow:0 0 9px 2px rgba(200,16,46,.7);}
    @keyframes gqsTw{0%,100%{opacity:.25;transform:scale(.7)}50%{opacity:1;transform:scale(1.4)}}

    /* the login card sits above the cosmos */
    .fi-simple-main{position:relative;z-index:2;}

    /* header bar */
    .gqs-login-bar{position:fixed;top:0;left:0;right:0;z-index:30;display:flex;align-items:center;justify-content:space-between;
        padding:16px 32px;background:#1C1C21;border-bottom:2px solid #A4123F;}
    .gqs-login-bar .lhs{display:flex;align-items:center;gap:12px;}
    .gqs-login-bar img{height:34px;}
    .gqs-login-bar .brand{color:#fff;font-weight:700;letter-spacing:.03em;font-size:15px;}
    .gqs-login-bar .rhs a{color:#E8C24A;font-weight:600;font-size:14px;text-decoration:none;}
    .gqs-login-bar .rhs a:hover{color:#F0CB55;}

    /* hide in-card brand logo (it's in our top bar instead) */
    .fi-simple-main .fi-logo, .fi-simple-layout > a.fi-logo{display:none !important;}
    .fi-simple-main{padding-top:30px;}

    /* solid magenta sign-in button (no pink) + padding */
    .fi-simple-main .fi-btn-color-primary,
    .fi-simple-main button[type=submit]{
        background-color:#A4123F !important;border-color:#A4123F !important;color:#fff !important;
        --tw-ring-color:#A4123F !important;box-shadow:none !important;}
    .fi-simple-main .fi-btn-color-primary:hover,
    .fi-simple-main button[type=submit]:hover{background-color:#850F33 !important;}
    .fi-simple-main form{padding-bottom:18px;}
</style>
<div class="gqs-neb"></div>
<div class="gqs-stars">
    @foreach($stars as $s)
        <i class="{{ $s['cls'] }}" style="top:{{$s['t']}}%;left:{{$s['l']}}%;width:{{$s['sz']}}px;height:{{$s['sz']}}px;animation-delay:{{$s['d']}}s;animation-duration:{{$s['u']}}s;"></i>
    @endforeach
</div>
<div class="gqs-login-bar">
    <span class="lhs">
        <img src="{{ asset('images/astellas-logo.png') }}" alt="Astellas">
        <span class="brand">Gowning Qualification</span>
    </span>
    <span class="rhs"><a href="{{ url('/') }}">&larr; Back To Site</a></span>
</div>
@endif
