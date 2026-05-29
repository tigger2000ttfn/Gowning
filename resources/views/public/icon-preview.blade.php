@extends('public.layout')
@section('title', 'Icon Options')
@section('content')
    <section class="section">
        <h2>Hero Icon Options</h2>
        <p class="tab-sub">All shown with the glow animation. Tell me the name of the one you want.</p>
        <style>
            .glowdemo{filter:drop-shadow(0 0 6px rgba(232,194,74,.5));animation:glow 3.5s ease-in-out infinite;}
            @keyframes glow{0%,100%{filter:drop-shadow(0 0 5px rgba(232,194,74,.35));}50%{filter:drop-shadow(0 0 16px rgba(200,16,46,.6));}}
            .igrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-top:20px;}
            .icard{background:var(--charcoal);border-radius:14px;padding:30px;text-align:center;}
            .icard img{width:120px;height:120px;}
            .icard .nm{color:#fff;margin-top:12px;font-weight:600;font-size:15px;}
            .icard .id{color:#9A9AA2;font-size:12px;}
        </style>
        <div class="igrid">
            @foreach ([
                ['user-shield','Qualified Person (shield)'],
                ['clipboard-check','Qualification Checklist'],
                ['head-side-mask','Masked Head'],
                ['flask','Lab Flask'],
                ['microscope','Microscope'],
                ['shield-halved','Shield'],
                ['labcoat','Lab Coat'],
                ['glove','Glove'],
                ['hero-icon','Current (Mask + Glasses)'],
            ] as [$file,$label])
                <div class="icard">
                    <img class="glowdemo" src="{{ asset('images/' . ($file === 'hero-icon' ? 'hero-icon.svg' : 'options/' . $file . '.svg')) }}">
                    <div class="nm">{{ $label }}</div>
                    <div class="id">name: {{ $file }}</div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
