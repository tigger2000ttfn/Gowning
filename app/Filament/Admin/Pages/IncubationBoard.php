<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Enums\WorkflowStage;
use App\Models\Qualification;
use App\Models\QualificationRun;
use App\Models\Reservation;
use App\Models\RunSample;
use App\Models\Setting;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Carbon\CarbonImmutable;

class IncubationBoard extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ManageScheduling) || $u->hasCapability(Capability::RecordRuns) || $u->hasCapability(Capability::QaReview)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationLabel = 'Lab Review';
    protected static ?string $slug = 'lab-review';
    protected static string|\UnitEnum|null $navigationGroup = 'Review';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Lab Review';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.incubation-board';

    /** incubating | evaluation | history */
    public string $tab = 'incubating';
    public function setTab(string $t): void { $this->tab = in_array($t, ['incubating', 'evaluation', 'history'], true) ? $t : 'incubating'; }

    // Undo a released plate-read result for re-entry (reason required, reverts stage, logged).
    public ?int $undoRunId = null;
    public string $undoReason = '';
    public string $undoComment = '';
    public string $undoPassword = '';
    public function openResultUndo(int $runId): void { $this->undoRunId = $runId; $this->undoReason = ''; $this->undoComment = ''; $this->undoPassword = ''; }
    public function closeResultUndo(): void { $this->undoRunId = null; }

    public function undoReasons(): array
    {
        return [
            'misread' => 'Plate Misread',
            'data_error' => 'Data Entry Error',
            'wrong_worklist' => 'Wrong Worklist',
            'recount' => 'Recount Required',
            'other' => 'Other (See Comment)',
        ];
    }

    /** Revert a released result back to Awaiting Results so the QCM analyst can re-enter it. */
    public function finalizeResultUndo(): void
    {
        if (! $this->canEvaluate()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $run = QualificationRun::find($this->undoRunId);
        if (! $run) { $this->undoRunId = null; return; }
        $reason = trim($this->undoReason);
        $comment = trim($this->undoComment);
        if ($reason === '' || $comment === '') {
            Notification::make()->warning()->title('Reason And Comment Required')->body('Provide a reason and comment to undo this result.')->send();
            return;
        }
        if ((bool) Setting::get('esig_required', true) && ! \Illuminate\Support\Facades\Hash::check($this->undoPassword, Auth::user()->password)) {
            Notification::make()->danger()->title('Signature Failed')->body('Password did not match.')->send();
            return;
        }
        $q = $run->qualification_id ? Qualification::find($run->qualification_id) : Qualification::currentFor($run->personnel_id);

        \App\Models\ElectronicSignature::create([
            'signable_type' => QualificationRun::class, 'signable_id' => $run->id,
            'user_id' => Auth::id(), 'signer_name' => Auth::user()->name, 'meaning' => 'Lab Result Reverted',
            'statement' => 'Reverted a released plate-read result for re-entry. Reason: ' . $reason . '. Comment: ' . $comment,
            'signed_at' => now(),
        ]);
        if ($q) {
            $q->comments()->create([
                'user_id' => Auth::id(), 'author_name' => Auth::user()?->name,
                'body' => 'Lab result reverted for re-entry. Reason: ' . $reason . '. ' . $comment,
            ]);
        }
        // Clear the result + release stamps so it returns to evaluation as not-yet-entered.
        $run->result = \App\Enums\RunResult::Pending->value;
        $run->results_released_at = null;
        $run->save();
        if ($q) {
            $q->workflow_stage = WorkflowStage::AwaitingResults;
            $q->stage_changed_at = now();
            $q->save();
        }
        $this->undoRunId = null;
        Notification::make()->success()->title('Result Reverted')->body('Returned to evaluation for re-entry.')->send();
    }

    public function mount(): void
    {
        // opportunistic time-based advance whenever the page is viewed; use the same
        // multi-run-aware advancer as the daily cron so page-load and cron agree.
        app(\App\Services\RunCycleAdvancer::class)->sweep();

        // Deep-link: ?tab=evaluation lands on Result Evaluation; ?evaluate=<qualId> also opens that
        // record's results modal so a drag/link from the board lands on the right record, not Incubating.
        $tab = request()->query('tab');
        if (in_array($tab, ['incubating', 'evaluation', 'history'], true)) {
            $this->tab = $tab;
        }
        $evalId = (int) request()->query('evaluate', 0);
        if ($evalId > 0) {
            $this->tab = 'evaluation';
            if ($this->canEvaluate()) {
                $this->openEnterResults($evalId);
            }
        }
    }

    public function incubationDays(): int { return (int) Setting::get('incubation_days', 8); }

    /** Runs still in incubation (timer running), the lab holding area. */
    public function getIncubating()
    {
        $days = $this->incubationDays();
        return Qualification::with('personnel')
            ->where('workflow_stage', WorkflowStage::Incubating->value)
            ->whereNull('superseded_at')
            ->get()
            ->map(function ($q) use ($days) {
                $run = QualificationRun::where('personnel_id', $q->personnel_id)->latest('run_date')->latest('id')->first();
                $started = $run?->incubation_started_at;
                $ready = $started ? CarbonImmutable::parse($started)->addDays($days) : null;
                return (object) [
                    'id' => $q->id,
                    'name' => $q->personnel?->full_name ?? 'Unknown',
                    'employee_id' => $q->personnel?->employee_id,
                    'started' => $started,
                    'ready' => $ready,
                    'remaining' => $ready ? now()->diffInDays($ready, false) : null,
                    'worklist' => $run?->lims_worklist_id,
                ];
            })
            ->sortBy('ready')->values();
    }

    /** Runs whose incubation has elapsed: ready for QCM pass/fail evaluation. */
    public function getEvaluation()
    {
        return Qualification::with('personnel')
            ->whereIn('workflow_stage', [WorkflowStage::AwaitingResults->value, WorkflowStage::ResultsReleased->value])
            ->whereNull('superseded_at')
            ->get()
            ->map(function ($q) {
                $run = QualificationRun::where('personnel_id', $q->personnel_id)->latest('run_date')->latest('id')->first();
                $signoff = $q->workflow_stage === WorkflowStage::ResultsReleased;
                return (object) [
                    'id' => $q->id,
                    'name' => $q->personnel?->full_name ?? 'Unknown',
                    'employee_id' => $q->personnel?->employee_id,
                    'worklist' => $run?->lims_worklist_id,
                    'run_uid' => $run?->run_uid,
                    'veeva' => $run?->veeva_doc_number,
                    'performed' => $run?->run_date,
                    'cycle' => $q->type?->label(),
                    'progress' => (int) $q->runs_completed . ' / ' . (int) $q->runs_required,
                    'step' => $signoff ? 'signoff' : 'result',
                    'form_url' => route('print.approval', [$q->id, 'FORM-AST-36749-' . ($run?->run_uid ?: 'QRUN') . '.pdf']),
                ];
            })
            ->sortBy('performed')->values();
    }

    /** Two-person rule: the signer cannot be the person being qualified. */
    protected function signerIsSubject(Qualification $q): bool
    {
        $u = Auth::user();
        if (! $u || ! $q->personnel_id) return false;
        $p = \App\Models\Personnel::where('id', $q->personnel_id)->first();
        if (! $p) return false;
        return ($p->user_id && $p->user_id === $u->id) || ($p->email && $u->email && strcasecmp($p->email, $u->email) === 0);
    }

    /** Recently evaluated runs (results entered), for the History tab. */
    public function getHistory()
    {
        // Historical = the QCM has signed off the result evaluation (which sends it to QA). A worklist
        // showing Pass with results auto-entered is NOT historical until that sign-off; it stays in the
        // Result Evaluation tab awaiting the QCM signature.
        return QualificationRun::with('personnel')
            ->whereNotNull('qcm_signed_at')
            ->latest('qcm_signed_at')->latest('id')->limit(60)->get()
            ->map(fn ($r) => (object) [
                'id' => $r->id,
                'qualification_id' => $r->qualification_id,
                'name' => $r->personnel?->full_name ?? 'Unknown',
                'employee_id' => $r->personnel?->employee_id,
                'worklist' => $r->lims_worklist_id,
                'result' => $r->result instanceof \BackedEnum ? ucfirst($r->result->value) : ucfirst((string) $r->result),
                'entered_at' => $r->results_entered_at,
                'run_date' => $r->run_date,
                // QCM sign-off locks the result; once signed to QA it should not be undone here.
                'locked' => (bool) $r->qcm_signed_at,
            ]);
    }

    public function canEvaluate(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::RecordRuns) || $u->hasCapability(Capability::ManageScheduling)));
    }

    /** QCM enters LIMS results (worklist + overall pass/fail + optional NC note), routes to QA. */

    // ---- Custom Enter-Results modal (reliable in a table loop; worklist editable here) ----
    public ?int $erQid = null;
    public array $er = ['worklist' => '', 'overall' => '', 'trackwise_id' => '', 'nc_note' => '', 'veeva' => ''];

    // Whether LIMS has already returned a result for the linked worklist (so manual pass/fail is optional).
    public bool $erHasLimsResult = false;
    public ?string $erLimsResult = null;   // 'pass' | 'fail' inferred from LIMS, when present

    public function openEnterResults(int $qid): void
    {
        if (! $this->canEvaluate()) { Notification::make()->danger()->title('Not Authorized')->body('QC Micro role required to enter results.')->send(); return; }
        $q = Qualification::find($qid);
        $run = $q ? QualificationRun::where('personnel_id', $q->personnel_id)->latest('run_date')->latest('id')->first() : null;
        $this->erQid = $qid;

        // Self-aware: did LIMS return a result on this worklist? Only TRUE when a worklist is actually
        // attached AND there is a real result - either the run carries a pass/fail (not pending), or the
        // worklist is final/QCM-ready in LIMS. A pending run with no worklist is NOT a returned result.
        $this->erHasLimsResult = false;
        $this->erLimsResult = null;
        $wl = $run?->lims_worklist_id ? \App\Models\LimsWorklist::findByWorklist($run->lims_worklist_id) : null;
        $runResult = $run ? ($run->result instanceof \BackedEnum ? $run->result->value : $run->result) : null;
        if ($run && $run->lims_worklist_id) {
            if (in_array($runResult, ['pass', 'fail'], true)) {
                // The run already carries a real result (from a prior LIMS sync / QCM-ready release).
                $this->erHasLimsResult = true;
                $this->erLimsResult = $runResult;
            } elseif ($wl && ($wl->worklist_all_final || $wl->isQcmReady())) {
                // LIMS marked the worklist final/QCM-ready: treat as a result present (pass unless excursion).
                $this->erHasLimsResult = true;
                $this->erLimsResult = $wl->isQcmReady() ? 'pass' : null;
            }
        }

        $this->er = [
            'worklist' => $run?->lims_worklist_id ? preg_replace('/^EM-/i', '', (string) $run->lims_worklist_id) : '',
            'overall' => $this->erHasLimsResult && $this->erLimsResult ? $this->erLimsResult : '',
            'trackwise_id' => $run?->lims_nc_number ? preg_replace('/^NC-/i', '', (string) $run->lims_nc_number) : '',
            'nc_note' => '',
            'veeva' => $run?->veeva_doc_number ? preg_replace('/^RPT-AST-/i', '', (string) $run->veeva_doc_number) : '',
        ];
    }
    public function closeEnterResults(): void { $this->erQid = null; }

    /** Worklist suggestions for autocomplete (numbers only; EM- shown in the UI). */
    public function erWorklistSuggestions(): array
    {
        $term = preg_replace('/^EM[-\s]*/i', '', trim((string) ($this->er['worklist'] ?? '')));
        $q = \App\Models\LimsWorklist::query()->orderByDesc('id')->limit(15);
        if ($term !== '') $q->where('worklist', 'ilike', '%' . $term . '%');
        return $q->pluck('worklist')->map(fn ($w) => preg_replace('/^EM-/i', '', (string) $w))->filter()->unique()->values()->all();
    }

    public function erPersonName(): ?string
    {
        return $this->erQid ? (Qualification::with('personnel')->find($this->erQid)?->personnel?->full_name ?? 'Operator') : null;
    }

    public function saveEnterResults(): void
    {
        if (! $this->canEvaluate()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $q = Qualification::with('personnel')->find($this->erQid);
        if (! $q) { $this->erQid = null; return; }
        $overall = $this->er['overall'] ?? '';

        // The worklist can be recorded WITHOUT a result yet (the pass/fail comes later, separately). If no
        // result is chosen, we just save/link the worklist and leave the run pending - no stage change.
        $wlInput = trim((string) ($this->er['worklist'] ?? ''));
        if (! in_array($overall, ['pass', 'fail'], true)) {
            if ($wlInput === '') {
                Notification::make()->danger()->title('Nothing To Save')->body('Enter the worklist (and optionally a Pass/Fail result).')->send();
                return;
            }
            $wl = strtoupper($wlInput);
            if (! str_starts_with($wl, 'EM-')) { $wl = 'EM-' . ltrim(preg_replace('/^EM[-\s]*/i', '', $wl), '-'); }
            $run = QualificationRun::where('personnel_id', $q->personnel_id)->latest('run_date')->latest('id')->first();
            if ($run) {
                $run->lims_worklist_id = $wl;
                if (($run->result instanceof \BackedEnum ? $run->result->value : $run->result) === null) {
                    $run->result = \App\Enums\RunResult::Pending;
                }
                $run->save();
                app(\App\Services\WorklistSync::class)->syncRun($run);
            }
            $q->lims_worklist_id = $wl;
            $q->save();
            $this->erQid = null;
            Notification::make()->success()->title('Worklist Saved')->body($wl . ' linked. Enter the Pass/Fail result when the read is complete.')->send();
            return;
        }
        $worklist = trim((string) ($this->er['worklist'] ?? ''));
        if ($worklist === '') {
            Notification::make()->danger()->title('Worklist Required')->body('Enter the LIMS worklist before recording results.')->send();
            return;
        }
        // Normalize the EM- worklist prefix (the input collects numbers only).
        $worklist = strtoupper($worklist);
        if (! str_starts_with($worklist, 'EM-')) { $worklist = 'EM-' . ltrim(preg_replace('/^EM[-\s]*/i', '', $worklist), '-'); }
        if ($overall === 'fail' && trim((string) ($this->er['trackwise_id'] ?? '')) === '') {
            Notification::make()->danger()->title('TrackWise NC Required')->body('A fail requires the TrackWise NC number (per SOP-AST-28480).')->send();
            return;
        }
        // Normalize the NC- TrackWise prefix on a fail (input collects numbers only).
        if ($overall === 'fail') {
            $tw = strtoupper(trim((string) $this->er['trackwise_id']));
            if (! str_starts_with($tw, 'NC-')) { $tw = 'NC-' . ltrim(preg_replace('/^NC[-\s]*/i', '', $tw), '-'); }
            $this->er['trackwise_id'] = $tw;
        }
        $run = QualificationRun::where('personnel_id', $q->personnel_id)->latest('run_date')->latest('id')->first();
        if ($run) {
            $run->lims_worklist_id = $worklist;
            $run->results_entered_at = now();
            $run->results_released_at = now();
            $run->recorded_by = $run->recorded_by ?: Auth::id();
            $run->result = $overall === 'fail' ? \App\Enums\RunResult::Fail : \App\Enums\RunResult::Pass;
            // Veeva report number (RPT-AST- added; input collects numbers only). The model saving-hook
            // auto-fills veeva_url from the catalog.
            $vv = trim((string) ($this->er['veeva'] ?? ''));
            if ($vv !== '') {
                $vv = strtoupper($vv);
                if (! str_starts_with($vv, 'RPT-AST-')) { $vv = 'RPT-AST-' . ltrim(preg_replace('/^RPT-AST[-\s]*/i', '', $vv), '-'); }
                $run->veeva_doc_number = $vv;
            }
            // TrackWise NC on a fail (the model saving-hook auto-fills lims_nc_url from the catalog).
            if ($overall === 'fail') { $run->lims_nc_number = $this->er['trackwise_id']; }
            $run->save();
        }
        app(\App\Services\QualificationEngine::class)->recompute($q->fresh());
        $q = $q->fresh();
        \App\Services\AutomationEngine::fire(
            $overall === 'fail' ? \App\Enums\AutomationTrigger::RunFailed : \App\Enums\AutomationTrigger::RunPassed,
            ['personnel' => $q->personnel, 'qualification' => $q]
        );
        $q->workflow_stage = $overall === 'fail' ? WorkflowStage::Failed : WorkflowStage::ResultsReleased;
        $q->stage_changed_at = now();
        $q->save();
        if ($overall === 'fail') {
            $nc = \App\Models\NonConformance::firstOrCreate(
                ['qualification_run_id' => $run?->id, 'nc_type' => 'failed_run'],
                [
                    'qualification_id' => $q->id,
                    'personnel_id' => $q->personnel_id,
                    'status' => 'open',
                    'observed_date' => now()->toDateString(),
                    'created_by' => Auth::id(),
                    'trackwise_id' => $this->er['trackwise_id'] ?? null,
                    'summary' => $this->er['nc_note'] ?? 'Auto-created from failed qualification run. Link TrackWise NC.',
                ]
            );
            if (! empty($this->er['trackwise_id']) && $nc->trackwise_id !== $this->er['trackwise_id']) {
                $nc->trackwise_id = $this->er['trackwise_id'];
            }
            if (! empty($nc->trackwise_id) && ($doc = \App\Models\NcDocument::findByNumber($nc->trackwise_id))) {
                $nc->trackwise_url = $doc->url ?: $nc->trackwise_url;
                $nc->trackwise_status = $doc->workflow_status ?: $nc->trackwise_status;
            }
            $nc->save();
            \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::NcOpened, ['personnel' => $q->personnel]);
        }
        $this->erQid = null;
        Notification::make()->success()->title($overall === 'fail' ? 'Failed, Sent To QA Determination' : 'Results Released')
            ->body(($q->personnel?->full_name ?? 'Operator') . ': ' . ucfirst($overall) . ($overall === 'fail'
                ? ', NC opened and sent to QA determination.'
                : '. Download the approval form, wet-sign, upload to Veeva, then enter the Veeva number to send to QA.'))->send();
    }

    // ---- Add-Worklist-only modal (incubating dashboard / anywhere a run lacks a worklist) ----
    public ?int $awQid = null;
    public string $awWorklist = '';
    public function openAddWorklist(int $qid): void
    {
        if (! $this->canEvaluate()) { Notification::make()->danger()->title('Not Authorized')->body('QC Micro role required.')->send(); return; }
        $q = Qualification::find($qid);
        $run = $q ? QualificationRun::where('personnel_id', $q->personnel_id)->latest('run_date')->latest('id')->first() : null;
        $this->awQid = $qid;
        $this->awWorklist = $run?->lims_worklist_id ?? '';
    }
    public function closeAddWorklist(): void { $this->awQid = null; $this->awWorklist = ''; }
    public function awPersonName(): ?string
    {
        return $this->awQid ? (Qualification::with('personnel')->find($this->awQid)?->personnel?->full_name ?? 'Operator') : null;
    }
    public function saveAddWorklist(): void
    {
        if (! $this->canEvaluate()) { Notification::make()->danger()->title('Not Authorized')->send(); return; }
        $q = Qualification::find($this->awQid);
        if (! $q) { $this->awQid = null; return; }
        $wl = strtoupper(trim($this->awWorklist));
        if ($wl === '') { Notification::make()->danger()->title('Worklist Required')->send(); return; }
        if (! str_starts_with($wl, 'EM-')) { $wl = 'EM-' . ltrim(preg_replace('/^EM[-\s]*/i', '', $wl), '-'); }
        // stamp the qualification + every run in the current cycle, like the run scheduler does.
        $q->lims_worklist_id = $wl;
        $q->save();
        $runsQ = QualificationRun::where('personnel_id', $q->personnel_id);
        if ($q->cycle_started_at) { $runsQ->whereDate('run_date', '>=', $q->cycle_started_at); }
        $runsQ->update(['lims_worklist_id' => $wl]);
        // pull LIMS state immediately if the worklist is in the catalog.
        $latest = QualificationRun::where('personnel_id', $q->personnel_id)->latest('id')->first();
        if ($latest) { app(\App\Services\WorklistSync::class)->syncRun($latest); }
        $this->awQid = null; $this->awWorklist = '';
        Notification::make()->success()->title('Worklist Linked')->body($wl . ' linked. LIMS data will sync.')->send();
    }

    public function enterResultsAction(): Action
    {
        return Action::make('enterResultsInc')
            ->label('Enter Results')
            ->icon('heroicon-m-clipboard-document-check')            ->color('success')
            ->size(\Filament\Support\Enums\Size::Small)
            ->button()
            ->modalHeading('Enter LIMS Results')
            ->modalWidth('md')
            ->fillForm(function (array $arguments) {
                $q = Qualification::find($arguments['id'] ?? null);
                $run = $q ? QualificationRun::where('personnel_id', $q->personnel_id)->latest('id')->first() : null;
                return ['lims_worklist_id' => $run?->lims_worklist_id];
            })
            ->schema([
                TextInput::make('lims_worklist_id')->label('LIMS Worklist ID')->columnSpanFull(),
                ToggleButtons::make('overall')->label('Overall Result')->options(['pass' => 'Pass', 'fail' => 'Fail'])->colors(['pass' => 'success', 'fail' => 'danger'])->icons(['pass' => 'heroicon-m-check-circle', 'fail' => 'heroicon-m-x-circle'])->inline()->grouped()->required()->live(),
                TextInput::make('trackwise_id')->label('TrackWise NC Number')->columnSpanFull()
                    ->helperText('Required on a fail. The TrackWise NC opened for this excursion (per SOP-AST-28480).')
                    ->visible(fn ($get) => ($get('overall') ?? '') === 'fail')
                    ->required(fn ($get) => ($get('overall') ?? '') === 'fail'),
                TextInput::make('nc_note')->label('Observation / Note (Optional)')->columnSpanFull()
                    ->helperText('Brief observation for the NC (not a transcription).')
                    ->visible(fn ($get) => ($get('overall') ?? '') === 'fail'),
            ])
            ->action(function (array $data, array $arguments) {
                if (! $this->canEvaluate()) {
                    Notification::make()->danger()->title('Not authorized')->body('QC Micro role required to enter results.')->send();
                    return;
                }
                $q = Qualification::with('personnel')->find($arguments['id'] ?? null);
                if (! $q) return;
                $overall = $data['overall'] ?? 'pass';
                $run = QualificationRun::where('personnel_id', $q->personnel_id)->latest('run_date')->latest('id')->first();
                if ($run) {
                    $run->lims_worklist_id = $data['lims_worklist_id'] ?? $run->lims_worklist_id;
                    $run->results_entered_at = now();
                    $run->results_released_at = now();
                    $run->recorded_by = $run->recorded_by ?: Auth::id();
                    $run->result = $overall === 'fail' ? \App\Enums\RunResult::Fail : \App\Enums\RunResult::Pass;
                    $run->save();
                }
                // count the pass now that the real result is known (run was Pending)
                app(\App\Services\QualificationEngine::class)->recompute($q->fresh());
                $q = $q->fresh();
                \App\Services\AutomationEngine::fire(
                    $overall === 'fail' ? \App\Enums\AutomationTrigger::RunFailed : \App\Enums\AutomationTrigger::RunPassed,
                    ['personnel' => $q->personnel, 'qualification' => $q]
                );
                // Pass -> Results Released: the approval form is generated for QCM wet signature + Veeva upload,
                // then QCM enters the Veeva number to send to QA. Fail -> Failed + NC.
                $q->workflow_stage = $overall === 'fail' ? WorkflowStage::Failed : WorkflowStage::ResultsReleased;
                $q->stage_changed_at = now();
                $q->save();
                // every failed run gets a non-conformance record (TrackWise to be linked)
                if ($overall === 'fail') {
                    $nc = \App\Models\NonConformance::firstOrCreate(
                        ['qualification_run_id' => $run?->id, 'nc_type' => 'failed_run'],
                        [
                            'qualification_id' => $q->id,
                            'personnel_id' => $q->personnel_id,
                            'status' => 'open',
                            'observed_date' => now()->toDateString(),
                            'created_by' => Auth::id(),
                            'trackwise_id' => $data['trackwise_id'] ?? null,
                            'summary' => $data['nc_note'] ?? 'Auto-created from failed qualification run. Link TrackWise NC.',
                        ]
                    );
                    if (! empty($data['trackwise_id']) && $nc->trackwise_id !== $data['trackwise_id']) {
                        $nc->trackwise_id = $data['trackwise_id'];
                    }
                    // Auto-fill the NC link + workflow status from the NC catalog when known.
                    if (! empty($nc->trackwise_id)) {
                        if ($doc = \App\Models\NcDocument::findByNumber($nc->trackwise_id)) {
                            $nc->trackwise_url = $doc->url ?: $nc->trackwise_url;
                            $nc->trackwise_status = $doc->workflow_status ?: $nc->trackwise_status;
                        }
                    }
                    $nc->save();
                    \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::NcOpened, ['personnel' => $q->personnel]);
                }
                Notification::make()->success()->title($overall === 'fail' ? 'Failed, Sent To QA Determination' : 'Results Released')
                    ->body(($q->personnel?->full_name ?? 'Operator') . ': ' . ucfirst($overall) . ($overall === 'fail'
                        ? ', NC opened and sent to QA determination.'
                        : '. Download the approval form, wet-sign, upload to Veeva, then enter the Veeva number to send to QA.'))->send();
            });
    }

    /** QCM final sign-off: enter the Veeva report number (after wet-sign + upload) and send to QA. */
    public function qcmSignOffAction(): Action
    {
        return Action::make('qcmSignOff')
            ->label('QCM Sign-off (Veeva)')
            ->icon('heroicon-m-document-check')
            ->color('primary')
            ->size(\Filament\Support\Enums\Size::Small)
            ->button()
            ->modalHeading('QCM Sign-off, Enter Veeva Report Number')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Sign & Send To QA')
            ->fillForm(function (array $arguments) {
                $q = Qualification::find($arguments['id'] ?? null);
                $run = $q ? QualificationRun::where('personnel_id', $q->personnel_id)->latest('id')->first() : null;
                return ['veeva_doc_number' => $run?->veeva_doc_number, 'veeva_url' => $run?->veeva_url];
            })
            ->schema(function () {
                $fields = [
                    TextInput::make('veeva_doc_number')->label('Veeva Report Number')->required()
                        ->helperText('The Veeva document/report number for the signed FORM-AST-36749.'),
                    TextInput::make('veeva_url')->label('Veeva Link (Optional)')->url(),
                ];
                if ((bool) Setting::get('esig_required', true)) {
                    $fields[] = TextInput::make('password')->label('Confirm Your Password')->password()->required()
                        ->helperText('Re-enter your password to apply your QCM electronic signature.');
                }
                return $fields;
            })
            ->action(function (array $data, array $arguments) {
                if (! $this->canEvaluate()) {
                    Notification::make()->danger()->title('Not Authorized')->body('QC Micro role required.')->send();
                    return;
                }
                $q = Qualification::with('personnel')->find($arguments['id'] ?? null);
                if (! $q) return;
                if ($this->signerIsSubject($q)) {
                    Notification::make()->danger()->title('Two-Person Rule')
                        ->body('You cannot sign off your own qualification. Another QC Micro analyst must sign.')->send();
                    return;
                }
                if ((bool) Setting::get('esig_required', true) && ! \Illuminate\Support\Facades\Hash::check($data['password'] ?? '', Auth::user()->password)) {
                    Notification::make()->danger()->title('Signature Failed')->body('Password did not match.')->send();
                    return;
                }
                $run = QualificationRun::where('personnel_id', $q->personnel_id)->latest('run_date')->latest('id')->first();
                if ($run) {
                    $run->veeva_doc_number = $data['veeva_doc_number'] ?? null;
                    $run->veeva_url = $data['veeva_url'] ?? null;
                    // Auto-fill the link from the Veeva catalog when a number was entered without a link.
                    if ($run->veeva_doc_number && ! $run->veeva_url) {
                        $run->veeva_url = \App\Models\VeevaDocument::urlForNumber($run->veeva_doc_number);
                    }
                    $run->qcm_signed_at = now();
                    $run->qcm_signed_by = Auth::id();
                    $run->save();
                }
                \App\Models\ElectronicSignature::create([
                    'signable_type' => QualificationRun::class,
                    'signable_id' => $run?->id,
                    'user_id' => Auth::id(),
                    'signer_name' => Auth::user()->name,
                    'meaning' => 'QCM Sign-off',
                    'statement' => 'QCM result sign-off; Veeva report ' . ($data['veeva_doc_number'] ?? '') . ' linked. Sent to QA.',
                    'signed_at' => now(),
                ]);
                $q->workflow_stage = WorkflowStage::QaReview;
                $q->stage_changed_at = now();
                $q->save();
                Notification::make()->success()->title('Sent To QA')
                    ->body(($q->personnel?->full_name ?? 'Operator') . ' signed off, Veeva ' . ($data['veeva_doc_number'] ?? '') . ', now in QA Sign-off.')->send();
            });
    }
}
