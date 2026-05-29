{{-- Reusable branded page hero. Pass $title, $subtitle, $icon (heroicon name). --}}
<style>
    .pg-hero{position:relative;overflow:hidden;background:#15151A;color:#fff;
        padding:30px 40px;margin:-32px -32px 24px;display:flex;align-items:center;gap:24px;}
    .pg-hero::before{content:'';position:absolute;inset:-20%;z-index:0;background:
        radial-gradient(45% 50% at 22% 40%,rgba(126,60,168,.30),transparent 70%),
        radial-gradient(42% 48% at 82% 60%,rgba(164,18,63,.28),transparent 72%);
        filter:blur(8px);animation:pgneb 26s ease-in-out infinite;}
    @keyframes pgneb{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(3%,-3%) scale(1.06)}}
    .pg-hero-ico{position:relative;z-index:1;width:54px;height:54px;color:#E8C24A;flex:0 0 54px;}
    .pg-hero-tx{position:relative;z-index:1;}
    .pg-hero-tx h1{font-size:24px;font-weight:800;margin:0;color:#fff;}
    .pg-hero-tx p{margin:4px 0 0;color:#C8C8D0;font-size:14px;}
</style>
<div class="pg-hero">
    @isset($icon)<x-filament::icon :icon="$icon" class="pg-hero-ico" />@endisset
    <div class="pg-hero-tx">
        <h1>{{ $title }}</h1>
        @isset($subtitle)<p>{{ $subtitle }}</p>@endisset
    </div>
</div>
