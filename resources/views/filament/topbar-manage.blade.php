<div x-data="{ open: false }" style="position:relative;margin-right:8px;">
    <button @click="open = !open" type="button"
        style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;border:1px solid var(--astellas-magenta,#A4123F);color:var(--astellas-magenta,#A4123F);font-weight:600;font-size:14px;background:transparent;cursor:pointer;">
        <span>Manage</span>
        <span x-text="open ? '▴' : '▾'"></span>
    </button>
    <div x-show="open" @click.outside="open = false" x-cloak
        style="position:absolute;right:0;top:46px;background:#fff;border:1px solid #DCDCE2;border-radius:10px;box-shadow:0 12px 32px rgba(0,0,0,.18);min-width:230px;padding:6px;z-index:50;">
        @php
            $links = [
                ['Run Slots', \App\Filament\Admin\Resources\RunSlotResource::getUrl(), 'heroicon-o-calendar-days'],
                ['Class Completions', \App\Filament\Admin\Resources\ClassCompletionResource::getUrl(), 'heroicon-o-academic-cap'],
                ['Reservations', \App\Filament\Admin\Resources\ReservationResource::getUrl(), 'heroicon-o-ticket'],
                ['Import Personnel', \App\Filament\Admin\Pages\ImportPersonnel::getUrl(), 'heroicon-o-arrow-up-tray'],
                ['Users & Approvals', \App\Filament\Admin\Resources\UserResource::getUrl(), 'heroicon-o-user-group'],
                ['Reports', \App\Filament\Admin\Pages\Reports::getUrl(), 'heroicon-o-chart-bar'],
                ['Settings', \App\Filament\Admin\Pages\Settings::getUrl(), 'heroicon-o-cog-6-tooth'],
            ];
        @endphp
        @foreach ($links as [$label, $url, $icon])
            <a href="{{ $url }}"
               style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:7px;color:#1A1A1F;font-size:14px;font-weight:500;text-decoration:none;"
               onmouseover="this.style.background='#F4E6EC'" onmouseout="this.style.background='transparent'">
                <x-filament::icon :icon="$icon" style="width:18px;height:18px;color:#A4123F;" />
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>
