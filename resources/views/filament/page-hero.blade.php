{{-- Clean compact page header (NOT the cosmic hero - that's dashboard-only).
     Title on the left, optional actions/tabs on the right. No subtitle (saves vertical space). --}}
<style>
    .pg-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
        padding:6px 0 12px;margin-bottom:14px;margin-top:4px;border-bottom:2px solid var(--gqs-border,#DADADF);}
    .pg-head-l{display:flex;align-items:center;gap:14px;min-width:0;}
    .pg-head-ico{width:42px;height:42px;flex:0 0 42px;border-radius:11px;display:flex;align-items:center;justify-content:center;
        background:linear-gradient(135deg,#A4123F,#850F33);box-shadow:0 3px 10px rgba(164,18,63,.28);}
    .pg-head-ico svg{width:22px;height:22px;color:#fff;}
    .pg-head-tx h1{font-size:21px;font-weight:800;margin:0;color:var(--gqs-text,#1A1A1F);line-height:1.1;}
    .pg-head-r{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
</style>
<div class="pg-head">
    <div class="pg-head-l">
        @isset($icon)<span class="pg-head-ico"><x-filament::icon :icon="$icon" /></span>@endisset
        <div class="pg-head-tx"><h1>{{ $title }}</h1></div>
    </div>
    @isset($actions)<div class="pg-head-r">{!! $actions !!}</div>@endisset
</div>
