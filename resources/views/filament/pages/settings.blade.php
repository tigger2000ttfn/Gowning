<x-filament-panels::page>
    @include('filament.page-hero', ['title' => 'Qualification Settings', 'subtitle' => 'Run counts, cycle length, and access.', 'icon' => 'heroicon-o-cog-6-tooth'])
    <form wire:submit="save">
        {{ $this->form }}
        <div style="margin-top:18px;">
            <x-filament::button type="submit">Save Settings</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
