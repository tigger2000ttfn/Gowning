@php
    $u = \Illuminate\Support\Facades\Auth::user();
    $can = fn ($cap) => $u && $u->hasCapability($cap);
    $links = [];
    if ($can(\App\Enums\Capability::ManageScheduling)) {
        $links[] = ['Run Slots', \App\Filament\Admin\Resources\RunSlotResource::getUrl(), 'heroicon-o-calendar-days'];
        $links[] = ['Class Completions', \App\Filament\Admin\Resources\ClassCompletionResource::getUrl(), 'heroicon-o-academic-cap'];
        $links[] = ['Reservations', \App\Filament\Admin\Resources\ReservationResource::getUrl(), 'heroicon-o-ticket'];
    }
    if ($can(\App\Enums\Capability::ViewReports)) {
        $links[] = ['Reports', \App\Filament\Admin\Pages\Reports::getUrl(), 'heroicon-o-chart-bar'];
    }
    if ($can(\App\Enums\Capability::ManageUsers)) {
        $links[] = ['Import Personnel', \App\Filament\Admin\Pages\ImportPersonnel::getUrl(), 'heroicon-o-arrow-up-tray'];
        $links[] = ['Users & Approvals', \App\Filament\Admin\Resources\UserResource::getUrl(), 'heroicon-o-user-group'];
        $links[] = ['Roles & Permissions', \App\Filament\Admin\Pages\RolePermissions::getUrl(), 'heroicon-o-shield-check'];
        $links[] = ['Settings', \App\Filament\Admin\Pages\Settings::getUrl(), 'heroicon-o-cog-6-tooth'];
    }
    if ($can(\App\Enums\Capability::ManageClasses) || $can(\App\Enums\Capability::ManageUsers)) {
        $links[] = ['Announcements', \App\Filament\Admin\Resources\AnnouncementResource::getUrl(), 'heroicon-o-megaphone'];
    }
    if ($can(\App\Enums\Capability::ManagePersonnel)) {
        $links[] = ['Departments', \App\Filament\Admin\Resources\DepartmentResource::getUrl(), 'heroicon-o-building-office'];
        $links[] = ['Job Titles', \App\Filament\Admin\Resources\JobTitleResource::getUrl(), 'heroicon-o-briefcase'];
    }
    if ($can(\App\Enums\Capability::ManageScheduling)) {
        $links[] = ['Cleanrooms', \App\Filament\Admin\Resources\CleanroomResource::getUrl(), 'heroicon-o-beaker'];
        $links[] = ['Sampling Sites', \App\Filament\Admin\Resources\SamplingSiteResource::getUrl(), 'heroicon-o-hand-raised'];
    }
    if ($can(\App\Enums\Capability::QaReview) || $can(\App\Enums\Capability::SystemSettings)) {
        $links[] = ['Audit Trail', \App\Filament\Admin\Pages\AuditTrail::getUrl(), 'heroicon-o-document-magnifying-glass'];
    }
@endphp
@if (count($links))
<div x-data="{ open: false }" class="gqs-manage">
    <button @click="open = !open" type="button" class="gqs-manage-btn">
        <x-filament::icon icon="heroicon-m-squares-2x2" class="gqs-manage-ico" />
        <span>Manage</span>
        <x-filament::icon icon="heroicon-m-chevron-down" class="gqs-manage-chev" x-bind:style="open && 'transform:rotate(180deg)'" />
    </button>
    <div x-show="open" x-transition.origin.top.left @click.outside="open = false" x-cloak class="gqs-manage-menu">
        @foreach ($links as [$label, $url, $icon])
            <a href="{{ $url }}" class="gqs-manage-link">
                <x-filament::icon :icon="$icon" class="gqs-manage-link-ico" />
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </div>
</div>
@endif
