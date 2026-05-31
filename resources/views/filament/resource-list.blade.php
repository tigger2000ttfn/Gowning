<x-filament-panels::page>
    {{-- GQS hero header with header actions docked on the right (in-header, not above the table) --}}
    <div class="pg-head" style="justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:16px;min-width:0;">
            <span class="pg-head-ico"><x-filament::icon :icon="$this->gqsIcon ?? 'heroicon-o-square-3-stack-3d'" /></span>
            <div class="pg-head-tx" style="min-width:0;">
                <h1>{{ $this->gqsTitle ?? $this->getTitle() }}</h1>
            </div>
        </div>
        @if(count($this->getCachedHeaderActions()))
            <div style="display:flex;gap:8px;flex:0 0 auto;align-items:center;">
                <x-filament::actions :actions="$this->getCachedHeaderActions()" />
            </div>
        @endif
    </div>

    {{-- Optional data-gap / status alert boxes (only if the page provides them) --}}
    @if(method_exists($this, 'gqsAlerts') && count($alerts = $this->gqsAlerts()))
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
            @foreach($alerts as $a)
                <div style="flex:1;min-width:180px;background:var(--gqs-surface,#fff);border:1px solid {{ $a['border'] ?? 'var(--gqs-border,#E2E2E8)' }};border-left:4px solid {{ $a['accent'] ?? '#C79A2E' }};border-radius:10px;padding:12px 14px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="color:{{ $a['accent'] ?? '#C79A2E' }};">@if(!empty($a['icon']))<x-filament::icon :icon="$a['icon']" style="width:20px;height:20px;"/>@endif</span>
                        <span style="font-size:22px;font-weight:800;color:var(--gqs-text,#1A1A1F);line-height:1;">{{ $a['count'] }}</span>
                    </div>
                    <div style="font-size:12px;font-weight:700;color:var(--gqs-text,#1A1A1F);margin-top:6px;">{{ $a['label'] }}</div>
                    @if(!empty($a['hint']))<div style="font-size:11px;color:var(--gqs-text-dim,#6A6A72);margin-top:2px;">{{ $a['hint'] }}</div>@endif
                    @if(!empty($a['names']))<div style="font-size:11px;color:var(--gqs-text-dim,#6A6A72);margin-top:4px;">{{ $a['names'] }}</div>@endif
                </div>
            @endforeach
        </div>
    @endif

    {{ $this->table }}

    <x-filament-actions::modals />
</x-filament-panels::page>
