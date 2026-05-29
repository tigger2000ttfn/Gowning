<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ClassSession;
use App\Models\Personnel;
use App\Models\Qualification;
use App\Models\Reservation;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverviewStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Overview';

    protected function getStats(): array
    {
        $qualified = Qualification::where('status', 'qualified')->count();
        $inProgress = Qualification::where('status', 'in_progress')->count();
        $lapsed = Qualification::where('status', 'lapsed')->count();
        $dueSoon = Qualification::whereNotNull('due_date')
            ->whereBetween('due_date', [now(), now()->addDays(30)])->count();
        $pendingUsers = User::where('approval_status', 'pending')->count();
        $pendingRes = Reservation::where('status', 'requested')->count();

        return [
            Stat::make('Qualified', $qualified)
                ->description('Currently certified')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success'),
            Stat::make('In Progress', $inProgress)
                ->description('Working through runs')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('warning'),
            Stat::make('Due Within 30 Days', $dueSoon)
                ->description('Upcoming requalification')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
            Stat::make('Lapsed', $lapsed)
                ->description('Past due date')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
            Stat::make('Pending Approvals', $pendingUsers)
                ->description('Accounts awaiting review')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color($pendingUsers > 0 ? 'warning' : 'gray'),
            Stat::make('Run Requests', $pendingRes)
                ->description('Reservations to approve')
                ->descriptionIcon('heroicon-m-ticket')
                ->color($pendingRes > 0 ? 'warning' : 'gray'),
        ];
    }
}
