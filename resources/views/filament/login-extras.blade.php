@if (request()->routeIs('filament.admin.auth.*'))
@php
    $stars = [];
    for ($i=0;$i<40;$i++){
        $stars[]=[
            't'=>rand(2,95),'l'=>rand(1,98),
            'c'=>['#fff','#fff','#fff','#E8C24A','#B98CE0','#C8102E'][rand(0,5)],
            's'=>rand(3,9),                       // bigger, varied
            'd'=>rand(0,40)/10,                   // staggered delay
            'u'=>rand(15,42)/10,                  // varied flash rate
        ];
    }
@endphp
<style>
    .gqs-login-stars{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;}
    .gqs-login-stars i{position:absolute;border-radius:50%;animation:gqsTw var(--u) ease-in-out infinite;}
    @keyframes gqsTw{0%,100%{opacity:.2;transform:scale(.6);}50%{opacity:1;transform:scale(1.4);}}
    .gqs-login-bar{position:fixed;top:0;left:0;right:0;z-index:30;display:flex;align-items:center;justify-content:space-between;
        padding:14px 24px;background:rgba(15,15,18,.55);backdrop-filter:blur(6px);}
    .gqs-login-bar a{color:#E8C24A;font-weight:600;font-size:14px;text-decoration:none;display:flex;align-items:center;gap:7px;}
    .gqs-login-bar a:hover{color:#F0CB55;}
    .gqs-login-bar .brand{color:#fff;font-weight:700;letter-spacing:.04em;font-size:14px;}
    .fi-simple-layout{padding-top:60px;}
</style>
<div class="gqs-login-bar">
    <a href="{{ url('/') }}">&larr; Back to Site</a>
    <span class="brand">ASTELLAS · Gowning Qualification</span>
</div>
<div class="gqs-login-stars">
    @foreach($stars as $s)
        <i style="top:{{$s['t']}}%;left:{{$s['l']}}%;width:{{$s['s']}}px;height:{{$s['s']}}px;
                  background:{{$s['c']}};box-shadow:0 0 {{$s['s']+3}}px {{$s['c']}};
                  --u:{{$s['u']}}s;animation-delay:{{$s['d']}}s;"></i>
    @endforeach
</div>
@endif
