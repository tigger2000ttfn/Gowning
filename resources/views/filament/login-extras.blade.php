@php
    $onLogin = request()->routeIs('filament.admin.auth.*')
        || str_contains(request()->path(), 'admin/login')
        || str_ends_with(request()->path(), 'admin/login');
    $stars = [];
    for ($i=0;$i<100;$i++){
        $r = rand(0,9);
        $cls = $r < 5 ? '' : ($r < 7 ? 'g' : ($r < 9 ? 'p' : 'r'));
        $b = rand(0,9);
        $sz = $b < 6 ? rand(2,4) : ($b < 9 ? rand(5,7) : rand(8,9));
        $glow = round($sz * 1.6, 1);
        $spread = round($sz * 0.4, 1);
        $color = ['' => '255,255,255', 'g' => '232,194,74', 'p' => '185,140,224', 'r' => '232,101,127'][$cls];
        $stars[] = ['t'=>rand(1,98),'l'=>rand(1,98),'sz'=>$sz,'cls'=>$cls,'d'=>rand(0,400)/100,'u'=>(rand(0,3)===0 ? rand(180,320) : rand(400,900))/100,'glow'=>$glow,'spread'=>$spread,'color'=>$color];
    }
    // extra small stars clustered top-left
    for ($i=0;$i<22;$i++){
        $cls = rand(0,9) < 7 ? '' : (rand(0,1) ? 'g' : 'p');
        $sz = rand(2,4);
        $color = ['' => '255,255,255', 'g' => '232,194,74', 'p' => '185,140,224', 'r' => '232,101,127'][$cls];
        $stars[] = ['t'=>rand(1,42),'l'=>rand(1,40),'sz'=>$sz,'cls'=>$cls,'d'=>rand(0,400)/100,'u'=>(rand(0,3)===0 ? rand(180,320) : rand(400,900))/100,'glow'=>round($sz*1.6,1),'spread'=>round($sz*0.4,1),'color'=>$color];
    }
