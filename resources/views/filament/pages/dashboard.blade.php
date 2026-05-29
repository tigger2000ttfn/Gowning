<x-filament-panels::page>
@php
    $stars = [];
    for ($i=0;$i<26;$i++){ $stars[]=['t'=>rand(8,86),'l'=>rand(2,97),'c'=>['#fff','#fff','#E8C24A','#B98CE0'][rand(0,3)],'s'=>rand(2,4),'d'=>rand(0,30)/10,'u'=>rand(22,45)/10]; }
@endphp

<style>
    /* tighter rhythm: one spacing variable */
    .dash-sec{margin-bottom:16px;}
    .dash-section-title{font-size:15px;font-weight:700;margin:0 0 10px;color:var(--gqs-text,#1A1A1F);display:flex;align-items:center;gap:8px;}

    /* FULL-BLEED HERO: break out of Filament's page padding entirely */
    .dash-hero{position:relative;overflow:hidden;background:#15151A;color:#fff;
        display:flex;align-items:center;gap:36px;
        padding:clamp(28px,5vw,56px) clamp(20px,5vw,56px);
        width:calc(100% + 3rem);margin-left:-1.5rem;margin-top:-1.5rem;margin-bottom:18px;border-radius:0;}
    @media (max-width:640px){.dash-hero{flex-direction:column;text-align:center;gap:18px;width:calc(100% + 2rem);margin-left:-1rem;margin-top:-1rem;}}
    .dash-hero::before{content:'';position:absolute;inset:-20%;z-index:0;background:
        radial-gradient(45% 50% at 24% 38%,rgba(126,60,168,.32),transparent 70%),
        radial-gradient(42% 48% at 80% 60%,rgba(164,18,63,.30),transparent 72%);
        filter:blur(8px);animation:dashneb 26s ease-in-out infinite;}
    @keyframes dashneb{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(3%,-3%) scale(1.07)}}
    .dash-star{position:absolute;border-radius:50%;z-index:0;animation:dashtw 3s ease-in-out infinite;}
    @keyframes dashtw{0%,100%{opacity:.3;transform:scale(.8)}50%{opacity:1;transform:scale(1.3)}}
    .dash-hero-in{position:relative;z-index:1;flex:1;}
    .dash-hero-icon{position:relative;z-index:1;width:clamp(90px,12vw,130px);height:clamp(90px,12vw,130px);flex:0 0 auto;filter:drop-shadow(0 0 14px rgba(232,194,74,.5));animation:dashFloat 6s ease-in-out infinite;}
    @keyframes dashFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
    .dash-hello{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#E8C24A;font-weight:700;}
    .dash-title{font-size:clamp(26px,4vw,38px);font-weight:800;margin:2px 0 4px;line-height:1.05;}
    .dash-sub{color:#C8C8D0;font-size:clamp(14px,2vw,16px);max-width:560px;margin:0 auto;}
    @media(min-width:641px){.dash-sub{margin:0;}}

    /* STATS: spread wider on big screens, 2-up on phones */
    .dash-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px;}
    @media(max-width:480px){.dash-grid{grid-template-columns:repeat(2,1fr);}}
    .dash-stat{border-radius:14px;padding:16px;color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.14);position:relative;overflow:hidden;}
    .dash-stat .n{font-size:30px;font-weight:800;line-height:1;}
    .dash-stat .l{font-size:13px;font-weight:600;margin-top:6px;opacity:.95;}
    .s-green{background:linear-gradient(135deg,#2E7D5B,#21563F);}
    .s-gold{background:linear-gradient(135deg,#C79A2E,#9E7818);}
    .s-purple{background:linear-gradient(135deg,#6B2C91,#4A1E66);}
    .s-red{background:linear-gradient(135deg,#C8102E,#920B22);}
    .s-charcoal{background:linear-gradient(135deg,#3A3A40,#26262C);}

    /* WEEK strip */
    .dash-week{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin-bottom:16px;}
    @media(max-width:760px){.dash-week{grid-template-columns:repeat(4,1fr);}}
    @media(max-width:440px){.dash-week{grid-template-columns:repeat(2,1fr);}}
    .wk-day{background:var(--gqs-surface,#fff);border:1px solid var(--gqs-border,#DADADF);border-radius:11px;padding:9px;min-height:84px;}
    .wk-day.today{border-color:#A4123F;box-shadow:0 0 0 1px #A4123F;}
    .wk-head{display:flex;align-items:baseline;justify-content:space-between;margin-bottom:6px;}
    .wk-name{font-size:11px;font-weight:700;color:var(--gqs-text-dim,#6A6A72);text-transform:uppercase;}
    .wk-num{font-size:17px;font-weight:800;color:var(--gqs-text,#1A1A1F);}
    .today .wk-num{color:#A4123F;}
    .wk-ev{font-size:10px;font-weight:600;padding:2px 5px;border-radius:5px;margin-bottom:3px;line-height:1.15;}
    .wk-ev.class{background:#F4E0E8;color:#A4123F;}
    .wk-ev.run{background:#FBF3DC;color:#8A6D0B;}
    html.dark .wk-ev.class{background:rgba(164,18,63,.25);color:#F0A8C0;}
    html.dark .wk-ev.run{background:rgba(199,154,46,.22);color:#E8C24A;}
    .wk-empty{font-size:11px;color:var(--gqs-text-dim,#aaa);}

    /* ACTION LISTS: 3-up wide, stack on small */
    .dash-cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-bottom:16px;}
    .dash-card{background:var(--gqs-surface,#fff);border:1px solid var(--gqs-border,#DADADF);border-radius:14px;overflow:hidden;}
    .dash-card h3{display:flex;align-items:center;gap:9px;font-size:14px;font-weight:700;padding:12px 16px;margin:0;color:#fff;}
    .dc-overdue h3{background:linear-gradient(135deg,#C8102E,#920B22);}
    .dc-runs h3{background:linear-gradient(135deg,#A4123F,#850F33);}
    .dc-appr h3{background:linear-gradient(135deg,#6B2C91,#4A1E66);}
    .dash-row{display:flex;justify-content:space-between;align-items:center;padding:9px 16px;border-bottom:1px solid var(--gqs-border,#EEE);font-size:14px;color:var(--gqs-text,#1A1A1F);}
    .dash-row:last-child{border-bottom:none;}
    .dash-row .muted{color:var(--gqs-text-dim,#6A6A72);font-size:13px;}
    .dash-empty{padding:16px;color:var(--gqs-text-dim,#888);font-size:14px;}
    .dash-row .pill{font-size:12px;font-weight:700;padding:2px 9px;border-radius:20px;}
    .pill-red{background:#FBE3E7;color:#C8102E;}
    .pill-gold{background:#FBF3DC;color:#8A6D0B;}

    /* QUICK ACCESS */
    .dash-quick{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:11px;}
    .qtile{display:flex;align-items:center;gap:11px;background:var(--gqs-surface,#fff);border:1px solid var(--gqs-border,#DADADF);border-radius:12px;padding:14px;text-decoration:none;transition:transform .12s,box-shadow .12s;}
    .qtile:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,0,0,.10);}
    .qtile-ic{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex:0 0 36px;}
    .qtile-ic svg{width:19px;height:19px;color:#fff;}
    .qtile-label{font-weight:700;font-size:14px;color:var(--gqs-text,#1A1A1F);}
</style>

<div class="dash-hero">
    @foreach($stars as $s)
        <span class="dash-star" style="top:{{$s['t']}}%;left:{{$s['l']}}%;width:{{$s['s']}}px;height:{{$s['s']}}px;background:{{$s['c']}};animation-delay:{{$s['d']}}s;animation-duration:{{$s['u']}}s;"></span>
    @endforeach
    <img src="{{ asset('images/dashboard-icon.svg') }}" alt="" class="dash-hero-icon">
    <div class="dash-hero-in">
        <div class="dash-hello">Welcome Back</div>
        <div class="dash-title">{{ $userName }}</div>
        <div class="dash-sub">Here's the current state of cleanroom gowning qualification across the site.</div>
    </div>
</div>

<div class="dash-grid">
    <div class="dash-stat s-green"><div class="n">{{ $qualified }}</div><div class="l">Qualified</div></div>
    <div class="dash-stat s-gold"><div class="n">{{ $inProgress }}</div><div class="l">In Progress</div></div>
    <div class="dash-stat s-purple"><div class="n">{{ $dueSoon }}</div><div class="l">Due Within 30 Days</div></div>
    <div class="dash-stat s-red"><div class="n">{{ $lapsed }}</div><div class="l">Lapsed</div></div>
    <div class="dash-stat s-charcoal"><div class="n">{{ $pendingUsers }}</div><div class="l">Pending Approvals</div></div>
    <div class="dash-stat s-charcoal"><div class="n">{{ $pendingRes }}</div><div class="l">Run Requests</div></div>
</div>


<div class="dash-section-title">This Week</div>
<div class="dash-week">
    @foreach($weekDays as $day)
        <div class="wk-day {{ $day['today'] ? 'today' : '' }}">
            <div class="wk-head"><span class="wk-name">{{ $day['name'] }}</span><span class="wk-num">{{ $day['num'] }}</span></div>
            @forelse($day['events'] as $ev)
                <div class="wk-ev {{ $ev['type'] }}">{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::title($ev['label']), 22) }}</div>
            @empty
                <div class="wk-empty">—</div>
            @endforelse
        </div>
    @endforeach
</div>

<div class="dash-cols">
    <div class="dash-card dc-overdue">
        <h3>Overdue Qualifications</h3>
        @forelse($overdueList as $q)
            <div class="dash-row"><span>{{ $q->personnel?->full_name ?? $q->personnel?->employee_id }}</span>
                <span class="pill pill-red">{{ $q->due_date?->format('M j, Y') }}</span></div>
        @empty<div class="dash-empty">No Overdue Qualifications.</div>@endforelse
    </div>

    <div class="dash-card dc-runs">
        <h3>Upcoming Class Sessions</h3>
        @forelse($upcomingRuns as $s)
            <div class="dash-row"><span>{{ \Illuminate\Support\Str::title($s->trainingClass?->name) }}</span>
                <span class="muted">{{ $s->session_date?->format('M j') }}</span></div>
        @empty<div class="dash-empty">No Upcoming Sessions.</div>@endforelse
    </div>

    <div class="dash-card dc-appr">
        <h3>Pending Approvals</h3>
        @forelse($pendingApprovals as $u)
            <div class="dash-row"><span>{{ $u->name }}</span><span class="pill pill-gold">Review</span></div>
        @empty<div class="dash-empty">No Accounts Awaiting Approval.</div>@endforelse
    </div>
</div>

@if(!empty($quickLinks))
<div class="dash-section-title" style="margin-top:24px;">Quick Access</div>
<div class="dash-quick">
    @foreach($quickLinks as [$label,$url,$icon,$color])
        <a href="{{ $url }}" class="qtile">
            <span class="qtile-ic" style="background:{{ $color }};"><x-filament::icon :icon="$icon" /></span>
            <span class="qtile-label">{{ $label }}</span>
        </a>
    @endforeach
</div>
@endif

</x-filament-panels::page>
