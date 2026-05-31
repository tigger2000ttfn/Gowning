<x-filament-panels::page>
    @php $q = $record; $q->loadMissing('personnel', 'children'); @endphp

    {{-- Back link + breadcrumb header --}}
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap;">
        <a href="{{ \App\Filament\Admin\Resources\QualificationResource::getUrl('index') }}" class="gqs-btn gqs-btn-ghost" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <x-filament::icon icon="heroicon-m-arrow-left" style="width:16px;height:16px;"/> Back To Active Runs
        </a>
        <span style="color:var(--gqs-text-dim,#9A9AA4);font-size:13px;">Active Runs / {{ $q->personnel?->full_name ?? 'Record' }}</span>
    </div>

    {{-- Reuse the detail content (it reads $getRecord; provide it) --}}
    @include('filament.qualification-detail', ['getRecord' => fn () => $q])
</x-filament-panels::page>
