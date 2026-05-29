@extends('public.layout')
@section('title', 'Icon Styles')
@section('content')
    <section class="section">
        <h2>Clipboard — Flat Color Styles</h2>
        <p class="tab-sub">Same icon, different treatments (no gradient). Shown on charcoal as it'll appear in the hero. Tell me the name.</p>
        <style>
            .glowdemo{animation:glow 3.5s ease-in-out infinite;}
            @keyframes glow{0%,100%{filter:drop-shadow(0 0 5px rgba(232,194,74,.3));}50%{filter:drop-shadow(0 0 16px rgba(232,194,74,.55));}}
            .igrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-top:20px;}
            .icard{background:var(--charcoal);border-radius:14px;padding:30px;text-align:center;}
            .icard img{width:110px;height:110px;}
            .icard .id{color:#9A9AA2;font-size:12px;margin-top:10px;}
        </style>
        <div class="igrid">
            @foreach ([
                'clip-white' => 'Check · White',
                'clip-gold' => 'Check · Gold',
                'clip-magenta' => 'Check · Magenta',
                'clip-charcoal' => 'Check · Charcoal',
                'cliplist-white' => 'List · White',
                'cliplist-magenta' => 'List · Magenta',
            ] as $file => $label)
                <div class="icard">
                    <img class="glowdemo" src="{{ asset('images/options/' . $file . '.svg') }}">
                    <div class="id">name: {{ $file }}</div>
                </div>
            @endforeach
        </div>
        <p class="tab-sub" style="margin-top:28px;">If a flat clipboard still isn't it, say so — I can try a custom two-tone clipboard with a gold check on a white pad, or go a different shape entirely.</p>
    </section>
@endsection
