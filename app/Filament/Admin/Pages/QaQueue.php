<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Enums\WorkflowStage;
use App\Models\Qualification;
use App\Models\ElectronicSignature;
use App\Models\Setting;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class QaQueue extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::QaReview) || $u->hasCapability(Capability::QaApprove)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'QA Review';
    protected static ?string $slug = 'qa-review';
    protected static string|\UnitEnum|null $navigationGroup = 'Review';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'QA Review';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.qa-queue';

    /** runs | classroom */
    public string $tab = 'runs';
    public function setTab(string $t): void { $this->tab = in_array($t, ['runs', 'classroom'], true) ? $t : 'runs'; }

    // ---------------- Classroom approval (the Classroom tab) ----------------

    /** Submitted class sessions with attendees awaiting QA classroom approval. */
    /** Last few QA-signed classroom sessions, so a sign-off can be reopened for correction. */
    public function recentlySignedClassrooms(): array
    {
        return \App\Models\ClassSession::with('trainingClass')
            ->whereNotNull('qa_signed_at')
            ->latest('qa_signed_at')->limit(8)->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => ($s->session_uid ? $s->session_uid . ' · ' : '') . ($s->trainingClass?->name ?? 'Class'),
                'signed_at' => $s->qa_signed_at?->gmpDt(),
                'veeva' => $s->veeva_doc_number,
            ])->all();
    }

    public function getClassroomQueue(): array
    {
        return \App\Models\ClassSession::with(['trainingClass', 'enrollments.personnel', 'submittedBy', 'instructorUser'])
            ->whereNotNull('attendance_submitted_at')
            ->whereHas('enrollments', fn ($q) => $q->where('status', 'pending_qa'))
            ->orderBy('attendance_submitted_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => ($s->session_uid ? $s->session_uid . ' · ' : '') . ($s->trainingClass?->name ?? 'Class') . ' · ' . $s->session_date?->gmpL(),
                'submitted_at' => $s->attendance_submitted_at?->gmpDt(),
                'submitted_by' => $s->submittedBy?->name,
                'trainer' => $s->instructorUser?->name ?? $s->instructor,
                'form_url' => route('print.class-attendance', [$s->id, 'FORM-AST-36513-' . ($s->session_uid ?: 'Class') . '.pdf']),
                'rows' => $s->enrollments->where('status', 'pending_qa')->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => $e->personnel?->full_name ?? $e->name ?? 'Unknown',
                    'employee_id' => $e->employee_id,
                ])->values()->all(),
            ])->values()->all();
    }

    public function approveClassroom(int $enrollmentId): void
    {
        if (! $this->canApprove()) { Notification::make()->danger()->title('Not authorized to approve')->send(); return; }
        $e = \App\Models\ClassEnrollment::with('personnel', 'classSession')->find($enrollmentId);
        if (! $e || $e->status !== 'pending_qa') return;
        $e->markStatus('completed', Auth::id());
        Notification::make()->success()->title('Classroom training approved')
            ->body(($e->personnel?->full_name ?? 'Trainee') . ' is now Class Complete and eligible for runs.')->send();
    }

    public function approveClassroomSession(int $sessionId): void
    {
        if (! $this->canApprove()) { Notification::make()->danger()->title('Not authorized to approve')->send(); return; }
        $s = \App\Models\ClassSession::with('enrollments.personnel')->find($sessionId);
        if (! $s) return;
        $n = 0;
        foreach ($s->enrollments->where('status', 'pending_qa') as $e) { $e->markStatus('completed', Auth::id()); $n++; }
        Notification::make()->success()->title('Session approved')->body($n . ' trainee(s) are now Class Complete.')->send();
    }

    // ===================== CLASSROOM SIGN-OFF (custom modal) =====================
    public ?int $clsSid = null;          // class session under sign-off
    public string $clsMode = 'signoff';  // signoff | reopen
    public string $clsVeeva = '';
    public string $clsVeevaUrl = '';
    public string $clsLms = '';
    public string $clsPassword = '';

    public function openClassSignoff(int $sessionId): void
    {
        $s = \App\Models\ClassSession::find($sessionId);
        if (! $s) return;
        $this->clsSid = $sessionId; $this->clsMode = 'signoff';
        $this->clsVeeva = $s->veeva_doc_number ?? ''; $this->clsVeevaUrl = $s->veeva_url ?? '';
        $this->clsLms = $s->lms_number ?? ''; $this->clsPassword = '';
    }
    public function openClassReopen(int $sessionId): void
    {
        $this->clsSid = $sessionId; $this->clsMode = 'reopen'; $this->clsPassword = '';
    }
    public function closeClassSignoff(): void { $this->clsSid = null; }

    public function clsData(): ?array
    {
        if (! $this->clsSid) return null;
        $s = \App\Models\ClassSession::with('trainingClass', 'enrollments.personnel')->find($this->clsSid);
        if (! $s) return null;
        $pending = $s->enrollments->where('status', 'pending_qa');
        $u = Auth::user();
        $myPid = $u ? (\App\Models\Personnel::where('user_id', $u->id)->orWhere('email', $u->email)->value('id')) : null;
        $signerIsTrainee = $myPid && $s->enrollments->whereNotIn('status', ['cancelled'])->contains('personnel_id', $myPid);
        return [
            'title' => ($s->session_uid ? $s->session_uid . ' · ' : '') . ($s->trainingClass?->name ?? 'Class'),
            'count' => $pending->count(),
            'names' => $pending->map(fn ($e) => $e->personnel?->full_name ?? $e->name)->implode(', '),
            'signer_is_trainee' => (bool) $signerIsTrainee,
            'esig' => (bool) Setting::get('esig_required', true),
        ];
    }

    public function finalizeClassSignoff(): void
    {
        $s = \App\Models\ClassSession::with('enrollments')->find($this->clsSid);
        if (! $s) return;
        $d = $this->clsData();
        if (! $this->canApprove()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        if ($d['signer_is_trainee']) {
            Notification::make()->danger()->title('Two-Person Rule')->body('You are a trainee on this session and cannot sign it off.')->send();
            return;
        }
        if ($d['esig'] && ! Hash::check($this->clsPassword, Auth::user()->password)) {
            Notification::make()->danger()->title('Signature Failed')->body('Password did not match.')->send();
            return;
        }
        $s->update([
            'veeva_doc_number' => $this->clsVeeva ?: null,
            'veeva_url' => $this->clsVeevaUrl ?: null,
            'lms_number' => $this->clsLms ?: null,
            'qa_signed_at' => now(), 'qa_signed_by' => Auth::id(),
        ]);
        $n = 0;
        foreach ($s->enrollments->where('status', 'pending_qa') as $e) { $e->markStatus('completed', Auth::id()); $n++; }
        ElectronicSignature::create([
            'signable_type' => \App\Models\ClassSession::class, 'signable_id' => $s->id,
            'user_id' => Auth::id(), 'signer_name' => Auth::user()->name, 'meaning' => 'Classroom QA Sign-off',
            'statement' => 'Classroom training approved for ' . $n . ' trainee(s). Veeva ' . $this->clsVeeva . ($this->clsLms ? ', LMS ' . $this->clsLms : '') . '.',
            'signed_at' => now(),
        ]);
        $this->clsSid = null;
        Notification::make()->success()->title('Classroom Signed Off')->body($n . ' trainee(s) are now Class Complete.')->send();
    }

    public function finalizeClassReopen(): void
    {
        $s = \App\Models\ClassSession::find($this->clsSid);
        if (! $s) return;
        if (! $this->canApprove()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        if ((bool) Setting::get('esig_required', true) && ! Hash::check($this->clsPassword, Auth::user()->password)) {
            Notification::make()->danger()->title('Signature Failed')->body('Password did not match.')->send();
            return;
        }
        $n = 0;
        foreach (\App\Models\ClassEnrollment::where('class_session_id', $s->id)->where('status', 'completed')->get() as $e) {
            $e->markStatus('pending_qa', Auth::id()); $n++;
        }
        $s->update(['qa_signed_at' => null, 'qa_signed_by' => null]);
        ElectronicSignature::create([
            'signable_type' => \App\Models\ClassSession::class, 'signable_id' => $s->id,
            'user_id' => Auth::id(), 'signer_name' => Auth::user()->name, 'meaning' => 'Classroom Sign-off Reopened',
            'statement' => 'Reopened classroom sign-off for correction; ' . $n . ' trainee(s) returned to QA review.', 'signed_at' => now(),
        ]);
        $this->clsSid = null;
        Notification::make()->success()->title('Reopened')->body($n . ' trainee(s) returned to QA review.')->send();
    }

    public function returnClassroom(int $enrollmentId): void
    {
        $e = \App\Models\ClassEnrollment::with('classSession')->find($enrollmentId);
        if (! $e || $e->status !== 'pending_qa') return;
        $e->markStatus('attended', Auth::id());
        Notification::make()->warning()->title('Returned to trainer')->body('Attendee moved back to Attended for correction.')->send();
    }

    // ---------------- Run sign-off (the Runs tab) ----------------

    public function getQueue()
    {
        return Qualification::with('personnel', 'qaOwner')
            ->whereIn('workflow_stage', [WorkflowStage::QaReview->value, WorkflowStage::ResultsReleased->value])
            ->whereNull('superseded_at')
            ->orderBy('stage_changed_at')
            ->get();
    }

    public function getFailed()
    {
        return Qualification::with('personnel')
            ->where('workflow_stage', WorkflowStage::Failed->value)
            ->whereNull('superseded_at')
            ->get();
    }

    public function canApprove(): bool
    {
        return (bool) Auth::user()?->hasCapability(Capability::QaApprove);
    }

    /** QA sign-off with two-component electronic signature (Part 11). */

    public function signOff(int $id): void
    {
        if (! $this->canApprove()) {
            Notification::make()->danger()->title('Not authorized')->body('QA approver role required to sign off.')->send();
            return;
        }
        $q = Qualification::with('personnel')->find($id);
        if (! $q) return;
        $q->workflow_stage = WorkflowStage::QaSignoff;
        $q->stage_changed_at = now();
        $q->status = 'qualified';
        if (! $q->qualified_date) $q->qualified_date = now();
        if (! $q->due_date) $q->due_date = now()->addMonths((int) \App\Models\Setting::get('cycle_months', 12));
        $q->save();
        \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::Qualified, ['personnel' => $q->personnel, 'qualification' => $q]);
        Notification::make()->success()->title('Signed off')
            ->body(($q->personnel?->full_name ?? 'Qualification') . ' is now Qualified.')->send();
    }

    /** QA requalification recommendation on the failed/requal path:
     *  full initial (3 runs) or annual requal (1 run). Resets the qualification accordingly. */
    // ===================== QA SIGN-OFF WIZARD (custom modal) =====================
    public ?int $wizQid = null;          // qualification under review
    public string $wizStep = 'review';   // review | pass | fail
    public string $wizMeaning = 'Approved';
    public string $wizPassword = '';
    public string $wizPath = 'requal_three';
    public bool $wizRetrain = false;
    public string $wizNote = '';

    public function openSignoff(int $qid): void
    {
        $this->wizQid = $qid;
        $this->wizStep = 'review';
        $this->wizMeaning = 'Approved';
        $this->wizPassword = '';
        $this->wizPath = 'requal_three';
        $this->wizRetrain = false;
        $this->wizNote = '';
    }
    public function closeSignoff(): void { $this->wizQid = null; }
    public function openDetermination(int $qid): void { $this->openSignoff($qid); $this->wizStep = 'fail'; }
    public function wizSetStep(string $s): void { if (in_array($s, ['review','pass','fail'], true)) $this->wizStep = $s; }

    /** Details payload for the wizard's review step. */
    public function wizData(): ?array
    {
        if (! $this->wizQid) return null;
        $q = Qualification::with('personnel')->find($this->wizQid);
        if (! $q) return null;
        $run = \App\Models\QualificationRun::where('personnel_id', $q->personnel_id)->latest('run_date')->latest('id')->first();
        $nc = $run ? \App\Models\NonConformance::where('qualification_run_id', $run->id)->first() : null;
        return [
            'name' => $q->personnel?->full_name ?? 'Unknown',
            'employee_id' => $q->personnel?->employee_id,
            'progress' => $q->runs_completed . ' / ' . $q->runs_required,
            'type' => $q->sessionLabel(),
            'run_uid' => $run?->run_uid,
            'veeva' => $run?->veeva_doc_number,
            'veeva_url' => $run?->veeva_url,
            'lms' => $q->lms_number,
            'nc' => $nc?->nc_number,
            'is_subject' => $this->signerIsSubject($q),
            'esig' => (bool) Setting::get('esig_required', true),
        ];
    }

    /** Two-person rule: signer cannot be the person being qualified. */
    protected function signerIsSubject(Qualification $q): bool
    {
        $u = Auth::user();
        if (! $u || ! $q->personnel_id) return false;
        $p = \App\Models\Personnel::find($q->personnel_id);
        if (! $p) return false;
        return ($p->user_id && $p->user_id === $u->id) || ($p->email && $u->email && strcasecmp($p->email, $u->email) === 0);
    }

    protected function wizGuard(Qualification $q): bool
    {
        if (! $this->canApprove()) { Notification::make()->danger()->title('Not Authorized')->send(); return false; }
        if ($this->signerIsSubject($q)) {
            Notification::make()->danger()->title('Two-Person Rule')->body('You cannot sign off your own qualification.')->send();
            return false;
        }
        if ((bool) Setting::get('esig_required', true) && ! Hash::check($this->wizPassword, Auth::user()->password)) {
            Notification::make()->danger()->title('Signature Failed')->body('Password did not match.')->send();
            return false;
        }
        return true;
    }

    /** PASS: final QA sign-off -> Qualified. */
    public function finalizeSignoff(): void
    {
        $q = Qualification::with('personnel')->find($this->wizQid);
        if (! $q || ! $this->wizGuard($q)) return;
        ElectronicSignature::create([
            'signable_type' => Qualification::class, 'signable_id' => $q->id,
            'user_id' => Auth::id(), 'signer_name' => Auth::user()->name,
            'meaning' => $this->wizMeaning ?: 'Approved',
            'statement' => 'QA sign-off: qualification approved as complete.', 'signed_at' => now(),
        ]);
        $q->workflow_stage = WorkflowStage::QaSignoff;
        $q->stage_changed_at = now();
        $q->status = 'qualified';
        if (! $q->qualified_date) $q->qualified_date = now();
        if (! $q->due_date) $q->due_date = now()->addMonths((int) Setting::get('cycle_months', 12));
        $q->save();
        \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::Qualified, ['personnel' => $q->personnel, 'qualification' => $q]);
        $this->wizQid = null;
        Notification::make()->success()->title('Signed Off')->body(($q->personnel?->full_name ?? 'Qualification') . ' is now Qualified.')->send();
    }

    /** FAIL: QA determination -> requalification path. */
    public function finalizeFail(): void
    {
        $parent = Qualification::with('personnel')->find($this->wizQid);
        if (! $parent || ! $this->wizGuard($parent)) return;
        $three = $this->wizPath === 'requal_three';
        $retrain = $this->wizRetrain;

        // The failed run belongs to the parent cycle; record QA's determination on it.
        $failedRun = \App\Models\QualificationRun::where('personnel_id', $parent->personnel_id)
            ->whereNull('deleted_at')->latest('run_date')->latest('id')->first();
        if ($failedRun) {
            $failedRun->update([
                'qa_determination' => $three ? 'fail_requalify' : ($retrain ? 'fail_retrain' : 'fail_redo'),
                'qa_determined_at' => now(), 'is_complete' => true,
                'qa_signed_by' => Auth::id(), 'qa_notes' => $this->wizNote ?: null,
            ]);
        }

        // E-signature of the determination, recorded against the parent (the cycle QA reviewed).
        ElectronicSignature::create([
            'signable_type' => Qualification::class, 'signable_id' => $parent->id,
            'user_id' => Auth::id(), 'signer_name' => Auth::user()->name, 'meaning' => 'QA Determination',
            'statement' => 'QA determination on failed qualification: opened ' . ($three ? 'a full 3-run requalification (QA Initial Requal)' : 'an annual requalification (1 additional run, REQUAL2)') . ($retrain ? ' with mandatory gowning class retraining' : '') . '. A new requalification session was created, linked to the failed cycle, and access remains restricted until it is successfully completed. ' . $this->wizNote,
            'signed_at' => now(),
        ]);

        // Open the nonconformance against the parent / failed run (once).
        $existingNc = $failedRun ? \App\Models\NonConformance::where('qualification_run_id', $failedRun->id)->exists() : false;
        if (! $existingNc) {
            \App\Models\NonConformance::create([
                'qualification_id' => $parent->id, 'qualification_run_id' => $failedRun?->id, 'personnel_id' => $parent->personnel_id,
                'nc_type' => 'failed_run', 'status' => 'open', 'observed_date' => now()->toDateString(), 'created_by' => Auth::id(),
                'summary' => 'Opened from QA determination (' . ($three ? '3-run' : '1-run') . ' requalification' . ($retrain ? ', retraining required' : '') . '). ' . $this->wizNote,
            ]);
            \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::NcOpened, ['personnel' => $parent->personnel]);
        }

        // Spawn the requalification as a NEW child session, linked to the failed parent. From here
        // the child is the active cycle (currentFor resolves to it); the parent stays as read-only
        // history so the rerun can always be read apart from the original.
        $child = new Qualification();
        $child->personnel_id = $parent->personnel_id;
        $child->parent_qualification_id = $parent->id;
        $child->cycle_number = ((int) $parent->cycle_number) + 1;
        $child->type = $three ? \App\Enums\QualificationType::Initial : \App\Enums\QualificationType::Annual;
        $child->runs_required = $three ? (int) Setting::get('initial_runs_required', 3) : (int) Setting::get('annual_runs_required', 1);
        $child->runs_completed = 0;
        $child->status = \App\Enums\QualificationStatus::Pending;
        $child->qualified_date = null;
        $child->due_date = null;
        $child->cycle_started_at = now()->toDateString();
        $child->qa_recommendation = $this->wizPath;            // drives the QA Initial Requal / QA Requal 2 label
        $child->qa_recommendation_note = $this->wizNote ?: null;
        $child->qa_owner_id = $parent->qa_owner_id;
        $child->workflow_stage = $retrain ? WorkflowStage::ClassPending : WorkflowStage::ClassComplete;
        $child->class_on_file = $retrain ? false : (bool) $parent->class_on_file;   // retraining clears the gate
        $child->class_on_file_date = $retrain ? null : $parent->class_on_file_date;
        $child->stage_changed_at = now();
        $child->pending_parent_run_id = $failedRun?->id;       // child's first run descends from the failed run
        $child->save();

        // Close the parent cycle as history.
        $parent->superseded_at = now();
        $parent->save();

        // Auto-book the CHILD's next run (unless they owe a retraining class first).
        $bookedMsg = '';
        if (! $retrain && app(\App\Services\AutoScheduler::class)->bookNext($child->fresh())) {
            $bookedMsg = ' Auto-booked for the next available run day.';
        }

        $this->wizQid = null;
        Notification::make()->success()->title('Determination Recorded')
            ->body(($parent->personnel?->full_name ?? 'Operator') . ': new requalification session opened (' . ($three ? 'QA Initial Requal - 3 runs' : 'QA Requal 2 - 1 additional run') . ($retrain ? ' + class retraining required first' : '') . '), linked to the failed cycle. Access restricted until complete.' . $bookedMsg)->send();
    }


    /** Staff who can be assigned ownership of approvals (QA reviewers/approvers). */
    public function qaReviewers(): array
    {
        return \App\Models\User::where('is_active', true)->get()
            ->filter(fn ($u) => $u->hasCapability(Capability::QaReview) || $u->hasCapability(Capability::QaApprove))
            ->pluck('name', 'id')->all();
    }

    public function assignOwner(int $id, $userId): void
    {
        $q = Qualification::find($id);
        if (! $q) return;
        $q->qa_owner_id = $userId ?: null;
        $q->save();
        Notification::make()->success()->title('Owner assigned')->send();
    }

}
