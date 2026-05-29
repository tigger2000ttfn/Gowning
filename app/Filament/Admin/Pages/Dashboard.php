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
            $links[] = ['Import Personnel', \App\Filament\Admin\Pages\ImportPersonnel::getUrl(), 'heroicon-o-arrow-up-tray', '#1F6FB2'];
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

    public function getViewData(): array
    {
        return [
            'userName'    => Auth::user()?->name,
            'qualified'   => Qualification::where('status', 'qualified')->count(),
            'inProgress'  => Qualification::where('status', 'in_progress')->count(),
            'dueSoon'     => Qualification::whereNotNull('due_date')->whereBetween('due_date', [now(), now()->addDays(30)])->count(),
            'lapsed'      => Qualification::where('status', 'lapsed')->count(),
            'pendingUsers'=> User::where('approval_status', 'pending')->count(),
            'pendingRes'  => Reservation::where('status', 'requested')->count(),
            'overdueList' => Qualification::with('personnel')->whereNotNull('due_date')
                                ->whereDate('due_date', '<', now())->orderBy('due_date')->limit(6)->get(),
            'upcomingRuns'=> ClassSession::with('trainingClass')
                                ->whereDate('session_date', '>=', now())->where('status','open')
                                ->orderBy('session_date')->limit(5)->get(),
            'pendingApprovals' => User::where('approval_status', 'pending')->latest()->limit(5)->get(),
            'weekDays' => $this->buildWeek(),
            'quickLinks' => $this->buildQuickLinks(),
        ];
    }
}
