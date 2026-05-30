{{-- Clean page header (NOT the cosmic hero - that's dashboard-only).
     Light card-style header with magenta accent, icon, title, subtitle. --}}
<style>
    .pg-head{display:flex;align-items:center;gap:16px;padding:8px 0 16px;margin-bottom:18px;margin-top:6px;border-bottom:2px solid var(--gqs-border,#DADADF);}
    .pg-head-ico{width:46px;height:46px;flex:0 0 46px;border-radius:12px;display:flex;align-items:center;justify-content:center;
        background:linear-gradient(135deg,#A4123F,#850F33);box-shadow:0 3px 10px rgba(164,18,63,.28);}
    .pg-head-ico svg{width:24px;height:24px;color:#fff;}
    .pg-head-tx h1{font-size:22px;font-weight:800;margin:0;color:var(--gqs-text,#1A1A1F);}
    .pg-head-tx p{margin:3px 0 0;color:var(--gqs-text-dim,#5A5A62);font-size:14px;}
</style>
<div class="pg-head">
    @isset($icon)<span class="pg-head-ico"><x-filament::icon :icon="$icon" /></span>@endisset
    <div class="pg-head-tx">
        <h1>{{ $title }}</h1>
        @isset($subtitle)<p>{{ $subtitle }}</p>@endisset
    </div>
</div>
