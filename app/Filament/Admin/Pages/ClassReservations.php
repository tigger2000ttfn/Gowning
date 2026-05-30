<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\ClassSession;
use App\Models\ClassEnrollment;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ClassReservations extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ManageClasses)
            || $u->hasCapability(Capability::ManageAttendance)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Class Reservations';
    protected static string|\UnitEnum|null $navigationGroup = 'Classroom';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Gowning Class Reservations';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.class-reservations';

    /** Enrollments grouped by class session, for the per-session list view. */
    public function getGroupedBySession(): array
    {
        return ClassSession::with(['trainingClass', 'enrollments.personnel'])
            ->orderBy('session_date')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => ($s->trainingClass?->name ?? 'Class') . ' · ' . $s->session_date?->format('l, M j, Y')
                    . ($s->start_time ? ' · ' . \Illuminate\Support\Carbon::parse($s->start_time)->format('g:i A') : ''),
                'location' => $s->location,
                'seats' => $s->seatsLeft(),
                'capacity' => $s->capacity,
                'submitted' => (bool) $s->attendance_submitted_at,
                'submitted_at' => $s->attendance_submitted_at?->format('M j, Y g:i A'),
                'rows' => $s->enrollments
                    ->whereNotIn('status', ['cancelled'])
                    ->map(fn ($e) => [
                        'id' => $e->id,
                        'name' => $e->personnel?->full_name ?? $e->name ?? 'Unknown',
                        'employee_id' => $e->employee_id,
                        'status' => $e->status,
                    ])->values()->all(),
            ])->values()->all();
    }

    public function setStatus(int $id, string $status): void
    {
        $e = ClassEnrollment::with('personnel', 'classSession')->find($id);
        if (! $e) return;
        // Cannot change attendance once the session has been submitted to QA.
        if ($e->classSession?->attendance_submitted_at) {
            Notification::make()->warning()->title('Attendance already submitted')
                ->body('Reopen the session to change attendance.')->send();
            return;
        }
        // Trainer only marks attended / no-show (draft). Completed is a QA action.
        if (! in_array($status, ['signed_up', 'attended', 'no_show'], true)) return;
        $e->markStatus($status, \Illuminate\Support\Facades\Auth::id());
        Notification::make()->success()->title('Attendance updated')->send();
    }

    /**
     * Submit a session's attendance: lock it, and push everyone marked Attended into the
     * QA Classroom Approval queue (pending_qa). The signed FORM-AST-36513 is printed before
     * this. Requires that no one is still left undecided (signed_up).
     */
    public function submitAttendance(int $sessionId): void
    {
        $s = ClassSession::with('enrollments')->find($sessionId);
        if (! $s) return;
        if ($s->attendance_submitted_at) {
            Notification::make()->warning()->title('Already submitted')->send();
            return;
        }
        $active = $s->enrollments->whereNotIn('status', ['cancelled', 'historical']);
        $undecided = $active->where('status', 'signed_up')->count();
        if ($undecided > 0) {
            Notification::make()->warning()->title('Mark everyone first')
                ->body($undecided . ' enrollee(s) are still Signed Up. Mark each Attended or No-Show before submitting.')->send();
            return;
        }
        if ($active->where('status', 'attended')->count() === 0) {
            Notification::make()->warning()->title('No attendees to submit')
                ->body('No one is marked Attended on this session.')->send();
            return;
        }

        // push attended -> pending_qa; lock the session
        foreach ($active->where('status', 'attended') as $e) {
            $e->markStatus('pending_qa', \Illuminate\Support\Facades\Auth::id());
        }
        $s->attendance_submitted_at = now();
        $s->attendance_submitted_by = \Illuminate\Support\Facades\Auth::id();
        $s->save();

        Notification::make()->success()->title('Attendance submitted to QA')
            ->body('Attendees are now in the QA Classroom Approval queue.')->send();
    }

    /** Reopen a submitted session so attendance can be corrected (admins). */
    public function reopenAttendance(int $sessionId): void
    {
        $s = ClassSession::with('enrollments')->find($sessionId);
        if (! $s) return;
        // pending_qa (not yet QA-approved) goes back to attended; QA-completed stays.
        foreach ($s->enrollments->where('status', 'pending_qa') as $e) {
            $e->markStatus('attended', \Illuminate\Support\Facades\Auth::id());
        }
        $s->attendance_submitted_at = null;
        $s->attendance_submitted_by = null;
        $s->save();
        Notification::make()->success()->title('Session reopened')->send();
    }
}
