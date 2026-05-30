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

    /** Enrollments grouped by class session, booking-management view. */
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

    /** Open future sessions a booking can be moved to (id => label). */
    public function openSessions(): array
    {
        return ClassSession::with('trainingClass')
            ->where('status', '!=', 'cancelled')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')
            ->get()
            ->mapWithKeys(fn ($s) => [$s->id => ($s->trainingClass?->name ?? 'Class') . ' · ' . $s->session_date?->format('M j, Y')])
            ->all();
    }

    // ---- Booking management: move / reschedule / cancel (NOT attendance) ----

    public bool $showMove = false;
    public ?int $moveEnrollmentId = null;
    public ?int $moveSessionId = null;
    public string $moveName = '';

    // reusable in-app confirmation modal (no native system prompts)
    public array $confirm = [];
    public function askConfirm(string $method, $arg, string $title, string $body, ?string $confirmLabel = null, bool $danger = false): void
    {
        $this->confirm = compact('method', 'arg', 'title', 'body', 'danger') + ['label' => $confirmLabel ?? 'Confirm'];
    }
    public function runConfirm(): void
    {
        $m = $this->confirm['method'] ?? null;
        $allowed = ['reschedule', 'cancelBooking'];
        if ($m && in_array($m, $allowed, true)) {
            $this->{$m}($this->confirm['arg']);
        }
        $this->confirm = [];
    }
    public function cancelConfirm(): void { $this->confirm = []; }

    public function openMove(int $enrollmentId): void
    {
        $e = ClassEnrollment::with('personnel')->find($enrollmentId);
        if (! $e) return;
        $this->moveEnrollmentId = $enrollmentId;
        $this->moveName = $e->personnel?->full_name ?? $e->name ?? 'Trainee';
        $this->moveSessionId = null;
        $this->showMove = true;
    }

    public function move(): void
    {
        $e = ClassEnrollment::with('classSession')->find($this->moveEnrollmentId);
        if (! $e || ! $this->moveSessionId) {
            Notification::make()->warning()->title('Pick A Session')->send();
            return;
        }
        if ($e->classSession?->attendance_submitted_at) {
            Notification::make()->warning()->title('Locked')->body('This session\'s attendance is already submitted.')->send();
            return;
        }
        $target = ClassSession::find($this->moveSessionId);
        if (! $target) return;
        if ($target->seatsLeft() <= 0) {
            Notification::make()->warning()->title('Session Full')->send();
            return;
        }
        $e->class_session_id = $this->moveSessionId;
        $e->status = 'signed_up';
        $e->save();
        $this->showMove = false;
        Notification::make()->success()->title('Booking Moved')->send();
    }

    /** Move to the next available future session automatically. */
    public function reschedule(int $enrollmentId): void
    {
        $e = ClassEnrollment::with('classSession')->find($enrollmentId);
        if (! $e) return;
        if ($e->classSession?->attendance_submitted_at) {
            Notification::make()->warning()->title('Locked')->body('This session\'s attendance is already submitted.')->send();
            return;
        }
        $next = ClassSession::where('status', '!=', 'cancelled')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->where('id', '!=', $e->class_session_id)
            ->orderBy('session_date')->get()
            ->first(fn ($s) => $s->seatsLeft() > 0);
        if (! $next) {
            Notification::make()->warning()->title('No Open Session')->body('No future session has an open seat.')->send();
            return;
        }
        $e->class_session_id = $next->id;
        $e->status = 'signed_up';
        $e->save();
        Notification::make()->success()->title('Rescheduled')
            ->body('Moved to ' . ($next->trainingClass?->name ?? 'class') . ' on ' . $next->session_date?->format('M j, Y') . '.')->send();
    }

    public function cancelBooking(int $enrollmentId): void
    {
        $e = ClassEnrollment::with('classSession')->find($enrollmentId);
        if (! $e) return;
        if ($e->classSession?->attendance_submitted_at) {
            Notification::make()->warning()->title('Locked')->body('This session\'s attendance is already submitted.')->send();
            return;
        }
        $e->markStatus('cancelled', \Illuminate\Support\Facades\Auth::id());
        Notification::make()->success()->title('Booking Cancelled')->send();
    }
}
