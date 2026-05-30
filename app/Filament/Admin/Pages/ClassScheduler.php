<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\TrainingClass;
use App\Models\ClassSession;
use App\Models\ClassEnrollment;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ClassScheduler extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ManageClasses) || $u->hasCapability(Capability::ManageScheduling)));
    }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Class Scheduler';
    protected static string|\UnitEnum|null $navigationGroup = 'Classroom';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Class Scheduler';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.class-scheduler';

    public string $tab = 'overview';   // overview | classes | sessions

    // ---- session add / generate form ----
    public bool $showAddSession = false;
    public ?int $sessClassId = null;
    public ?string $sessDate = null;
    public ?string $sessStart = '09:00';
    public ?string $sessEnd = '11:00';
    public ?string $sessLocation = null;
    public ?int $sessCapacity = null;
    public ?int $sessInstructorId = null;
    public bool $sessRepeat = false;
    public string $sessPattern = 'weekly';   // weekly | biweekly | monthly
    public ?string $sessUntil = null;

    // ---- class (template) add form ----
    public bool $showAddClass = false;
    public ?string $clsName = null;
    public ?string $clsCode = null;
    public ?int $clsValidity = 12;
    public ?int $clsCapacity = 12;
    public ?string $clsLocation = null;
    public bool $clsPrereq = true;

    public string $sessSort = 'session_date';
    public string $sessDir = 'asc';

    // ===== Overview =====
    public function overviewStats(): array
    {
        $needClass = \App\Models\Qualification::where('workflow_stage', \App\Enums\WorkflowStage::ClassPending->value)->count();
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();
        $sessionsThisWeek = ClassSession::where('status', '!=', 'cancelled')->whereBetween('session_date', [$weekStart, $weekEnd])->count();
        $openSeats = ClassSession::where('status', 'open')->whereDate('session_date', '>=', now()->toDateString())->get()
            ->sum(fn ($s) => $s->seatsLeft());
        $signedUp = ClassEnrollment::where('status', 'signed_up')->count();
        $templates = TrainingClass::where('is_published', true)->count();

        return [
            ['Need A Class', $needClass, 'heroicon-o-exclamation-circle', '#A4123F'],
            ['Signed Up', $signedUp, 'heroicon-o-user-group', '#1F6FB2'],
            ['Sessions This Week', $sessionsThisWeek, 'heroicon-o-calendar-days', '#2E7D5B'],
            ['Open Seats (Upcoming)', $openSeats, 'heroicon-o-user-plus', '#C79A2E'],
            ['Class Templates', $templates, 'heroicon-o-rectangle-stack', '#6B2C91'],
        ];
    }

    /** People who still need the gowning class (Class Pending) and are not yet signed up. */
    public function needingClass(): array
    {
        $enrolled = ClassEnrollment::whereIn('status', ['signed_up', 'attended'])
            ->pluck('personnel_id')->filter()->unique()->all();
        return \App\Models\Qualification::with('personnel')
            ->where('workflow_stage', \App\Enums\WorkflowStage::ClassPending->value)
            ->whereNotIn('personnel_id', $enrolled)
            ->get()
            ->map(fn ($q) => [
                'name' => $q->personnel?->full_name ?? 'Unknown',
                'employee_id' => $q->personnel?->employee_id,
                'department' => $q->personnel?->department,
                'since' => $q->stage_changed_at?->diffForHumans(),
            ])->values()->all();
    }

    // ===== Classes (templates) =====
    public function templates()
    {
        return TrainingClass::withCount(['sessions'])->orderBy('name')->get();
    }

    public function addClass(): void
    {
        if (! static::allowed()) return;
        if (! $this->clsName) { Notification::make()->danger()->title('Name required')->send(); return; }
        TrainingClass::create([
            'name' => $this->clsName,
            'code' => $this->clsCode,
            'validity_months' => $this->clsValidity ?: 12,
            'default_capacity' => $this->clsCapacity ?: 12,
            'default_location' => $this->clsLocation,
            'is_gowning_prerequisite' => $this->clsPrereq,
            'is_published' => true,
        ]);
        $this->showAddClass = false;
        $this->reset(['clsName', 'clsCode', 'clsLocation']);
        Notification::make()->success()->title('Class template created')->send();
    }

    // ===== Sessions =====
    public function classOptions(): array
    {
        return TrainingClass::orderBy('name')->pluck('name', 'id')->all();
    }
    public function instructorOptions(): array
    {
        return \App\Models\User::where('is_active', true)
            ->where(fn ($q) => $q->where('team', 'qcm')->orWhere('is_team_manager', true))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public function sessions()
    {
        $rows = ClassSession::with(['trainingClass', 'instructorUser'])
            ->where('status', '!=', 'cancelled')
            ->whereDate('session_date', '>=', now()->subDays(7)->toDateString())
            ->get()
            ->map(function ($s) {
                $s->booked = $s->enrollments()->whereIn('status', ['signed_up', 'attended', 'completed'])->count();
                $s->seats_left = $s->seatsLeft();
                return $s;
            });
        $dir = $this->sessDir === 'desc' ? -1 : 1;
        $rows = $rows->sort(function ($a, $b) {
            return match ($this->sessSort) {
                'class' => strcmp((string) $a->trainingClass?->name, (string) $b->trainingClass?->name),
                'booked' => $a->booked <=> $b->booked,
                default => ($a->session_date->toDateString() . ($a->start_time ?? '')) <=> ($b->session_date->toDateString() . ($b->start_time ?? '')),
            };
        })->values();
        if ($dir === -1) $rows = $rows->reverse()->values();
        return $rows;
    }

    public function sortSessions(string $field): void
    {
        if ($this->sessSort === $field) { $this->sessDir = $this->sessDir === 'asc' ? 'desc' : 'asc'; }
        else { $this->sessSort = $field; $this->sessDir = 'asc'; }
    }

    public function addSession(): void
    {
        if (! static::allowed()) return;
        if (! $this->sessClassId || ! $this->sessDate) {
            Notification::make()->danger()->title('Pick a class and date')->send();
            return;
        }
        $tpl = TrainingClass::find($this->sessClassId);
        $base = [
            'training_class_id' => $this->sessClassId,
            'start_time' => $this->sessStart ?: null,
            'end_time' => $this->sessEnd ?: null,
            'location' => $this->sessLocation ?: $tpl?->default_location,
            'capacity' => $this->sessCapacity ?: ($tpl?->default_capacity ?: 12),
            'assigned_instructor_id' => $this->sessInstructorId ?: null,
            'status' => 'open',
        ];

        $dates = [\Illuminate\Support\Carbon::parse($this->sessDate)];
        if ($this->sessRepeat && $this->sessUntil) {
            $until = \Illuminate\Support\Carbon::parse($this->sessUntil);
            $cursor = \Illuminate\Support\Carbon::parse($this->sessDate);
            $guard = 0;
            while ($guard < 400) {
                $cursor = match ($this->sessPattern) {
                    'biweekly' => $cursor->copy()->addWeeks(2),
                    'monthly' => $cursor->copy()->addMonth(),
                    default => $cursor->copy()->addWeek(),
                };
                if ($cursor->gt($until)) break;
                $dates[] = $cursor; $guard++;
            }
        }

        $created = 0;
        foreach ($dates as $d) {
            $exists = ClassSession::where('training_class_id', $this->sessClassId)
                ->whereDate('session_date', $d->toDateString())
                ->where('start_time', $this->sessStart ?: null)
                ->where('status', '!=', 'cancelled')->exists();
            if ($exists) continue;
            ClassSession::create(array_merge($base, ['session_date' => $d->toDateString()]));
            $created++;
        }
        $this->showAddSession = false;
        $this->reset(['sessLocation', 'sessInstructorId', 'sessCapacity', 'sessRepeat', 'sessUntil']);
        Notification::make()->success()->title($created > 1 ? "{$created} sessions generated" : 'Session added')
            ->body($created === 0 ? 'All matching sessions already existed.' : '')->send();
    }

    public function cancelSession(int $id): void
    {
        if (! static::allowed()) return;
        $s = ClassSession::find($id);
        if (! $s) return;
        $s->update(['status' => 'cancelled']);
        Notification::make()->success()->title('Session cancelled')->send();
    }
}
