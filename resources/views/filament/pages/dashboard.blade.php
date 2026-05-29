<x-filament-panels::page>
@php
    $stars = [];
    for ($i=0;$i<26;$i++){ $stars[]=['t'=>rand(8,86),'l'=>rand(2,97),'c'=>['#fff','#fff','#E8C24A','#B98CE0'][rand(0,3)],'s'=>rand(2,4),'d'=>rand(0,30)/10,'u'=>rand(22,45)/10]; }
@endphp

<style>
    .dash-hero{position:relative;overflow:hidden;background:#15151A;border-radius:16px;padding:30px 32px;color:#fff;margin-bottom:22px;}
    .dash-hero::before{content:'';position:absolute;inset:-20%;z-index:0;background:
        radial-gradient(45% 50% at 24% 38%,rgba(126,60,168,.32),transparent 70%),
        radial-gradient(42% 48% at 80% 60%,rgba(164,18,63,.30),transparent 72%);
        filter:blur(8px);animation:dashneb 26s ease-in-out infinite;}
    @keyframes dashneb{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(3%,-3%) scale(1.07)}}
    .dash-star{position:absolute;border-radius:50%;z-index:0;animation:dashtw 3s ease-in-out infinite;}
    @keyframes dashtw{0%,100%{opacity:.3;transform:scale(.8)}50%{opacity:1;transform:scale(1.3)}}
    .dash-hero-in{position:relative;z-index:1;}
    .dash-hello{font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#E8C24A;font-weight:700;}
    .dash-title{font-size:30px;font-weight:800;margin:4px 0 4px;}
    .dash-sub{color:#C8C8D0;font-size:15px;}
    .dash-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(168px,1fr));gap:14px;margin-bottom:22px;}
    .dash-stat{border-radius:14px;padding:18px 18px;color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.14);position:relative;overflow:hidden;}
    .dash-stat .n{font-size:32px;font-weight:800;line-height:1;}
    .dash-stat .l{font-size:13px;font-weight:600;margin-top:7px;opacity:.95;}
    .dash-stat .ic{position:absolute;right:14px;top:14px;width:26px;height:26px;opacity:.35;}
    .s-green{background:linear-gradient(135deg,#2E7D5B,#21563F);}
    .s-gold{background:linear-gradient(135deg,#C79A2E,#9E7818);}
    .s-purple{background:linear-gradient(135deg,#6B2C91,#4A1E66);}
    .s-red{background:linear-gradient(135deg,#C8102E,#920B22);}
    .s-charcoal{background:linear-gradient(135deg,#3A3A40,#26262C);}
    .dash-cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;}
    .dash-card{background:var(--gqs-surface,#fff);border:1px solid var(--gqs-border,#DADADF);border-radius:14px;overflow:hidden;}
    .dash-card h3{display:flex;align-items:center;gap:9px;font-size:15px;font-weight:700;padding:14px 18px;margin:0;color:#fff;}
    .dc-overdue h3{background:linear-gradient(135deg,#C8102E,#920B22);}
    .dc-runs h3{background:linear-gradient(135deg,#A4123F,#850F33);}
    .dc-appr h3{background:linear-gradient(135deg,#6B2C91,#4A1E66);}
    .dash-row{display:flex;justify-content:space-between;align-items:center;padding:10px 18px;border-bottom:1px solid var(--gqs-border,#EEE);font-size:14px;color:var(--gqs-text,#1A1A1F);}
    .dash-row:last-child{border-bottom:none;}
    .dash-row .muted{color:var(--gqs-text-dim,#6A6A72);font-size:13px;}
    .dash-empty{padding:18px;color:var(--gqs-text-dim,#888);font-size:14px;}
    .dash-row .pill{font-size:12px;font-weight:700;padding:2px 9px;border-radius:20px;}
    .pill-red{background:#FBE3E7;color:#C8102E;}
    .pill-gold{background:#FBF3DC;color:#8A6D0B;}
</style>

<div class="dash-hero">
    @foreach($stars as $s)
        <span class="dash-star" style="top:{{$s['t']}}%;left:{{$s['l']}}%;width:{{$s['s']}}px;height:{{$s['s']}}px;background:{{$s['c']}};animation-delay:{{$s['d']}}s;animation-duration:{{$s['u']}}s;"></span>
    @endforeach
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
</x-filament-panels::page>
