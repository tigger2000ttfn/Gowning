<div x-data="{ open: false }" class="gqs-manage">
    <button @click="open = !open" type="button" class="gqs-manage-btn">
        <x-filament::icon icon="heroicon-m-squares-2x2" class="gqs-manage-ico" />
        <span>Manage</span>
        <x-filament::icon icon="heroicon-m-chevron-down" class="gqs-manage-chev" x-bind:style="open && 'transform:rotate(180deg)'" />
    </button>
    <div x-show="open" x-transition.origin.top.left @click.outside="open = false" x-cloak class="gqs-manage-menu">
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
            <a href="{{ $url }}" class="gqs-manage-link">
                <x-filament::icon :icon="$icon" class="gqs-manage-link-ico" />
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </div>
</div>
