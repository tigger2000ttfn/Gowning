<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'MATC Gowning Qualification')</title>
    <style>
        :root{
            --ink:#15151A; --muted:#5A5A62; --line:#E4E4EA; --bg:#F6F6F8;
            --magenta:#A4123F; --red:#C8102E; --gold:#B8860B; --purple:#6B2C91;
            --charcoal:#15151A; --charcoal2:#222228;
        }
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:var(--ink);background:var(--bg);line-height:1.5;}
        a{color:var(--magenta);text-decoration:none;}
        .nav{display:flex;align-items:center;justify-content:space-between;padding:16px 32px;background:var(--charcoal);position:sticky;top:0;z-index:10;}
        .brand{display:flex;align-items:center;gap:10px;color:#fff;font-weight:700;letter-spacing:.02em;}
        .brand .star{color:var(--red);font-size:22px;}
        .brand small{display:block;font-weight:500;font-size:11px;color:#B8B8C0;}
        .nav-links a{color:#E5E5EA;margin-left:22px;font-size:14px;font-weight:500;}
        .nav-links a.btn{background:var(--magenta);padding:9px 16px;border-radius:8px;color:#fff;}
        .flash{max-width:1080px;margin:18px auto 0;padding:14px 18px;background:#E8F5EC;border:1px solid #2E7D5B;border-radius:10px;color:#1B5E3A;}
        .hero{position:relative;overflow:hidden;background:var(--charcoal);color:#fff;padding:48px 32px 56px;text-align:center;}
        .hero::before{content:'';position:absolute;inset:-20%;background:
            radial-gradient(2px 2px at 18% 75%,rgba(200,16,46,.9),transparent 60%),
            radial-gradient(3px 3px at 38% 60%,rgba(184,134,11,.8),transparent 60%),
            radial-gradient(2px 2px at 62% 82%,rgba(200,16,46,.7),transparent 60%),
            radial-gradient(2px 2px at 80% 55%,rgba(220,160,40,.85),transparent 60%),
            radial-gradient(40% 50% at 28% 30%,rgba(164,18,63,.25),transparent 66%),
            radial-gradient(45% 55% at 76% 68%,rgba(120,10,40,.28),transparent 68%);
            animation:rise 30s linear infinite;}
        @keyframes rise{0%{transform:translateY(0)}50%{transform:translateY(-6%)}100%{transform:translateY(0)}}
        @media(prefers-reduced-motion:reduce){.hero::before{animation:none}}
        .hero-inner{position:relative;z-index:1;max-width:760px;margin:0 auto;}
        .hero-star{width:64px;height:64px;margin-bottom:14px;filter:drop-shadow(0 6px 20px rgba(200,16,46,.5));animation:spin 40s linear infinite;}
        @keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
        @media(prefers-reduced-motion:reduce){.hero-star{animation:none}}
        .hero h1{font-size:34px;font-weight:800;line-height:1.1;margin-bottom:16px;}
        .hero .accent{color:var(--gold);}
        .hero p{font-size:18px;color:#C8C8D0;margin-bottom:30px;}
        .hero .cta{display:inline-flex;gap:14px;flex-wrap:wrap;justify-content:center;}
        .cta a{padding:13px 26px;border-radius:10px;font-weight:600;font-size:15px;}
        .cta .primary{background:var(--magenta);color:#fff;}
        .cta .ghost{border:1.5px solid var(--gold);color:var(--gold);}
        .section{max-width:1080px;margin:56px auto;padding:0 32px;}
        .section h2{font-size:26px;margin-bottom:8px;}
        .section .sub{color:var(--muted);margin-bottom:28px;}
        
        
        /* Stats strip */
        .strip{max-width:1080px;margin:-40px auto 0;padding:0 32px;position:relative;z-index:2;display:grid;grid-template-columns:repeat(4,1fr);gap:14px;}
        .strip-item{background:#fff;border:1px solid #D8D8DE;border-top:3px solid var(--magenta);border-radius:12px;padding:16px 18px;box-shadow:0 4px 14px rgba(0,0,0,.08);}
        .strip-item .num{display:block;font-size:28px;font-weight:800;color:var(--magenta);line-height:1;}
        .strip-item .lbl{display:block;margin-top:6px;font-size:12px;color:var(--muted);font-weight:500;}
        @media(max-width:760px){.strip{grid-template-columns:repeat(2,1fr);}}

        /* Section heads */
        .sec-head{margin-bottom:18px;}
        .sec-head h2{display:flex;align-items:center;gap:10px;font-size:22px;}
        .sec-head .sub{color:var(--muted);font-size:14px;margin-top:4px;}
        .dot{width:11px;height:11px;border-radius:3px;display:inline-block;transform:rotate(45deg);}
        .dot-magenta{background:var(--magenta);}
        .dot-gold{background:var(--gold);}

        /* Compact cards */
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:14px;}
        .ccard{display:flex;background:#fff;border:1px solid #DCDCE2;border-radius:12px;overflow:hidden;transition:box-shadow .15s,transform .15s;}
        .ccard:hover{box-shadow:0 8px 22px rgba(0,0,0,.12);transform:translateY(-2px);}
        .ccard-date{flex:0 0 64px;background:var(--charcoal);color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:12px 0;}
        .ccard-run .ccard-date{background:linear-gradient(160deg,#33333A,#1C1C21);}
        .ccard-date .mo{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--gold);font-weight:700;}
        .ccard-date .dy{font-size:24px;font-weight:800;line-height:1;}
        .ccard-body{flex:1;padding:14px 16px;}
        .ccard-body h3{font-size:15px;margin-bottom:4px;}
        .ccard-meta{font-size:13px;color:var(--muted);margin-bottom:12px;}
        .ccard-foot{display:flex;align-items:center;justify-content:space-between;}
        .seats{font-size:12px;font-weight:700;color:var(--gold-deep,#8A6D0B);background:#FBF3DC;padding:3px 9px;border-radius:20px;}
        .signup{font-size:13px;font-weight:700;color:var(--magenta);}
        .signup-gold{color:#8A6D0B;}
        .empty{color:var(--muted);padding:26px;text-align:center;border:1px dashed #C4C4CC;border-radius:12px;background:#fff;}

        
        /* Tabs */
        .tabbar{display:flex;gap:6px;border-bottom:2px solid #D8D8DE;margin-bottom:20px;}
        .tabbar button{display:flex;align-items:center;gap:8px;background:none;border:none;cursor:pointer;padding:12px 18px;font-size:15px;font-weight:600;color:var(--muted);border-bottom:3px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s;}
        .tabbar button:hover{color:var(--ink);}
        .tabbar button.active{color:var(--magenta);border-bottom-color:var(--magenta);}
        .tabbar .count{background:#EDEDF0;color:var(--ink);font-size:12px;font-weight:700;border-radius:20px;padding:1px 8px;}
        .tabbar button.active .count{background:var(--magenta);color:#fff;}
        .tab-sub{color:var(--muted);font-size:14px;margin-bottom:18px;}

        .formwrap{max-width:460px;margin:56px auto;padding:0 24px;}
        .formcard{background:#fff;border:1px solid var(--line);border-top:4px solid var(--magenta);border-radius:14px;padding:32px;}
        .formcard h2{margin-bottom:6px;}
        .formcard .sub{color:var(--muted);margin-bottom:22px;font-size:14px;}
        label{display:block;font-size:13px;font-weight:600;margin:14px 0 6px;}
        input{width:100%;padding:11px 13px;border:1px solid var(--line);border-radius:9px;font-size:15px;}
        input:focus{outline:none;border-color:var(--magenta);}
        .submit{width:100%;margin-top:22px;background:var(--magenta);color:#fff;border:none;padding:13px;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;}
        .submit:hover{background:#850F33;}
        .err{color:var(--red);font-size:13px;margin-top:5px;}
        .foot{background:var(--charcoal);color:#9A9AA2;text-align:center;padding:28px;font-size:13px;margin-top:60px;}
        .backlink{display:inline-block;margin-bottom:18px;font-size:14px;}
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important;}</style>
</head>
<body>
    <nav class="nav">
        <a href="{{ route('public.home') }}" class="brand">
            <span>ASTELLAS<small>Gowning Qualification</small></span>
            <span class="star">&#10039;</span>
        </a>
        <div class="nav-links">
            <a href="{{ route('public.home') }}#classes">Classes</a>
            <a href="{{ route('public.register') }}">Register</a>
            <a href="{{ url('/admin') }}" class="btn">Sign in</a>
        </div>
    </nav>

    @if (session('flash'))
        <div class="flash">{{ session('flash') }}</div>
    @endif

    @yield('content')

    <div class="foot">
        &copy; {{ date('Y') }} Astellas &middot; MATC Gowning Qualification
    </div>
</body>
</html>