@endphp
@if ($onLogin)
<style>
    /* COSMIC BACKDROP behind the whole login (mirrors landing hero) */
    .fi-simple-layout{position:relative;background:#15151A !important;overflow:hidden;padding-top:96px;}
    .gqs-backdrop{position:fixed;inset:0;z-index:0;background:#15151A;pointer-events:none;}
    .gqs-neb{position:fixed;inset:-20%;z-index:1;pointer-events:none;background:
        radial-gradient(45% 50% at 26% 38%,rgba(126,60,168,.30),transparent 70%),
        radial-gradient(42% 48% at 78% 60%,rgba(164,18,63,.24),transparent 72%),
        radial-gradient(38% 42% at 58% 80%,rgba(80,40,140,.22),transparent 70%);
        filter:blur(10px);animation:gqsNeb 18s ease-in-out infinite;}
    @keyframes gqsNeb{0%,100%{transform:translate(0,0) scale(1)}25%{transform:translate(5%,3%) scale(1.08)}50%{transform:translate(-4%,-5%) scale(1.12)}75%{transform:translate(3%,-3%) scale(1.06)}}
    .gqs-stars{position:fixed;inset:0;z-index:2;pointer-events:none;}
    .gqs-stars i{position:absolute;border-radius:50%;animation:gqsTw 3s ease-in-out infinite;}
    .gqs-stars i.g{background:#E8C24A;}
    .gqs-stars i.p{background:#B98CE0;}
    .gqs-stars i.r{background:#E8657F;}
    @keyframes gqsTw{0%,100%{opacity:.4;transform:scale(.95)}50%{opacity:1;transform:scale(1.12)}}

    /* the login card sits above the cosmos */
    /* card + its container must out-stack the fixed star layer */
    .fi-simple-layout .fi-simple-main-ctn,
    .fi-simple-main-ctn{position:relative;z-index:10 !important;}
    .fi-simple-main{position:relative;z-index:10;}

    /* header bar */
    .gqs-login-bar{position:fixed;top:0;left:0;right:0;z-index:30;display:flex;align-items:center;justify-content:space-between;
        padding:16px 32px;background:#1C1C21;border-bottom:2px solid #A4123F;}
    .gqs-login-bar .lhs{display:flex;align-items:center;gap:12px;}
    .gqs-login-bar img{height:34px;}
    .gqs-login-bar .brand{color:#444;font-weight:700;letter-spacing:.03em;font-size:15px;padding-top:20px;}
    .gqs-login-bar .rhs a{color:#E8C24A;font-weight:600;font-size:14px;text-decoration:none;}
    .gqs-login-bar .rhs a:hover{color:#F0CB55;}

    /* in-card brand: keep the logo visible, give it room */
    .fi-simple-main .gqs-brand-text{display:none !important;}
    /* bigger logo inside the sign-in card */
    .fi-simple-main img{height:56px !important;}
    /* breathing room ABOVE the card content (pushes logo down from card top) */
    .fi-simple-main{padding-top:56px !important;}
    .fi-simple-main{padding-top:30px;}

    /* solid magenta sign-in button (no pink) + padding */
    .fi-simple-main .fi-btn-color-primary,
    .fi-simple-main button[type=submit]{
        background-color:#A4123F !important;border-color:#A4123F !important;color:#fff !important;
        --tw-ring-color:#A4123F !important;box-shadow:none !important;}
    .fi-simple-main .fi-btn-color-primary:hover,
    .fi-simple-main button[type=submit]:hover{background-color:#850F33 !important;}
    .fi-simple-main form{padding-bottom:18px;}

    /* Shooting stars across the login cosmos (sprinkled by JS, behind the card) */
    .gqs-shoot{position:fixed;inset:0;z-index:3;pointer-events:none;overflow:hidden;}
    .gqs-shoot .shooting-star{position:absolute;height:2px;border-radius:2px;opacity:0;pointer-events:none;
        background:linear-gradient(90deg,rgba(255,255,255,0),#fff);transform-origin:center;
        filter:drop-shadow(0 0 6px rgba(255,255,255,.65));}
    .gqs-shoot .shooting-star::after{content:'';position:absolute;right:0;top:-1.5px;width:5px;height:5px;border-radius:50%;
        background:#fff;box-shadow:0 0 9px 2px rgba(255,255,255,.9);}
    @keyframes gqsShoot{0%{opacity:0;transform:translate(0,0) rotate(var(--ang,22deg));}
        12%{opacity:1;}100%{opacity:0;transform:translate(var(--dx,360px),var(--dy,150px)) rotate(var(--ang,22deg));}}
    @media(prefers-reduced-motion:reduce){.gqs-shoot{display:none;}}
</style>
<div class="gqs-backdrop"></div>
<div class="gqs-neb"></div>
<div class="gqs-stars">
    @foreach($stars as $s)
        <i style="top:{{$s['t']}}%;left:{{$s['l']}}%;width:{{$s['sz']}}px;height:{{$s['sz']}}px;background:rgb({{$s['color']}});box-shadow:0 0 {{$s['glow']}}px {{$s['spread']}}px rgba({{$s['color']}},.9);animation-delay:{{$s['d']}}s;animation-duration:{{$s['u']}}s;"></i>
    @endforeach
</div>
<div class="gqs-login-bar">
    <span class="lhs">
        <img src="{{ asset('images/astellas-logo.png') }}" alt="Astellas">
        <span class="brand">Gowning Qualification</span>
    </span>
    <span class="rhs"><a href="{{ url('/') }}">&larr; Back To Site</a></span>
</div>
<div class="gqs-shoot"></div>
<script>
(function(){
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var layer = document.querySelector('.gqs-shoot');
    if (!layer) return;
    var colors = ['#ffffff','#ffffff','#ffffff','#E8C24A','#B98CE0'];
    function spawn(){
        var w = window.innerWidth, h = window.innerHeight;
        var s = document.createElement('span');
        s.className = 'shooting-star';
        var ang = 14 + Math.random()*26, rad = ang*Math.PI/180;
        var dist = 240 + Math.random()*340, len = 90 + Math.random()*120;
        var dur = 700 + Math.random()*800;
        var col = colors[Math.floor(Math.random()*colors.length)];
        s.style.left = (Math.random()*w*0.7) + 'px';
        s.style.top = (Math.random()*h*0.5) + 'px';
        s.style.width = len + 'px';
        s.style.background = 'linear-gradient(90deg,rgba(255,255,255,0),' + col + ')';
        s.style.setProperty('--ang', ang + 'deg');
        s.style.setProperty('--dx', (Math.cos(rad)*dist) + 'px');
        s.style.setProperty('--dy', (Math.sin(rad)*dist) + 'px');
        s.style.animation = 'gqsShoot ' + dur + 'ms ease-out forwards';
        layer.appendChild(s);
        setTimeout(function(){ s.remove(); }, dur + 120);
        schedule();
    }
    function schedule(){ setTimeout(spawn, 2200 + Math.random()*5000); }
    schedule();
})();
</script>
@endif
