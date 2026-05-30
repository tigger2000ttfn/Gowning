<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\User;
use App\Models\RunSlot;
use App\Models\ClassSession;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class QcmTeamView extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && $u->hasCapability(Capability::ManageScheduling));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return false; } // in Manage
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'QCM Team View';
    protected static ?string $title = 'QC Micro Team & Assignments';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.qcm-team-view';

    public function getAnalysts()
    {
        $analysts = User::where('is_active', true)->get()
            ->filter(fn ($u) => $u->hasCapability(Capability::RecordRuns) || $u->hasCapability(Capability::ManageScheduling));

        $today = now()->toDateString();

        return $analysts->map(function ($u) use ($today) {
            $runDays = RunSlot::where('assigned_analyst_id', $u->id)
                ->whereDate('slot_date', '>=', $today)->where('status', 'open')
                ->orderBy('slot_date')->get();
            $classes = ClassSession::where('assigned_instructor_id', $u->id)
                ->whereDate('session_date', '>=', $today)
                ->orderBy('session_date')->get();
            return (object) [
                'name' => $u->name,
                'run_days' => $runDays,
                'classes' => $classes,
                'load' => $runDays->count() + $classes->count(),
            ];
        })->sortByDesc('load')->values();
    }

    public function getUnassignedRunDays()
    {
        return RunSlot::whereNull('assigned_analyst_id')
            ->whereDate('slot_date', '>=', now()->toDateString())
            ->where('status', 'open')->orderBy('slot_date')->get();
    }
}
