<x-filament-panels::page>
    @include('filament.page-hero', [
        'title' => $this->gqsTitle ?? $this->getTitle(),
        'subtitle' => $this->gqsSubtitle ?? '',
        'icon' => $this->gqsIcon ?? 'heroicon-o-square-3-stack-3d',
    ])

    @if(count($this->getCachedHeaderActions()))
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:14px;">
            @foreach($this->getCachedHeaderActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    @endif

    {{ $this->table }}

    <x-filament-actions::modals />
</x-filament-panels::page>
