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
        .hero{position:relative;overflow:hidden;background:var(--charcoal);color:#fff;padding:88px 32px 96px;text-align:center;}
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
        .hero-star{width:96px;height:96px;margin-bottom:22px;filter:drop-shadow(0 6px 20px rgba(200,16,46,.5));animation:spin 40s linear infinite;}
        @keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
        @media(prefers-reduced-motion:reduce){.hero-star{animation:none}}
        .hero h1{font-size:42px;font-weight:800;line-height:1.1;margin-bottom:16px;}
        .hero .accent{color:var(--gold);}
        .hero p{font-size:18px;color:#C8C8D0;margin-bottom:30px;}
        .hero .cta{display:inline-flex;gap:14px;flex-wrap:wrap;justify-content:center;}
        .cta a{padding:13px 26px;border-radius:10px;font-weight:600;font-size:15px;}
        .cta .primary{background:var(--magenta);color:#fff;}
        .cta .ghost{border:1.5px solid var(--gold);color:var(--gold);}
        .section{max-width:1080px;margin:56px auto;padding:0 32px;}
        .section h2{font-size:26px;margin-bottom:8px;}
        .section .sub{color:var(--muted);margin-bottom:28px;}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px;}
        .card{background:#fff;border:1px solid var(--line);border-left:4px solid var(--magenta);border-radius:12px;padding:20px;}
        .card h3{font-size:17px;margin-bottom:6px;}
        .card .meta{color:var(--muted);font-size:14px;margin-bottom:4px;}
        .card .seats{font-size:13px;color:var(--gold);font-weight:600;margin:10px 0 14px;}
        .card a.signup{display:inline-block;background:var(--magenta);color:#fff;padding:9px 16px;border-radius:8px;font-size:14px;font-weight:600;}
        .empty{color:var(--muted);padding:30px;text-align:center;border:1px dashed var(--line);border-radius:12px;}
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
</head>
<body>
    <nav class="nav">
        <a href="{{ route('public.home') }}" class="brand">
            <span class="star">&#10039;</span>
            <span>ASTELLAS<small>Gowning Qualification</small></span>
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
