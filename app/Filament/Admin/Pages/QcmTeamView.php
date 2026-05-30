<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\User;
use App\Models\RunSlot;
use App\Models\ClassSession;
use App\Models\Setting;
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

    public string $tab = 'overview';   // overview | table | cards | calendar
    public ?int $assignSlotId = null;
    public ?int $assignAnalystId = null;
    public bool $showAssign = false;

    /** Members of the QCM team: explicit team flag, or fallback to capability holders. */
    protected function teamMembers()
    {
        $byTeam = User::where('is_active', true)->where('team', 'qcm')->get();
        if ($byTeam->isNotEmpty()) return $byTeam;
        return User::where('is_active', true)->get()
            ->filter(fn ($u) => $u->hasCapability(Capability::RecordRuns) || $u->hasCapability(Capability::ManageScheduling))
            ->values();
    }

    public function manager(): ?User
    {
        $id = Setting::get('qcm_manager_id');
        return $id ? User::find($id) : null;
    }

    public function getAnalysts()
    {
        $today = now()->toDateString();
        return $this->teamMembers()->map(function ($u) use ($today) {
            $runDays = RunSlot::where('assigned_analyst_id', $u->id)
                ->whereDate('slot_date', '>=', $today)->where('status', 'open')
                ->orderBy('slot_date')->get();
            $classes = ClassSession::where('assigned_instructor_id', $u->id)
                ->whereDate('session_date', '>=', $today)
                ->orderBy('session_date')->get();
            return (object) [
                'id' => $u->id,
                'name' => $u->name,
                'is_manager' => (bool) $u->is_team_manager,
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

    public function getUnassignedClasses()
    {
        return ClassSession::with('trainingClass')
            ->whereNull('assigned_instructor_id')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->where('status', 'open')->orderBy('session_date')->get();
    }

    // Assign instructor to a class session
    public ?int $assignSessionId = null;
    public ?int $assignInstructorId = null;
    public bool $showAssignInstructor = false;

    public function openAssignInstructor(int $sessionId): void
    {
        $this->assignSessionId = $sessionId;
        $this->assignInstructorId = ClassSession::find($sessionId)?->assigned_instructor_id;
        $this->showAssignInstructor = true;
    }

    public function saveAssignInstructor(): void
    {
        $s = ClassSession::find($this->assignSessionId);
        if ($s) {
            $s->assigned_instructor_id = $this->assignInstructorId ?: null;
            $s->save();
            \Filament\Notifications\Notification::make()->success()->title('Instructor assigned')->send();
        }
        $this->showAssignInstructor = false;
    }

    /** Calendar data: upcoming run days with their assigned analyst, grouped by date. */
    public function getCalendar(): array
    {
        $today = now()->toDateString();
        $slots = RunSlot::with('analyst')
            ->whereDate('slot_date', '>=', $today)
            ->whereDate('slot_date', '<=', now()->addDays(42)->toDateString())
            ->where('status', '!=', 'cancelled')
            ->orderBy('slot_date')->orderBy('start_time')->get();

        return $slots->groupBy(fn ($s) => $s->slot_date->format('Y-m-d'))
            ->map(fn ($group, $day) => [
                'date' => \Illuminate\Support\Carbon::parse($day)->format('D, d M'),
                'rows' => $group->map(fn ($s) => [
                    'cleanroom' => $s->cleanroom,
                    'time' => $s->start_time ? \Illuminate\Support\Carbon::parse($s->start_time)->format('H:i') : null,
                    'analyst' => $s->analyst?->name,
                    'slot_id' => $s->id,
                ])->all(),
            ])->values()->all();
    }

    /** Analyst options for the assign modal. */
    public function analystOptions(): array
    {
        return $this->teamMembers()->mapWithKeys(fn ($u) => [$u->id => $u->name])->all();
    }

    public function openAssign(int $slotId): void
    {
        $this->assignSlotId = $slotId;
        $this->assignAnalystId = RunSlot::find($slotId)?->assigned_analyst_id;
        $this->showAssign = true;
    }

    public function saveAssign(): void
    {
        $slot = RunSlot::find($this->assignSlotId);
        if ($slot) {
            $slot->assigned_analyst_id = $this->assignAnalystId ?: null;
            $slot->save();
            \Filament\Notifications\Notification::make()->success()->title('Analyst assigned')->send();
        }
        $this->showAssign = false;
    }
}
