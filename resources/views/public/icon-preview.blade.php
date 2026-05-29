@extends('public.layout')
@section('title', 'Icon Options')
@section('content')
    <section class="section">
        <h2>More Icon Options</h2>
        <p class="tab-sub">Subtle gold gradient, shown on charcoal as in the hero. Glow on. Tell me the name.</p>
        <style>
            .glowdemo{animation:glow 3.8s ease-in-out infinite;}
            @keyframes glow{0%,100%{filter:drop-shadow(0 0 6px rgba(232,194,74,.3));}50%{filter:drop-shadow(0 0 18px rgba(232,194,74,.6));}}
            .igrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-top:20px;}
            .icard{background:var(--charcoal);border-radius:14px;padding:30px;text-align:center;}
            .icard img{width:112px;height:112px;}
            .icard .nm{color:#fff;margin-top:12px;font-weight:600;font-size:14px;}
            .icard .id{color:#9A9AA2;font-size:12px;}
        </style>
        <div class="igrid">
            @foreach ([
                'user-shield' => 'Qualified Person',
                'shield-heart' => 'Protective Shield',
                'award' => 'Qualification / Award',
                'vial' => 'Sample Vial',
                'atom' => 'Science / Atom',
                'droplet' => 'Sterile / Droplet',
                'wind' => 'HEPA Airflow',
                'temperature-half' => 'Controlled Env.',
            ] as $file => $label)
                <div class="icard">
                    <img class="glowdemo" src="{{ asset('images/options/' . $file . '.svg') }}">
                    <div class="nm">{{ $label }}</div>
                    <div class="id">name: {{ $file }}</div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
