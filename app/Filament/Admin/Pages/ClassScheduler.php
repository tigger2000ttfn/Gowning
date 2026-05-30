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

    // single-session attendance focus (Sessions tab drills into one sheet)
    public ?int $focusSessionId = null;
    public function focusSession(int $id): void { $this->focusSessionId = $id; }
    public function unfocusSession(): void { $this->focusSessionId = null; }

    public function mount(): void
    {
        // deep-link from Class Reservations: ?attend=SESSION_ID opens the Attendance tab on that session
        $attend = request()->integer('attend');
        if ($attend) {
            $this->tab = 'attendance';
            $this->focusSessionId = $attend;
        }
    }

    // ----- Session detail / reschedule modal (Sessions tab: move & adjust) -----
    public ?int $detailSessionId = null;
    public array $editSession = [];

    public function openSessionDetail(int $id): void
    {
        $s = \App\Models\ClassSession::with('trainingClass')->find($id);
        if (! $s) return;
        $this->detailSessionId = $id;
        $this->editSession = [
            'session_date' => $s->session_date?->toDateString(),
            'start_time' => $s->start_time ? \Illuminate\Support\Carbon::parse($s->start_time)->format('H:i') : null,
            'end_time' => $s->end_time ? \Illuminate\Support\Carbon::parse($s->end_time)->format('H:i') : null,
            'location' => $s->location,
            'capacity' => $s->capacity,
            'assigned_instructor_id' => $s->assigned_instructor_id,
        ];
    }
    public function closeSessionDetail(): void { $this->detailSessionId = null; $this->editSession = []; }

    public function saveSessionDetail(): void
    {
        $s = \App\Models\ClassSession::find($this->detailSessionId);
        if (! $s) return;
        if ($s->attendance_submitted_at) {
            Notification::make()->warning()->title('Locked')->body('Attendance has been submitted for this session.')->send();
            return;
        }
        $s->update([
            'session_date' => $this->editSession['session_date'] ?: $s->session_date,
            'start_time' => $this->editSession['start_time'] ?: null,
            'end_time' => $this->editSession['end_time'] ?: null,
            'location' => $this->editSession['location'] ?: null,
            'capacity' => $this->editSession['capacity'] ?: $s->capacity,
            'assigned_instructor_id' => $this->editSession['assigned_instructor_id'] ?: null,
        ]);
        $this->closeSessionDetail();
        Notification::make()->success()->title('Session Updated')->send();
    }

    // ----- Schedule a waiting person into a class session (Overview tab) -----
    public bool $showSchedule = false;
    public ?int $schedulePersonnelId = null;
    public ?int $scheduleSessionId = null;
    public string $scheduleName = '';

    /** Open future sessions with an open seat (id => label). */
    public function openSessionOptions(): array
    {
        return \App\Models\ClassSession::with('trainingClass')
            ->where('status', '!=', 'cancelled')
            ->whereNull('attendance_submitted_at')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')->get()
            ->filter(fn ($s) => $s->seatsLeft() > 0)
            ->mapWithKeys(fn ($s) => [$s->id => ($s->trainingClass?->name ?? 'Class') . ' · ' . $s->session_date?->format('M j, Y')
                . ($s->start_time ? ' (' . \Illuminate\Support\Carbon::parse($s->start_time)->format('g:i A') . ')' : '')
                . ' · ' . $s->seatsLeft() . ' seats'])
            ->all();
    }

    public function openSchedule(int $personnelId): void
    {
        $p = \App\Models\Personnel::find($personnelId);
        if (! $p) return;
        $this->schedulePersonnelId = $personnelId;
        $this->scheduleName = $p->full_name;
        $this->scheduleSessionId = null;
        $this->showSchedule = true;
    }

    public function saveSchedule(): void
    {
        if (! $this->schedulePersonnelId || ! $this->scheduleSessionId) {
            Notification::make()->warning()->title('Pick A Class Date')->send();
            return;
        }
        $this->enrollPersonIntoSession((int) $this->scheduleSessionId);
    }

    public function scheduleNextAvailable(): void
    {
        $next = \App\Models\ClassSession::where('status', '!=', 'cancelled')
            ->whereNull('attendance_submitted_at')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')->get()
            ->first(fn ($s) => $s->seatsLeft() > 0);
        if (! $next) { Notification::make()->warning()->title('No Open Session')->body('No future class session has an open seat.')->send(); return; }
        $this->enrollPersonIntoSession($next->id);
    }

    protected function enrollPersonIntoSession(int $sessionId): void
    {
        $session = \App\Models\ClassSession::find($sessionId);
        $p = \App\Models\Personnel::find($this->schedulePersonnelId);
        if (! $session || ! $p) return;
        if ($session->seatsLeft() <= 0) {
            Notification::make()->warning()->title('Session Full')->send();
            return;
        }
        $exists = \App\Models\ClassEnrollment::where('class_session_id', $session->id)
            ->where('personnel_id', $p->id)->whereNotIn('status', ['cancelled'])->exists();
        if ($exists) {
            Notification::make()->warning()->title('Already Enrolled')->body($p->full_name . ' is already in that session.')->send();
            $this->showSchedule = false;
            return;
        }
        \App\Models\ClassEnrollment::create([
            'class_session_id' => $session->id,
            'personnel_id' => $p->id,
            'employee_id' => $p->employee_id,
            'name' => $p->full_name,
            'email' => $p->email,
            'status' => 'signed_up',
            'signed_up_at' => now(),
        ]);
        $this->showSchedule = false;
        Notification::make()->success()->title('Scheduled')
            ->body($p->full_name . ' booked into ' . ($session->trainingClass?->name ?? 'class') . ' on ' . $session->session_date?->format('M j, Y') . '.')->send();
    }

    // reusable in-app confirmation modal (no native system prompts)
    public array $confirm = [];
    public function askConfirm(string $method, $arg, string $title, string $body, ?string $confirmLabel = null, bool $danger = false): void
    {
        $this->confirm = compact('method', 'arg', 'title', 'body', 'danger') + ['label' => $confirmLabel ?? 'Confirm'];
    }
    public function runConfirm(): void
    {
        $m = $this->confirm['method'] ?? null;
        $allowed = ['submitAttendance', 'reopenAttendance', 'cancelSession', 'rescheduleEnrollment'];
        if ($m && in_array($m, $allowed, true)) {
            $this->{$m}($this->confirm['arg']);
        }
        $this->confirm = [];
    }
    public function cancelConfirm(): void { $this->confirm = []; }

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
            ['Need A Class', $needClass, 'heroicon-o-exclamation-circle', 'magenta'],
            ['Signed Up', $signedUp, 'heroicon-o-user-group', 'charcoal'],
            ['Sessions This Week', $sessionsThisWeek, 'heroicon-o-calendar-days', 'green'],
            ['Open Seats (Upcoming)', $openSeats, 'heroicon-o-user-plus', 'gold'],
            ['Class Templates', $templates, 'heroicon-o-rectangle-stack', 'purple'],
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
                'personnel_id' => $q->personnel_id,
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
        // Only staff designated as qualified to deliver classroom training are selectable.
        return \App\Models\User::where('is_active', true)
            ->where('can_teach', true)
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    // ----- Attendance form: pick the trainer in a modal, then open the prefilled PDF -----
    public bool $showAttendanceForm = false;
    public ?int $attendanceSessionId = null;
    public ?int $attendanceTrainerId = null;

    public function openAttendanceForm(int $sessionId): void
    {
        $s = \App\Models\ClassSession::find($sessionId);
        $this->attendanceSessionId = $sessionId;
        // default to the assigned instructor if they are teach-qualified
        $this->attendanceTrainerId = $s?->assigned_instructor_id;
        $this->showAttendanceForm = true;
    }

    public function generateAttendanceForm(): void
    {
        if (! $this->attendanceSessionId) return;
        $trainer = $this->attendanceTrainerId
            ? (\App\Models\User::find($this->attendanceTrainerId)?->name ?? '')
            : '';
        $sess = \App\Models\ClassSession::find($this->attendanceSessionId);
        $url = route('print.class-attendance', [$this->attendanceSessionId, 'FORM-AST-36513-' . ($sess?->session_uid ?: 'Class') . '.pdf']) . '?trainer=' . urlencode($trainer);
        $this->showAttendanceForm = false;
        $this->dispatch('open-url', url: $url);
    }

    // ----- Attendance marking (trainer/admin work, lives on the scheduler) -----

    /** Attendees for one session, for the attendance roster. */
    public function sessionAttendees(int $sessionId): array
    {
        $s = \App\Models\ClassSession::with('enrollments.personnel')->find($sessionId);
        if (! $s) return [];
        return $s->enrollments->whereNotIn('status', ['cancelled'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->personnel?->full_name ?? $e->name ?? 'Unknown',
                'employee_id' => $e->employee_id,
                'status' => $e->status,
                'note' => $e->attendance_note,
            ])->values()->all();
    }

    public function markAttendance(int $enrollmentId, string $status): void
    {
        if (! in_array($status, ['attended', 'no_show', 'signed_up'], true)) return;
        $e = \App\Models\ClassEnrollment::with('classSession')->find($enrollmentId);
        if (! $e) return;
        if ($e->classSession?->attendance_submitted_at) {
            Notification::make()->warning()->title('Attendance Already Submitted')
                ->body('Reopen the session to change attendance.')->send();
            return;
        }
        // toggle off if the same status is tapped again -> back to signed_up
        $new = ($e->status === $status) ? 'signed_up' : $status;
        $e->markStatus($new, \Illuminate\Support\Facades\Auth::id());
        // no toast: the highlighted button is the save confirmation
    }

    public function saveAttendanceNote(int $enrollmentId, ?string $note): void
    {
        $e = \App\Models\ClassEnrollment::with('classSession')->find($enrollmentId);
        if (! $e || $e->classSession?->attendance_submitted_at) return;
        $e->attendance_note = $note;
        $e->save();
    }

    /** Reschedule one attendee to the next available open session. */
    public function rescheduleEnrollment(int $enrollmentId): void
    {
        $this->moveEnrollmentToNextOpen($enrollmentId);
    }

    /** Mark every still-unmarked attendee on a session as Attended. */
    public function markAllAttended(int $sessionId): void
    {
        $s = \App\Models\ClassSession::with('enrollments')->find($sessionId);
        if (! $s || $s->attendance_submitted_at) return;
        $n = 0;
        foreach ($s->enrollments->whereIn('status', ['signed_up']) as $e) {
            $e->markStatus('attended', \Illuminate\Support\Facades\Auth::id());
            $n++;
        }
        Notification::make()->success()->title('Marked Attended')->body($n . ' marked attended.')->send();
    }

    // ----- Reschedule modal (attendance): pick a date OR book next available -----
    public bool $showReschedule = false;
    public ?int $rescheduleEnrollmentId = null;
    public ?int $rescheduleSessionId = null;
    public string $rescheduleName = '';

    public function openReschedule(int $enrollmentId): void
    {
        $e = \App\Models\ClassEnrollment::find($enrollmentId);
        if (! $e) return;
        $this->rescheduleEnrollmentId = $enrollmentId;
        $this->rescheduleName = $e->name ?: ($e->personnel?->full_name ?? 'Trainee');
        $this->rescheduleSessionId = null;
        $this->showReschedule = true;
    }

    public function rescheduleToSelected(): void
    {
        if (! $this->rescheduleSessionId) { Notification::make()->warning()->title('Pick A Date')->send(); return; }
        $this->moveEnrollment($this->rescheduleEnrollmentId, (int) $this->rescheduleSessionId);
        $this->showReschedule = false;
    }

    public function rescheduleNextAvailable(): void
    {
        $this->moveEnrollmentToNextOpen($this->rescheduleEnrollmentId);
        $this->showReschedule = false;
    }

    protected function moveEnrollment(?int $enrollmentId, int $sessionId): void
    {
        $e = \App\Models\ClassEnrollment::with('classSession')->find($enrollmentId);
        $target = \App\Models\ClassSession::find($sessionId);
        if (! $e || ! $target || $e->classSession?->attendance_submitted_at) return;
        if ($target->seatsLeft() <= 0) { Notification::make()->warning()->title('Session Full')->send(); return; }
        $e->class_session_id = $target->id;
        $e->status = 'signed_up';
        $e->save();
        Notification::make()->success()->title('Rescheduled')
            ->body($e->name . ' moved to ' . ($target->trainingClass?->name ?? 'class') . ' on ' . $target->session_date?->format('M j, Y') . '.')->send();
    }

    protected function moveEnrollmentToNextOpen(?int $enrollmentId): void
    {
        $e = \App\Models\ClassEnrollment::with('classSession')->find($enrollmentId);
        if (! $e || $e->classSession?->attendance_submitted_at) return;
        $next = \App\Models\ClassSession::where('status', '!=', 'cancelled')
            ->whereNull('attendance_submitted_at')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->where('id', '!=', $e->class_session_id)
            ->orderBy('session_date')->get()
            ->first(fn ($s) => $s->seatsLeft() > 0);
        if (! $next) {
            Notification::make()->warning()->title('No Open Session')->body('No future session has an open seat to move them to.')->send();
            return;
        }
        $e->class_session_id = $next->id;
        $e->status = 'signed_up';
        $e->save();
        Notification::make()->success()->title('Rescheduled')
            ->body($e->name . ' moved to ' . ($next->trainingClass?->name ?? 'class') . ' on ' . $next->session_date?->format('M j, Y') . '.')->send();
    }

    /** Submit a session's attendance: lock it and push attendees to QA classroom approval. */
    public function setAttendanceTrainer(int $sessionId, $userId): void
    {
        $s = \App\Models\ClassSession::find($sessionId);
        if (! $s || $s->attendance_submitted_at) return;
        $s->assigned_instructor_id = $userId ?: null;
        $s->save();
        Notification::make()->success()->title('Trainer Updated')->send();
    }

    public function submitAttendance(int $sessionId): void
    {
        $s = \App\Models\ClassSession::with('enrollments')->find($sessionId);
        if (! $s) return;
        if ($s->attendance_submitted_at) { Notification::make()->warning()->title('Already submitted')->send(); return; }
        $active = $s->enrollments->whereNotIn('status', ['cancelled', 'historical']);
        $undecided = $active->where('status', 'signed_up')->count();
        if ($undecided > 0) {
            Notification::make()->warning()->title('Mark Everyone First')
                ->body($undecided . ' enrollee(s) are still Signed Up. Mark each Attended or No-Show before submitting.')->send();
            return;
        }
        if ($active->where('status', 'attended')->count() === 0) {
            Notification::make()->warning()->title('No Attendees To Submit')->body('No one is marked Attended on this session.')->send();
            return;
        }
        foreach ($active->where('status', 'attended') as $e) {
            $e->markStatus('pending_qa', \Illuminate\Support\Facades\Auth::id());
        }
        // The submitter signs the attendance. If no trainer is on record, the signer
        // becomes the trainer of record (their name flows onto FORM-AST-36513).
        if (! $s->assigned_instructor_id) {
            $s->assigned_instructor_id = \Illuminate\Support\Facades\Auth::id();
        }
        $s->attendance_submitted_at = now();
        $s->attendance_submitted_by = \Illuminate\Support\Facades\Auth::id();
        $s->save();
        Notification::make()->success()->title('Attendance Submitted To QA')
            ->body('Attendees are now in the QA Classroom Approval queue.')->send();
    }

    public function reopenAttendance(int $sessionId): void
    {
        $s = \App\Models\ClassSession::with('enrollments')->find($sessionId);
        if (! $s) return;
        foreach ($s->enrollments->where('status', 'pending_qa') as $e) {
            $e->markStatus('attended', \Illuminate\Support\Facades\Auth::id());
        }
        $s->attendance_submitted_at = null;
        $s->attendance_submitted_by = null;
        $s->save();
        Notification::make()->success()->title('Session Reopened')->send();
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
        if ($s->attendance_submitted_at) {
            Notification::make()->warning()->title('Locked')->body('Attendance is submitted; cannot cancel.')->send();
            return;
        }
        // Return active enrollees to the queue so they can be rebooked.
        $moved = \App\Models\ClassEnrollment::where('class_session_id', $s->id)
            ->whereNotIn('status', ['cancelled', 'completed', 'historical'])
            ->update(['status' => 'cancelled']);
        $s->update(['status' => 'cancelled']);
        $this->detailSessionId = null;
        Notification::make()->success()->title('Session Cancelled')
            ->body($moved > 0 ? $moved . ' enrolled trainee(s) returned to the queue to be rescheduled.' : 'No enrollees to move.')->send();
    }
}
