<x-filament-panels::page>
    {{-- GQS hero header with header actions docked on the right (in-header, not above the table) --}}
    <div class="pg-head" style="justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:16px;min-width:0;">
            <span class="pg-head-ico"><x-filament::icon :icon="$this->gqsIcon ?? 'heroicon-o-square-3-stack-3d'" /></span>
            <div class="pg-head-tx" style="min-width:0;">
                <h1>{{ $this->gqsTitle ?? $this->getTitle() }}</h1>
                @if($this->gqsSubtitle)<p>{{ $this->gqsSubtitle }}</p>@endif
            </div>
        </div>
        @if(count($this->getCachedHeaderActions()))
            <div style="display:flex;gap:8px;flex:0 0 auto;align-items:center;">
                @foreach($this->getCachedHeaderActions() as $action)
                    {{ $action }}
                @endforeach
            </div>
        @endif
    </div>

    {{ $this->table }}

    <x-filament-actions::modals />
</x-filament-panels::page>
