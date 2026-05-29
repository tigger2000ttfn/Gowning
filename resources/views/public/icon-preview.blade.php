@extends('public.layout')
@section('title', 'Icon Options')
@section('content')
    <section class="section">
        <h2>Font Awesome Icons — Flat Gold</h2>
        <p class="tab-sub">Real Font Awesome glyphs, flat gold, no gradient, on charcoal. Glow on. Tell me the name (e.g. "fa-medal").</p>
        <style>
            .glowdemo{animation:glow 3.8s ease-in-out infinite;}
            @keyframes glow{0%,100%{filter:drop-shadow(0 0 6px rgba(232,194,74,.3));}50%{filter:drop-shadow(0 0 16px rgba(232,194,74,.55));}}
            .igrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-top:20px;}
            .icard{background:var(--charcoal);border-radius:14px;padding:26px;text-align:center;}
            .icard img{width:96px;height:96px;}
            .icard .nm{color:#fff;margin-top:10px;font-weight:600;font-size:13px;}
            .icard .id{color:#9A9AA2;font-size:11px;}
        </style>
        <div class="igrid">
            @foreach ([
                'fa-user-shield'=>'Qualified Person','fa-user-check'=>'Verified Person',
                'fa-user-doctor'=>'Personnel','fa-id-badge'=>'ID Badge',
                'fa-award'=>'Award','fa-medal'=>'Medal','fa-certificate'=>'Certificate',
                'fa-shield-halved'=>'Shield','fa-shield-heart'=>'Shield Heart',
                'fa-circle-check'=>'Check','fa-clipboard-check'=>'Clipboard Check',
                'fa-hand-sparkles'=>'Clean Hand','fa-spray-can-sparkles'=>'Sanitize',
                'fa-wind'=>'Airflow','fa-vial'=>'Vial','fa-star'=>'Star',
            ] as $file=>$label)
                <div class="icard">
                    <img class="glowdemo" src="{{ asset('images/options/' . $file . '.svg') }}">
                    <div class="nm">{{ $label }}</div>
                    <div class="id">{{ $file }}</div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
