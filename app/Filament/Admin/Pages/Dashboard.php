<?php

namespace App\Filament\Admin\Pages;

use App\Models\Qualification;
use App\Models\Reservation;
use App\Models\ClassSession;
use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?int $navigationSort = -100;

    protected string $view = 'filament.pages.dashboard';

    public function getHeading(): string { return ''; }

    public function getFooterWidgets(): array
    {
        return [
            \App\Filament\Admin\Widgets\QualStatusChart::class,
            \App\Filament\Admin\Widgets\RunsTrendChart::class,
        ];
    }

    public function getColumns(): int|array { return 2; }

    /** Latest comment activity across all qualifications, with a link back to each record. */
    public function recentComments(): array
    {
        return \App\Models\QualificationComment::with(['qualification.personnel', 'user'])
            ->latest()->limit(6)->get()
            ->map(fn ($c) => [
                'author' => $c->author_name ?: ($c->user?->name ?? 'System'),
                'body' => \Illuminate\Support\Str::limit($c->body, 110),
                'person' => $c->qualification?->personnel?->full_name ?: 'Unknown',
                'when' => $c->created_at?->diffForHumans(),
                'url' => $c->qualification_id
                    ? \App\Filament\Admin\Resources\QualificationResource::getUrl('view', ['record' => $c->qualification_id])
                    : null,
            ])->all();
    }

    protected function buildQuickLinks(): array
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        if (! $u) return [];
        $links = [];
        if ($u->hasCapability(\App\Enums\Capability::ManageScheduling)) {
            $links[] = ['Run Slots', \App\Filament\Admin\Resources\RunSlotResource::getUrl(), 'heroicon-o-calendar-days', '#A4123F'];
            $links[] = ['Class Completions', \App\Filament\Admin\Resources\ClassCompletionResource::getUrl(), 'heroicon-o-academic-cap', '#6B2C91'];
            $links[] = ['Reservations', \App\Filament\Admin\Resources\ReservationResource::getUrl(), 'heroicon-o-ticket', '#C79A2E'];
        }
        if ($u->hasCapability(\App\Enums\Capability::ViewReports)) {
            $links[] = ['Reports', \App\Filament\Admin\Pages\Reports::getUrl(), 'heroicon-o-chart-bar', '#2E7D5B'];
        }
        if ($u->hasCapability(\App\Enums\Capability::ManageUsers)) {
            $links[] = ['Import LMS Data', \App\Filament\Admin\Pages\ImportPersonnel::getUrl(), 'heroicon-o-arrow-up-tray', '#1F6FB2'];
            $links[] = ['Users & Approvals', \App\Filament\Admin\Resources\UserResource::getUrl(), 'heroicon-o-user-group', '#A4123F'];
            $links[] = ['Settings', \App\Filament\Admin\Pages\Settings::getUrl(), 'heroicon-o-cog-6-tooth', '#3A3A40'];
        }
        return $links;
    }

    protected function buildWeek(): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = now()->addDays($i)->startOfDay();
            $classes = ClassSession::with('trainingClass')
                ->whereDate('session_date', $day->toDateString())->where('status', 'open')->get()
                ->map(fn ($s) => ['type' => 'class', 'label' => $s->trainingClass?->name, 'time' => $s->start_time]);
            $runs = \App\Models\RunSlot::whereDate('slot_date', $day->toDateString())->where('status', 'open')->get()
                ->map(fn ($s) => ['type' => 'run', 'label' => $s->cleanroom . ' Run', 'time' => $s->start_time]);
            $days[] = [
                'name' => $day->format('D'),
                'num' => $day->format('j'),
                'today' => $i === 0,
                'events' => $classes->concat($runs)->all(),
            ];
        }
        return $days;
    }

    /** Determine the user's primary dashboard role from their capabilities. */
    public function dashboardRole(): string
    {
        $u = Auth::user();
        if (! $u) return 'operator';
        $has = fn ($c) => $u->hasCapability($c);
        if ($has(\App\Enums\Capability::QaApprove) || $has(\App\Enums\Capability::QaReview)) return 'qa';
        if ($has(\App\Enums\Capability::ManageScheduling) || $has(\App\Enums\Capability::RecordRuns)) return 'qcm';
        if ($has(\App\Enums\Capability::ViewReports) || $has(\App\Enums\Capability::ManageUsers)) return 'manager';
        return 'operator';
    }

    /** Role-specific focus panels (counts + lists tuned to what that role acts on). */
    public function rolePanels(string $role): array
    {
        return match ($role) {
            'qa' => [
                'label' => 'QA Focus',
                'stats' => [
                    ['Awaiting Sign-off', \App\Models\Qualification::whereIn('workflow_stage', ['qa_review', 'results_released'])->count(), 'heroicon-o-clipboard-document-check', '#A4123F'],
                    ['Open Non-Conformances', \App\Models\NonConformance::where('status', 'open')->count(), 'heroicon-o-exclamation-triangle', '#C8102E'],
                    ['My Assigned Approvals', \App\Models\Qualification::where('qa_owner_id', Auth::id())->whereIn('workflow_stage', ['qa_review','results_released'])->count(), 'heroicon-o-user-circle', '#6B2C91'],
                ],
            ],
            'qcm' => [
                'label' => 'QC Micro Focus',
                'stats' => [
                    ['Run Requests', Reservation::where('status', 'requested')->count(), 'heroicon-o-inbox-arrow-down', '#A4123F'],
                    ['Incubating', \App\Models\Qualification::where('workflow_stage', 'incubating')->count(), 'heroicon-o-beaker', '#C79A2E'],
                    ['Run Days This Week', \App\Models\RunSlot::whereBetween('slot_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->where('status','open')->count(), 'heroicon-o-calendar-days', '#2E7D5B'],
                    ['Not Yet Scheduled', \App\Models\Qualification::where('workflow_stage', 'class_complete')->count(), 'heroicon-o-clock', '#6B2C91'],
                ],
            ],
            'manager' => [
                'label' => 'Compliance Focus',
                'stats' => [
                    ['Qualified', Qualification::where('status','qualified')->count(), 'heroicon-o-check-badge', '#2E7D5B'],
                    ['Overdue', Qualification::whereNotNull('due_date')->whereDate('due_date','<',now())->count(), 'heroicon-o-exclamation-circle', '#C8102E'],
                    ['Due In 30 Days', Qualification::whereNotNull('due_date')->whereBetween('due_date', [now(), now()->addDays(30)])->count(), 'heroicon-o-bell-alert', '#C79A2E'],
                    ['Lapsed', Qualification::where('status','lapsed')->count(), 'heroicon-o-x-circle', '#A4123F'],
                ],
            ],
            default => [
                'label' => 'My Qualification',
                'stats' => [],
            ],
        };
    }

    public function getViewData(): array
    {
        $me = Auth::user();
        $myPersonnel = $me?->personnel
            ?? \App\Models\Personnel::where('email', $me?->email)->first();
        $myQual = $myPersonnel
            ? \App\Models\Qualification::where('personnel_id', $myPersonnel->id)->first()
            : null;

        $role = $this->dashboardRole();

        return [
            'dashRole'    => $role,
            'rolePanels'  => $this->rolePanels($role),
            'userName'    => $me?->name,
            'qualified'   => Qualification::where('status', 'qualified')->count(),
            'inProgress'  => Qualification::where('status', 'in_progress')->count(),
            'dueSoon'     => Qualification::whereNotNull('due_date')->whereBetween('due_date', [now(), now()->addDays(30)])->count(),
            'lapsed'      => Qualification::where('status', 'lapsed')->count(),
            'classSignups'=> \App\Models\ClassEnrollment::where('status', 'signed_up')->count(),
            'pendingRes'  => Reservation::where('status', 'requested')->count(),
            'overdueList' => Qualification::with('personnel')->whereNotNull('due_date')
                                ->whereDate('due_date', '<', now())->orderBy('due_date')->limit(6)->get(),
            'upcomingRuns'=> ClassSession::with('trainingClass')
                                ->whereDate('session_date', '>=', now())->where('status','open')
                                ->orderBy('session_date')->limit(5)->get(),
            'classSignupList' => \App\Models\ClassEnrollment::with(['personnel', 'classSession.trainingClass'])
                                ->where('status', 'signed_up')->latest('signed_up_at')->limit(6)->get(),
            'failedRuns'  => \App\Models\QualificationRun::with('personnel')
                                ->where('result', 'fail')->latest('run_date')->limit(5)->get(),
            'runRequests' => Reservation::with(['personnel', 'runSlot'])
                                ->where('status', 'requested')->latest()->limit(5)->get(),
            'recentComments' => \App\Models\QualificationComment::with(['qualification.personnel'])
                                    ->latest()->limit(6)->get(),
            'myQual'      => $myQual,
            'myName'      => $myPersonnel?->full_name ?? $me?->name,
            'weekDays' => $this->buildWeek(),
            'quickLinks' => $this->buildQuickLinks(),
        ];
    }
}
