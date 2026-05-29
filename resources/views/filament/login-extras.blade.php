@if (request()->routeIs('filament.admin.auth.*'))
@php
    $stars = [];
    for ($i=0;$i<70;$i++){               // many more stars
        $stars[]=[
            't'=>rand(1,97),'l'=>rand(1,98),
            'c'=>['#fff','#fff','#fff','#fff','#E8C24A','#B98CE0','#C8102E','#7E3CA8'][rand(0,7)],
            's'=>rand(3,13),             // bigger + much more varied sizes
            'd'=>rand(0,60)/10,          // wide range of start delays (not synced)
            'u'=>rand(14,52)/10,         // wide range of flash rates
        ];
    }
@endphp
<style>
    .gqs-login-stars{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;}
    .gqs-login-stars i{position:absolute;border-radius:50%;animation:gqsTw var(--u) ease-in-out infinite;}
    @keyframes gqsTw{0%,100%{opacity:.15;transform:scale(.5);}50%{opacity:1;transform:scale(1.5);}}

    /* Landing-style header bar */
    .gqs-login-bar{position:fixed;top:0;left:0;right:0;z-index:30;display:flex;align-items:center;justify-content:space-between;
        padding:16px 32px;background:#1C1C21;border-bottom:2px solid #A4123F;}
    .gqs-login-bar .lhs{display:flex;align-items:center;gap:12px;}
    .gqs-login-bar img{height:34px;}
    .gqs-login-bar .brand{color:#fff;font-weight:700;letter-spacing:.03em;font-size:15px;}
    .gqs-login-bar .rhs a{color:#E8C24A;font-weight:600;font-size:14px;text-decoration:none;display:flex;align-items:center;gap:7px;}
    .gqs-login-bar .rhs a:hover{color:#F0CB55;}

    /* push the login card down + breathing room under logo */
    .fi-simple-layout{padding-top:96px;}
    .fi-simple-main .fi-logo,
    .fi-simple-layout .fi-logo{margin-bottom:28px !important;}
    .fi-simple-main{padding-bottom:8px;}
</style>
<div class="gqs-login-bar">
    <span class="lhs">
        <img src="{{ asset('images/astellas-logo.png') }}" alt="Astellas">
        <span class="brand">Gowning Qualification</span>
    </span>
    <span class="rhs"><a href="{{ url('/') }}">&larr; Back To Site</a></span>
</div>
<div class="gqs-login-stars">
    @foreach($stars as $s)
        <i style="top:{{$s['t']}}%;left:{{$s['l']}}%;width:{{$s['s']}}px;height:{{$s['s']}}px;
                  background:{{$s['c']}};box-shadow:0 0 {{$s['s']+4}}px {{$s['c']}};
                  --u:{{$s['u']}}s;animation-delay:{{$s['d']}}s;"></i>
    @endforeach
</div>
@endif
