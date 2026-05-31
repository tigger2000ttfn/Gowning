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
    use \App\Filament\Admin\Concerns\HasPersonDetailModal;

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
    protected static string|\UnitEnum|null $navigationGroup = 'Classroom';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Active Bookings';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.active-bookings';

    public string $filterStatus = '';

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
}
