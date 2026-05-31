<?php

namespace App\Filament\Admin\Pages;

use App\Models\ClassEnrollment;
use App\Models\Qualification;
use App\Enums\Capability;
use App\Enums\WorkflowStage;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ClassBoard extends Page
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

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Class Board';
    protected static string|\UnitEnum|null $navigationGroup = 'Classroom';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Class Board';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.class-board';

    public array $lanes = [
        'signed_up'    => ['label' => 'Scheduled',    'color' => '#1F6FB2'],
        'attended'     => ['label' => 'Attended',     'color' => '#C79A2E'],
        'no_show'      => ['label' => 'No-Show',      'color' => '#C8102E'],
        'qcm_reviewed' => ['label' => 'QCM Reviewed', 'color' => '#2563EB'],
        'pending_qa'   => ['label' => 'Pending QA',   'color' => '#6B2C91'],
        'completed'    => ['label' => 'QA Approved',  'color' => '#2E7D5B'],
    ];

    public string $groupBy = '';

    public function groupByOptions(): array
    {
        return ['' => 'No Grouping', 'department' => 'Department', 'class' => 'Class', 'instructor' => 'Instructor', 'session_date' => 'Session Date'];
    }

    /**
     * Swimlanes: the same status columns split into horizontal bands by a grouping
     * field (department or class). No grouping = a single band (the whole board).
     */
    public function getSwimlanes(): array
    {
        $cols = $this->getColumns();
        if ($this->groupBy === '' || ! array_key_exists($this->groupBy, $this->groupByOptions())) {
            return [['label' => null, 'key' => '_all', 'columns' => $cols]];
        }
        $field = $this->groupBy; // 'department' | 'class' | 'instructor' | 'session_date'
        $values = collect($cols)
            ->flatMap(fn ($c) => collect($c['cards'])->pluck($field))
            ->map(fn ($v) => ($v === null || $v === '') ? '—' : $v)
            ->unique()->values()->all();
        if ($this->groupBy === 'session_date') {
            // Sort the date bands chronologically (parallel Y-m-d key), '—' (no session) last.
            $sortKey = [];
            foreach ($cols as $col) {
                foreach ($col['cards'] as $c) {
                    $disp = ($c['session_date'] === null || $c['session_date'] === '') ? '—' : $c['session_date'];
                    $sortKey[$disp] = $c['session_sort'] ?? '9999-12-31';
                }
            }
            usort($values, fn ($a, $b) => ($sortKey[$a] ?? '9999-12-31') <=> ($sortKey[$b] ?? '9999-12-31'));
        } else {
            sort($values);
        }
        if (empty($values)) {
            return [['label' => null, 'key' => '_all', 'columns' => $cols]];
        }
        $lanes = [];
        foreach ($values as $val) {
            $count = 0;
            $forGroup = [];
            foreach ($cols as $status => $col) {
                $col['cards'] = array_values(array_filter($col['cards'], function ($c) use ($field, $val) {
                    $cv = $c[$field] ?? null;
                    $cv = ($cv === null || $cv === '') ? '—' : $cv;
                    return $cv === $val;
                }));
                $count += count($col['cards']);
                $forGroup[$status] = $col;
            }
            $lanes[] = ['label' => $val, 'count' => $count, 'key' => \Illuminate\Support\Str::slug($val) ?: 'na', 'columns' => $forGroup];
        }
        return $lanes;
    }

    /** Historical (archived completed) enrollments, shown in a collapsed far-right lane.
     *  An automation will move Completed -> Historical after a retention period. */
    public function getArchive(): array
    {
        $cards = ClassEnrollment::with(['personnel', 'classSession.trainingClass'])
            ->where('status', 'historical')
            ->latest('updated_at')->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->personnel?->full_name ?? $e->employee_id ?? 'Unknown',
                'employee_id' => $e->personnel?->employee_id ?? $e->employee_id,
                'class' => $e->classSession?->trainingClass?->name,
                'date' => $e->classSession?->session_date?->gmp(),
            ])->values()->all();
        return [
            'label' => \App\Models\WorkflowStatus::labelFor('class', 'historical', 'Historical'),
            'color' => \App\Models\WorkflowStatus::colorFor('class', 'historical', '#5A5A62'),
            'cards' => $cards,
        ];
    }

    /** People who still need the gowning class and aren't signed up to any session yet. */
    public function getNeedsClass(): array
    {
        $enrolled = ClassEnrollment::whereIn('status', ['signed_up', 'attended'])
            ->pluck('personnel_id')->filter()->unique()->all();
        return \App\Models\Qualification::with('personnel')
            ->where('workflow_stage', \App\Enums\WorkflowStage::ClassPending->value)
            ->whereNotIn('personnel_id', $enrolled)
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'personnel_id' => $q->personnel_id,
                'name' => $q->personnel?->full_name ?? 'Unknown',
                'employee_id' => $q->personnel?->employee_id,
                'department' => $q->personnel?->department,
            ])->values()->all();
    }

    public function getColumns(): array
    {
        $out = [];
        $byStatus = ClassEnrollment::with(['personnel', 'classSession.trainingClass', 'classSession.instructorUser'])
            ->whereNotIn('status', ['historical', 'cancelled'])
            ->get()->groupBy('status');
        foreach ($this->lanes as $key => $meta) {
            $label = \App\Models\WorkflowStatus::labelFor('class', $key, $meta['label']);
            $color = \App\Models\WorkflowStatus::colorFor('class', $key, $meta['color']);
            $cards = ($byStatus[$key] ?? collect())->map(fn ($e) => [
                'id' => $e->id,
                'personnel_id' => $e->personnel_id,
                'class_session_id' => $e->class_session_id,
                'class_id' => $e->classSession?->training_class_id,
                'name' => $e->personnel?->full_name ?? $e->employee_id ?? 'Unknown',
                'employee_id' => $e->personnel?->employee_id ?? $e->employee_id,
                'department' => $e->personnel?->department,
                'class' => $e->classSession?->trainingClass?->name,
                'date' => $e->classSession?->session_date?->gmp(),
                'instructor' => $e->classSession?->instructorUser?->name ?? $e->classSession?->instructor,
                'session_date' => $e->classSession?->session_date?->gmp(),
                'session_sort' => $e->classSession?->session_date?->format('Y-m-d'),
                'status_label' => $label,
                'status_color' => $color,
            ])->values()->all();
            $out[$key] = [
                'label' => $label,
                'color' => $color,
                'cards' => $cards,
            ];
        }

        // Manual / inferred class completions are themselves the QA-approved record (a manual entry implies
        // QA/Completed). Surface any that do NOT already have a matching completed enrollment in the QA
        // Approved lane so they show as completed alongside enrollment-driven cards.
        if (isset($out['completed'])) {
            $shownPersonnel = collect($out['completed']['cards'])->pluck('personnel_id')->filter()->all();
            $extra = \App\Models\ClassCompletion::with('personnel')
                ->when(! empty($shownPersonnel), fn ($q) => $q->whereNotIn('personnel_id', $shownPersonnel))
                ->whereNotNull('personnel_id')
                ->orderByDesc('completion_date')
                ->get()
                ->unique('personnel_id')
                ->map(fn ($c) => [
                    'id' => 'cc-' . $c->id,
                    'personnel_id' => $c->personnel_id,
                    'class_session_id' => null,
                    'class_id' => null,
                    'name' => $c->personnel?->full_name ?? $c->employee_id ?? 'Unknown',
                    'employee_id' => $c->personnel?->employee_id ?? $c->employee_id,
                    'department' => $c->personnel?->department,
                    'class' => $c->class_name,
                    'date' => $c->completion_date?->gmp(),
                    'instructor' => null,
                    'session_date' => $c->completion_date?->gmp(),
                    'session_sort' => $c->completion_date?->format('Y-m-d'),
                    'status_label' => \App\Models\WorkflowStatus::labelFor('class', 'completed', 'QA Approved'),
                    'status_color' => \App\Models\WorkflowStatus::colorFor('class', 'completed', '#2E7D5B'),
                    'is_completion_record' => true,
                    'source' => $c->source,
                ])->values()->all();
            $out['completed']['cards'] = array_merge($out['completed']['cards'], $extra);
        }

        return $out;
    }

    /** Drag an enrollment to a new status. 'completed' advances the person's workflow stage. */
    public ?array $detail = null;

    public function showDetail(?int $id): void
    {
        if (! $id) { $this->detail = null; return; }
        $e = ClassEnrollment::with(['personnel', 'classSession.trainingClass', 'classSession.instructorUser'])->find($id);
        if (! $e) { $this->detail = null; return; }
        $p = $e->personnel;
        $q = $p ? \App\Models\Qualification::currentFor($p->id) : null;
        $statusVal = $e->status instanceof \BackedEnum ? $e->status->value : $e->status;
        $qStatusVal = $q ? ($q->status instanceof \BackedEnum ? $q->status->value : $q->status) : null;
        $this->detail = [
            'id' => $e->id,
            'name' => $p?->full_name ?? $e->employee_id ?? 'Unknown',
            'employee_id' => $e->personnel?->employee_id ?? $e->employee_id,
            'email' => $p?->email,
            'department' => $p?->department,
            'job_title' => $p?->job_title,
            'class' => $e->classSession?->trainingClass?->name,
            'session_date' => $e->classSession?->session_date?->gmpL(),
            'session_time' => $e->classSession?->start_time ? \Illuminate\Support\Carbon::parse($e->classSession->start_time)->format('H:i') : null,
            'instructor' => $e->classSession?->instructorUser?->name ?? $e->classSession?->instructor,
            'location' => $e->classSession?->location,
            'status' => ucwords(str_replace('_', ' ', (string) $statusVal)),
            'status_color' => \App\Models\WorkflowStatus::colorFor('class', $statusVal, '#6A6A72'),
            'signed_up_at' => $e->signed_up_at?->gmp(),
            'attended_at' => $e->attended_at?->gmp(),
            'completed_at' => $e->completed_at?->gmp(),
            'qual_status' => $qStatusVal ? ucwords(str_replace('_', ' ', $qStatusVal)) : null,
            'qual_stage' => $q?->workflow_stage ? \App\Models\WorkflowStatus::labelFor('run', $q->workflow_stage->value, $q->workflow_stage->label()) : null,
            'qual_runs' => $q ? ((int) $q->runs_completed . ' / ' . (int) $q->runs_required) : null,
            'qual_due' => $q?->due_date?->gmp(),
            'class_on_file' => (bool) ($q?->class_on_file),
            // Edit affordances. Editing already-approved/completed info requires QA-or-higher; otherwise
            // class management is enough. Read-only Details for everyone else.
            'is_approved' => in_array($statusVal, ['completed', 'pending_qa'], true),
            'can_edit' => (function () use ($statusVal) {
                $u = \Illuminate\Support\Facades\Auth::user();
                if (! $u) return false;
                $isApproved = in_array($statusVal, ['completed', 'pending_qa'], true);
                if ($isApproved) {
                    return $u->hasCapability(\App\Enums\Capability::QaApprove) || $u->hasCapability(\App\Enums\Capability::QaReview);
                }
                return $u->hasCapability(\App\Enums\Capability::ManageClasses) || $u->hasCapability(\App\Enums\Capability::ManageAttendance);
            })(),
            // For approved/completed records, edits route back to the QA review (historic) page.
            'qa_url' => \App\Filament\Admin\Pages\QaQueue::getUrl(),
            // "Edit Record" opens the full (styled) qualification record page for this person's current cycle.
            'edit_url' => (function () use ($e) {
                if (! $e->personnel_id) return null;
                $q = \App\Models\Qualification::currentFor($e->personnel_id);
                return $q
                    ? \App\Filament\Admin\Resources\QualificationResource::getUrl('view', ['record' => $q->id])
                    : \App\Filament\Admin\Resources\PersonnelResource::getUrl('edit', ['record' => $e->personnel_id]);
            })(),
        ];
    }

    public function closeDetail(): void { $this->detail = null; }

    // Manual add-enrollment (analyst books a person into a class session)
    public bool $showAdd = false;
    public ?int $addSessionId = null;
    public ?int $addPersonnelId = null;

    public function openSessions(): array
    {
        return \App\Models\ClassSession::with('trainingClass')
            ->where('status', 'open')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')->get()
            ->mapWithKeys(fn ($s) => [$s->id => ($s->trainingClass?->name ?? 'Class') . ' · ' . $s->session_date?->gmp()])
            ->all();
    }

    public function bookablePersonnel(): array
    {
        return \App\Models\Personnel::where('is_active', true)
            ->orderBy('last_name')->orderBy('first_name')->get()
            ->mapWithKeys(fn ($p) => [$p->id => $p->full_name . ' (' . $p->employee_id . ')'])
            ->all();
    }

    public function addEnrollment(): void
    {
        if (! $this->addSessionId || ! $this->addPersonnelId) {
            \Filament\Notifications\Notification::make()->danger()->title('Pick a person and a session')->send();
            return;
        }
        $person = \App\Models\Personnel::find($this->addPersonnelId);
        ClassEnrollment::firstOrCreate(
            ['class_session_id' => $this->addSessionId, 'personnel_id' => $this->addPersonnelId],
            [
                'name' => $person?->full_name,
                'email' => $person?->email,
                'employee_id' => $person?->employee_id,
                'status' => 'signed_up',
                'signed_up_at' => now(),
            ]
        );
        $this->showAdd = false;
        $this->addPersonnelId = null;
        \Filament\Notifications\Notification::make()->success()->title('Enrollment added')->send();
    }

    // ----- Rebook a No-Show into another session -----
    public ?int $rebookEnrollmentId = null;
    public ?int $rebookPersonnelId = null;
    public ?int $rebookClassId = null;
    public string $rebookMode = 'next';        // 'next' | 'specific'
    public ?int $rebookSessionId = null;

    public function openRebook(int $enrollmentId): void
    {
        $e = ClassEnrollment::with('classSession')->find($enrollmentId);
        if (! $e) return;
        $this->rebookEnrollmentId = $e->id;
        $this->rebookPersonnelId = $e->personnel_id;
        $this->rebookClassId = $e->classSession?->training_class_id;
        $this->rebookMode = 'next';
        $this->rebookSessionId = null;
    }
    public function closeRebook(): void { $this->rebookEnrollmentId = null; $this->rebookSessionId = null; }

    /** Upcoming open sessions, preferring the same class first, for the specific-session picker. */
    public function rebookSessionOptions(): array
    {
        $q = \App\Models\ClassSession::with('trainingClass')
            ->where('status', 'open')
            ->whereDate('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')->get();
        return $q->mapWithKeys(fn ($s) => [$s->id =>
            ($s->trainingClass?->name ?? 'Class') . ' · ' . $s->session_date?->gmp()
            . ($s->training_class_id === $this->rebookClassId ? '' : ' (other class)')
        ])->all();
    }

    /** The soonest upcoming open session of the same class (fallback: any class). */
    protected function nextSessionForRebook(): ?\App\Models\ClassSession
    {
        $base = \App\Models\ClassSession::where('status', 'open')
            ->whereDate('session_date', '>=', now()->toDateString());
        $sameClass = (clone $base)->where('training_class_id', $this->rebookClassId)
            ->orderBy('session_date')->first();
        return $sameClass ?: (clone $base)->orderBy('session_date')->first();
    }

    public function confirmRebook(): void
    {
        $old = ClassEnrollment::find($this->rebookEnrollmentId);
        if (! $old) { $this->closeRebook(); return; }

        $session = $this->rebookMode === 'specific' && $this->rebookSessionId
            ? \App\Models\ClassSession::find($this->rebookSessionId)
            : $this->nextSessionForRebook();

        if (! $session) {
            \Filament\Notifications\Notification::make()->warning()->title('No Open Session Available')
                ->body('There is no upcoming open session to rebook into. Schedule one first, or pick a specific session.')->send();
            return;
        }

        $person = $old->personnel;
        // Create (or revive) the enrollment on the target session.
        $new = ClassEnrollment::firstOrCreate(
            ['class_session_id' => $session->id, 'personnel_id' => $old->personnel_id],
            [
                'name' => $person?->full_name ?? $old->name,
                'email' => $person?->email ?? $old->email,
                'employee_id' => $person?->employee_id ?? $old->employee_id,
                'status' => 'signed_up',
                'signed_up_at' => now(),
            ]
        );
        if ($new->status !== 'signed_up') {
            $new->markStatus('signed_up', \Illuminate\Support\Facades\Auth::id());
        }
        // Retire the old no-show enrollment so it leaves the board.
        $old->markStatus('historical', \Illuminate\Support\Facades\Auth::id());

        $this->closeRebook();
        \Filament\Notifications\Notification::make()->success()->title('Rebooked')
            ->body(($person?->full_name ?? 'Trainee') . ' signed up for ' . ($session->trainingClass?->name ?? 'the class') . ' on ' . $session->session_date?->gmp() . '.')->send();
    }

    public function moveCard(int $id, string $toStatus): void
    {
        // active lanes + the Archive (completed) lane
        if (! array_key_exists($toStatus, $this->lanes) && $toStatus !== 'historical') {
            return;
        }
        $e = ClassEnrollment::with('personnel', 'classSession.trainingClass')->find($id);
        if (! $e) {
            return;
        }
        $fromStatus = $e->status instanceof \BackedEnum ? $e->status->value : $e->status;

        // QCM REVIEW: dragging an Attended card to QCM Reviewed opens a forced QCM sign-off modal.
        // The drag does not set the status directly; the sign-off (with e-signature) does.
        if ($toStatus === 'qcm_reviewed') {
            if ($fromStatus !== 'attended') {
                \Filament\Notifications\Notification::make()->warning()->title('Only From Attended')
                    ->body('A class is QCM reviewed after the trainer has submitted attendance (Attended).')->send();
                return;
            }
            $this->openQcmSign($id);
            return;
        }

        // Attendance, Pending QA and completion are GMP-gated: recorded by submitting the attendance
        // sheet (trainer e-signature -> Attended), the QCM sign-off (-> Pending QA), and QA approval
        // (-> QA Approved), never by dragging a card.
        if (in_array($toStatus, ['attended', 'pending_qa', 'completed'], true)) {
            \Filament\Notifications\Notification::make()->warning()
                ->title('Set Through Attendance, QCM and QA')
                ->body('These stages are recorded by submitting the attendance sheet, the QCM sign-off, and QA approval, not by dragging a card.')
                ->send();
            return;
        }
        // centralized: stamps who/when and advances the qualification consistently
        $e->markStatus($toStatus, \Illuminate\Support\Facades\Auth::id());
    }

    // ---- QCM sign-off (forced modal when dragging Attended -> QCM Reviewed) ----
    public ?int $qcmSignEid = null;
    public string $qcmSignPassword = '';
    public function openQcmSign(int $enrollmentId): void
    {
        $e = ClassEnrollment::find($enrollmentId);
        if (! $e) return;
        $this->qcmSignEid = $enrollmentId;
        $this->qcmSignPassword = '';
    }
    public function closeQcmSign(): void { $this->qcmSignEid = null; $this->qcmSignPassword = ''; }

    public function qcmSignName(): ?string
    {
        return $this->qcmSignEid ? (ClassEnrollment::with('personnel')->find($this->qcmSignEid)?->personnel?->full_name ?? 'Trainee') : null;
    }

    public function confirmQcmSign(): void
    {
        $e = ClassEnrollment::with('personnel', 'classSession.trainingClass')->find($this->qcmSignEid);
        if (! $e) { $this->closeQcmSign(); return; }
        $statusVal = $e->status instanceof \BackedEnum ? $e->status->value : $e->status;
        if ($statusVal !== 'attended') {
            \Filament\Notifications\Notification::make()->warning()->title('Not In Attended')->send();
            $this->closeQcmSign();
            return;
        }
        if ((bool) \App\Models\Setting::get('esig_required', true)
            && ! \Illuminate\Support\Facades\Hash::check($this->qcmSignPassword, \Illuminate\Support\Facades\Auth::user()->password)) {
            \Filament\Notifications\Notification::make()->danger()->title('Signature Failed')
                ->body('Password did not match. The class was not QCM reviewed.')->send();
            return;
        }
        // Record the QCM sign-off e-signature against the enrollment, then advance to Pending QA.
        \App\Models\ElectronicSignature::create([
            'signable_type' => ClassEnrollment::class,
            'signable_id' => $e->id,
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
            'signer_name' => \Illuminate\Support\Facades\Auth::user()?->name,
            'meaning' => 'QCM Reviewed - Class Attendance',
            'signed_at' => now(),
        ]);
        $e->markStatus('pending_qa', \Illuminate\Support\Facades\Auth::id());
        $this->closeQcmSign();
        \Filament\Notifications\Notification::make()->success()->title('QCM Reviewed')
            ->body(($e->personnel?->full_name ?? 'Trainee') . ' is now Pending QA.')->send();
    }
}
