<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\ClassEnrollment;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Active Bookings: a flat list of everyone with an ACTIVE class enrollment (mirrors Active Runs for the
 * run side). Completed/historic classes are read from Class Completions, not here.
 */
class ActiveBookings extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ManageClasses) || $u->hasCapability(Capability::ManageAttendance)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Active Bookings';
    protected static string|\UnitEnum|null $navigationGroup = 'Sessions';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Active Bookings';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.active-bookings';

    public string $tab = 'roster';
    public string $filterStatus = '';
    public string $search = '';

    public function setTab(string $t): void { $this->tab = in_array($t, ['roster', 'dashboard'], true) ? $t : 'roster'; }

    public function isSuperUser(): bool { return (bool) Auth::user()?->hasCapability(Capability::ManageUsers); }

    // ===================== BOOKING DETAIL MODAL (class booking + qualification snapshot) =====================
    public ?array $bookingDetail = null;
    public function closeBooking(): void { $this->bookingDetail = null; }

    public function openBooking(int $enrollmentId): void
    {
        $e = ClassEnrollment::with(['personnel', 'classSession.trainingClass', 'classSession.instructorUser'])->find($enrollmentId);
        if (! $e) { $this->bookingDetail = null; return; }
        $p = $e->personnel;
        $session = $e->classSession;
        $status = $e->status instanceof \BackedEnum ? $e->status->value : (string) $e->status;
        [$label, $color] = $this->statusMeta($status);

        // Qualification snapshot for this person.
        $q = $p ? \App\Models\Qualification::currentFor($p->id) : null;
        $qual = null;
        if ($q) {
            $type = $q->type instanceof \BackedEnum ? $q->type->value : $q->type;
            $qstatus = $q->status instanceof \BackedEnum ? $q->status->value : $q->status;
            $dueTag = ($qstatus === 'lapsed' || $q->isPastDue()) ? 'Lapsed' : ($type === 'annual' ? 'Requal' : 'Initial');
            $qual = [
                'id' => $q->id,
                'type' => $q->sessionLabel(),
                'stage' => $q->workflow_stage ? \App\Models\WorkflowStatus::labelFor('run', $q->workflow_stage->value, $q->workflow_stage->label()) : '-',
                'status' => ucwords(str_replace('_', ' ', (string) $qstatus)),
                'due' => $q->due_date?->gmp(),
                'due_label' => $type === 'annual' ? 'Requal Due' : 'Initial Due',
                'due_tag' => $dueTag,
                'past_due' => $q->isPastDue(),
                'class_on_file' => (bool) $q->class_on_file,
                'record_url' => \App\Filament\Admin\Resources\QualificationResource::getUrl('index', ['view' => $q->id]),
                'qid' => $q->id,
            ];
        }

        $this->bookingDetail = [
            'id' => $e->id,
            'personnel_id' => $e->personnel_id,
            'name' => $p?->full_name ?? $e->name ?? 'Unknown',
            'employee_id' => $p?->employee_id ?? $e->employee_id,
            'department' => $p?->department,
            'job_title' => $p?->job_title,
            'class' => $session?->trainingClass?->name ?? 'Class',
            'session_date' => $session?->session_date?->gmp(),
            'session_past' => $session?->session_date ? $session->session_date->isPast() : false,
            'start_time' => $session?->start_time,
            'end_time' => $session?->end_time,
            'instructor' => $session?->instructorUser?->name ?? $session?->instructor,
            'location' => $session?->location,
            'status' => $status,
            'status_label' => $label,
            'status_color' => $color,
            'submitted' => (bool) $session?->attendance_submitted_at,
            'signed_up_at' => $e->signed_up_at?->gmp(),
            'attended_at' => $e->attended_at?->gmp(),
            'qual' => $qual,
            'scheduler_url' => \App\Filament\Admin\Pages\ClassScheduler::getUrl(),
        ];
    }

    /** HARD delete an enrollment (super user) - to fix stuck/duplicate bookings. */
    public function deleteBooking(int $enrollmentId): void
    {
        if (! $this->isSuperUser()) { Notification::make()->danger()->title('Not Authorized')->body('Super user required.')->send(); return; }
        $e = ClassEnrollment::find($enrollmentId);
        if (! $e) { Notification::make()->warning()->title('Already Gone')->send(); return; }
        $name = $e->personnel?->full_name ?? $e->name ?? 'Enrollment';
        $e->delete();
        if ($this->bookingDetail && ($this->bookingDetail['id'] ?? null) === $enrollmentId) $this->bookingDetail = null;
        Notification::make()->success()->title('Booking Deleted')->body($name . '\'s enrollment record was permanently removed.')->send();
    }

    public function statusOptions(): array
    {
        return [
            '' => 'All Active',
            'signed_up' => 'Scheduled',
            'attended' => 'Attended',
            'qcm_reviewed' => 'QCM Reviewed',
            'pending_qa' => 'Pending QA',
        ];
    }

    /** Status label + pill color (matches the class lifecycle lanes). */
    public function statusMeta(string $status): array
    {
        return match ($status) {
            'signed_up' => ['Scheduled', '#1F6FB2'],
            'attended' => ['Attended', '#C79A2E'],
            'qcm_reviewed' => ['QCM Reviewed', '#2563EB'],
            'pending_qa' => ['Pending QA', '#6B2C91'],
            'no_show' => ['No-Show', '#C8102E'],
            'completed' => ['QA Approved', '#2E7D5B'],
            default => [ucwords(str_replace('_', ' ', $status)), '#6B6B73'],
        };
    }

    public function rows(): array
    {
        $q = ClassEnrollment::query()
            ->with(['personnel', 'classSession.trainingClass', 'classSession.instructorUser'])
            ->whereIn('status', ClassEnrollment::ACTIVE_STATUSES);

        if ($this->filterStatus !== '' && in_array($this->filterStatus, ClassEnrollment::ACTIVE_STATUSES, true)) {
            $q->where('status', $this->filterStatus);
        }
        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $q->where(fn ($w) => $w
                ->where('name', 'ilike', $term)
                ->orWhere('employee_id', 'ilike', $term)
                ->orWhereHas('personnel', fn ($p) => $p->where('first_name', 'ilike', $term)->orWhere('last_name', 'ilike', $term)->orWhere('employee_id', 'ilike', $term)));
        }

        return $q->get()
            ->sortBy(fn ($e) => $e->classSession?->session_date?->timestamp ?? PHP_INT_MAX)
            ->map(function ($e) {
                $status = $e->status instanceof \BackedEnum ? $e->status->value : (string) $e->status;
                [$label, $color] = $this->statusMeta($status);
                $session = $e->classSession;
                return [
                    'id' => $e->id,
                    'personnel_id' => $e->personnel_id,
                    'name' => $e->personnel?->full_name ?? $e->name ?? 'Unknown',
                    'employee_id' => $e->personnel?->employee_id ?? $e->employee_id,
                    'department' => $e->personnel?->department,
                    'class' => $session?->trainingClass?->name ?? 'Class',
                    'session_date' => $session?->session_date?->gmp(),
                    'session_past' => $session?->session_date ? $session->session_date->isPast() : false,
                    'instructor' => $session?->instructorUser?->name ?? $session?->instructor,
                    'location' => $session?->location,
                    'status' => $status,
                    'status_label' => $label,
                    'status_color' => $color,
                    'submitted' => (bool) $session?->attendance_submitted_at,
                ];
            })->values()->all();
    }

    public function stats(): array
    {
        $all = ClassEnrollment::query()->whereIn('status', ClassEnrollment::ACTIVE_STATUSES)->get();
        $by = $all->groupBy(fn ($e) => $e->status instanceof \BackedEnum ? $e->status->value : (string) $e->status);
        return [
            'total' => $all->count(),
            'scheduled' => ($by['signed_up'] ?? collect())->count(),
            'attended' => ($by['attended'] ?? collect())->count(),
            'qcm' => ($by['qcm_reviewed'] ?? collect())->count(),
            'pending_qa' => ($by['pending_qa'] ?? collect())->count(),
        ];
    }

    /** Per-stage counts for the dashboard funnel. */
    public function stageFunnel(): array
    {
        $all = ClassEnrollment::query()->whereIn('status', ClassEnrollment::ACTIVE_STATUSES)->get();
        $by = $all->groupBy(fn ($e) => $e->status instanceof \BackedEnum ? $e->status->value : (string) $e->status);
        $funnel = [];
        foreach (['signed_up' => 'Scheduled', 'attended' => 'Attended', 'qcm_reviewed' => 'QCM Reviewed', 'pending_qa' => 'Pending QA'] as $k => $label) {
            [, $color] = $this->statusMeta($k);
            $funnel[] = ['key' => $k, 'label' => $label, 'count' => ($by[$k] ?? collect())->count(), 'color' => $color];
        }
        return $funnel;
    }

    /** Data-gap fix-it cards for classroom bookings. */
    public function gaps(): array
    {
        // Sessions whose date has passed but attendance was never submitted (trainer needs to take it).
        $pastNotTaken = \App\Models\ClassSession::query()
            ->whereNull('attendance_submitted_at')
            ->whereDate('session_date', '<', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->whereHas('enrollments', fn ($e) => $e->where('status', 'signed_up'))
            ->with('trainingClass')
            ->get();

        // Trainees still 'signed_up' for a session that has already passed.
        $staleSignups = ClassEnrollment::query()
            ->where('status', 'signed_up')
            ->whereHas('classSession', fn ($s) => $s->whereNull('attendance_submitted_at')->whereDate('session_date', '<', now()->toDateString()))
            ->with(['personnel', 'classSession.trainingClass'])
            ->get();

        return [
            'past_sessions' => [
                'count' => $pastNotTaken->count(),
                'items' => $pastNotTaken->take(12)->map(fn ($s) => [
                    'id' => $s->id,
                    'label' => ($s->trainingClass?->name ?? 'Class') . ' - ' . ($s->session_date?->gmp() ?? ''),
                ])->all(),
            ],
            'stale_signups' => [
                'count' => $staleSignups->count(),
                'people' => $staleSignups->take(12)->map(fn ($e) => [
                    'id' => $e->personnel_id,
                    'name' => $e->personnel?->full_name ?? $e->name ?? 'Unknown',
                    'employee_id' => $e->personnel?->employee_id ?? $e->employee_id,
                ])->all(),
            ],
        ];
    }
}
