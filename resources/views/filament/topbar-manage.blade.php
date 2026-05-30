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
    if ($can(\App\Enums\Capability::SystemSettings)) {
        $admin[] = ['Automation Rules', \App\Filament\Admin\Resources\AutomationRuleResource::getUrl(), 'heroicon-o-bolt'];
        $admin[] = ['Statuses', \App\Filament\Admin\Resources\WorkflowStatusResource::getUrl(), 'heroicon-o-swatch'];
        $admin[] = ['Email Templates', \App\Filament\Admin\Resources\EmailTemplateResource::getUrl(), 'heroicon-o-envelope'];
    }
    // Notification Settings is available to everyone (personal prefs)
    $admin[] = ['Notification Settings', \App\Filament\Admin\Pages\NotificationSettings::getUrl(), 'heroicon-o-bell-alert'];
    if ($admin) $sections[] = ['Setup & Settings', $admin];

    // Team & Assignments (managers)
    $team = [];
    if ($can(\App\Enums\Capability::ManageScheduling)) {
        $team[] = ['QCM Team View', \App\Filament\Admin\Pages\QcmTeamView::getUrl(), 'heroicon-o-user-group'];
    }
    if ($can(\App\Enums\Capability::QaReview) || $can(\App\Enums\Capability::QaApprove)) {
        $team[] = ['QA Team View', \App\Filament\Admin\Pages\QaTeamView::getUrl(), 'heroicon-o-clipboard-document-check'];
    }
    if ($team) $sections[] = ['Team & Assignments', $team];

    // Compliance (rare but important)
    $compliance = [];
    if ($can(\App\Enums\Capability::QaReview) || $can(\App\Enums\Capability::RecordRuns) || $can(\App\Enums\Capability::ManageScheduling)) {
        $compliance[] = ['Incubation', \App\Filament\Admin\Pages\IncubationBoard::getUrl(), 'heroicon-o-beaker'];
        $compliance[] = ['Non-Conformances', \App\Filament\Admin\Resources\NonConformanceResource::getUrl(), 'heroicon-o-exclamation-triangle'];
    }
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

        @if (count($top) && count($sections))
            <div class="gqs-manage-divider"></div>
        @endif

        @foreach ($sections as $si => [$heading, $items])
            <div x-data="{ sub: false }" class="gqs-manage-sub">
                <button type="button" @click="sub = !sub" class="gqs-manage-subbtn">
                    <span>{{ $heading }}</span>
                    <span class="gqs-manage-subcount">{{ count($items) }}</span>
                    <x-filament::icon icon="heroicon-m-chevron-down" class="gqs-manage-subchev" x-bind:style="sub && 'transform:rotate(180deg)'" />
                </button>
                <div x-show="sub" x-transition x-cloak class="gqs-manage-subitems">
                    @foreach ($items as [$label, $url, $icon])
                        <a href="{{ $url }}" class="gqs-manage-link gqs-manage-sublink">
                            <x-filament::icon :icon="$icon" class="gqs-manage-link-ico" />
                            <span>{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif
