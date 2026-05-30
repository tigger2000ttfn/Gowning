@php
    $u = \Illuminate\Support\Facades\Auth::user();
    $can = fn ($cap) => $u && $u->hasCapability($cap);

    // Top-level items (used often, shown standalone at the top)
    $top = [];
    if ($can(\App\Enums\Capability::ViewReports)) {
        $top[] = ['Reports', \App\Filament\Admin\Pages\Reports::getUrl(), 'heroicon-o-chart-bar'];
    }
    if ($can(\App\Enums\Capability::ManageUsers)) {
        $top[] = ['Import Personnel', \App\Filament\Admin\Pages\ImportPersonnel::getUrl(), 'heroicon-o-arrow-up-tray'];
    }
    if ($can(\App\Enums\Capability::ManageClasses) || $can(\App\Enums\Capability::ManageUsers)) {
        $top[] = ['Announcements', \App\Filament\Admin\Resources\AnnouncementResource::getUrl(), 'heroicon-o-megaphone'];
    }

    // Grouped sections (less frequent / setup)
    $sections = [];

    // Scheduling records
    $sched = [];
    if ($can(\App\Enums\Capability::ManageScheduling)) {
        $sched[] = ['Run Slots', \App\Filament\Admin\Resources\RunSlotResource::getUrl(), 'heroicon-o-calendar-days'];
        $sched[] = ['Reservations', \App\Filament\Admin\Resources\ReservationResource::getUrl(), 'heroicon-o-ticket'];
        $sched[] = ['Class Completions', \App\Filament\Admin\Resources\ClassCompletionResource::getUrl(), 'heroicon-o-academic-cap'];
    }
    if ($sched) $sections[] = ['Records', $sched];

    // Reference lists (rarely touched)
    $lists = [];
    if ($can(\App\Enums\Capability::ManagePersonnel)) {
        $lists[] = ['Departments', \App\Filament\Admin\Resources\DepartmentResource::getUrl(), 'heroicon-o-building-office'];
        $lists[] = ['Job Titles', \App\Filament\Admin\Resources\JobTitleResource::getUrl(), 'heroicon-o-briefcase'];
    }
    if ($can(\App\Enums\Capability::ManageScheduling)) {
        $lists[] = ['Cleanrooms', \App\Filament\Admin\Resources\CleanroomResource::getUrl(), 'heroicon-o-beaker'];
        $lists[] = ['Sampling Sites', \App\Filament\Admin\Resources\SamplingSiteResource::getUrl(), 'heroicon-o-hand-raised'];
    }
    if ($lists) $sections[] = ['Lists', $lists];

    // Administration / setup (rare)
    $admin = [];
    if ($can(\App\Enums\Capability::ManageUsers)) {
        $admin[] = ['Users & Approvals', \App\Filament\Admin\Resources\UserResource::getUrl(), 'heroicon-o-user-group'];
        $admin[] = ['Roles & Permissions', \App\Filament\Admin\Pages\RolePermissions::getUrl(), 'heroicon-o-shield-check'];
        $admin[] = ['Settings', \App\Filament\Admin\Pages\Settings::getUrl(), 'heroicon-o-cog-6-tooth'];
    }
    if ($admin) $sections[] = ['Setup & Settings', $admin];

    // Compliance (rare but important)
    $compliance = [];
    if ($can(\App\Enums\Capability::QaReview) || $can(\App\Enums\Capability::SystemSettings)) {
        $compliance[] = ['Audit Trail', \App\Filament\Admin\Pages\AuditTrail::getUrl(), 'heroicon-o-document-magnifying-glass'];
    }
    if ($compliance) $sections[] = ['Compliance', $compliance];

    $hasAny = count($top) || count($sections);
@endphp
@if ($hasAny)
<div x-data="{ open: false }" class="gqs-manage">
    <button @click="open = !open" type="button" class="gqs-manage-btn">
        <x-filament::icon icon="heroicon-m-squares-2x2" class="gqs-manage-ico" />
        <span>Manage</span>
        <x-filament::icon icon="heroicon-m-chevron-down" class="gqs-manage-chev" x-bind:style="open && 'transform:rotate(180deg)'" />
    </button>
    <div x-show="open" x-transition.origin.top.left @click.outside="open = false" x-cloak class="gqs-manage-menu">
        @foreach ($top as [$label, $url, $icon])
            <a href="{{ $url }}" class="gqs-manage-link">
                <x-filament::icon :icon="$icon" class="gqs-manage-link-ico" />
                <span>{{ $label }}</span>
            </a>
        @endforeach

        @foreach ($sections as [$heading, $items])
            <div class="gqs-manage-sec">{{ $heading }}</div>
            @foreach ($items as [$label, $url, $icon])
                <a href="{{ $url }}" class="gqs-manage-link">
                    <x-filament::icon :icon="$icon" class="gqs-manage-link-ico" />
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        @endforeach
    </div>
</div>
@endif
