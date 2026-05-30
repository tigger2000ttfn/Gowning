<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\ClassSession;
use App\Models\ClassEnrollment;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class QaClassroomApproval extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::QaReview) || $u->hasCapability(Capability::QaApprove)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'QA Classroom Approval';
    protected static string|\UnitEnum|null $navigationGroup = 'Review';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'QA Classroom Training Approval';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.qa-classroom-approval';

    /** Submitted sessions with attendees awaiting QA classroom approval. */
    public function getQueue(): array
    {
        return ClassSession::with(['trainingClass', 'enrollments.personnel', 'submittedBy'])
            ->whereNotNull('attendance_submitted_at')
            ->whereHas('enrollments', fn ($q) => $q->where('status', 'pending_qa'))
            ->orderBy('attendance_submitted_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => ($s->trainingClass?->name ?? 'Class') . ' · ' . $s->session_date?->format('l, M j, Y'),
                'submitted_at' => $s->attendance_submitted_at?->format('M j, Y g:i A'),
                'submitted_by' => $s->submittedBy?->name,
                'trainer' => $s->instructorUser?->name ?? $s->instructor,
                'form_url' => route('print.class-attendance', $s->id),
                'rows' => $s->enrollments->where('status', 'pending_qa')->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => $e->personnel?->full_name ?? $e->name ?? 'Unknown',
                    'employee_id' => $e->employee_id,
                ])->values()->all(),
            ])->values()->all();
    }

    /** Approve one attendee: QA reviewed the signed form -> Completed (class on file). */
    public function approve(int $enrollmentId): void
    {
        if (! Auth::user()?->hasCapability(Capability::QaApprove)) {
            Notification::make()->danger()->title('Not authorized to approve')->send();
            return;
        }
        $e = ClassEnrollment::with('personnel', 'classSession')->find($enrollmentId);
        if (! $e || $e->status !== 'pending_qa') return;
        $e->markStatus('completed', Auth::id());
        Notification::make()->success()->title('Classroom training approved')
            ->body(($e->personnel?->full_name ?? 'Trainee') . ' is now Class Complete and eligible for runs.')->send();
    }

    /** Approve every pending attendee on a submitted session at once. */
    public function approveSession(int $sessionId): void
    {
        if (! Auth::user()?->hasCapability(Capability::QaApprove)) {
            Notification::make()->danger()->title('Not authorized to approve')->send();
            return;
        }
        $s = ClassSession::with('enrollments.personnel')->find($sessionId);
        if (! $s) return;
        $n = 0;
        foreach ($s->enrollments->where('status', 'pending_qa') as $e) {
            $e->markStatus('completed', Auth::id());
            $n++;
        }
        Notification::make()->success()->title('Session approved')
            ->body($n . ' trainee(s) are now Class Complete.')->send();
    }

    /** Send one attendee back to the trainer (e.g. form issue) -> attended draft. */
    public function reject(int $enrollmentId): void
    {
        $e = ClassEnrollment::with('classSession')->find($enrollmentId);
        if (! $e || $e->status !== 'pending_qa') return;
        $e->markStatus('attended', Auth::id());
        Notification::make()->warning()->title('Returned to trainer')
            ->body('Attendee moved back to Attended for correction.')->send();
    }
}
