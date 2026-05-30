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
    protected static ?string $navigationLabel = 'QA Sign-off Queue';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'QA Sign-off Queue';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.qa-queue';

    public function getQueue()
    {
        return Qualification::with('personnel', 'qaOwner')
            ->whereIn('workflow_stage', [WorkflowStage::QaReview->value, WorkflowStage::ResultsReleased->value])
            ->orderBy('stage_changed_at')
            ->get();
    }

    public function getFailed()
    {
        return Qualification::with('personnel')
            ->where('workflow_stage', WorkflowStage::Failed->value)
            ->get();
    }

    public function canApprove(): bool
    {
        return (bool) Auth::user()?->hasCapability(Capability::QaApprove);
    }

    /** QA sign-off with two-component electronic signature (Part 11). */
    public function signOffAction(): Action
    {
        return Action::make('signOff')
            ->label('Sign Off')
            ->icon('heroicon-m-pencil-square')
            ->color('success')
            ->modalHeading('Electronic Signature, QA Sign-off')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Sign')
            ->schema(function (array $arguments) {
                $q = Qualification::with('personnel')->find($arguments['id'] ?? null);
                $name = $q?->personnel?->full_name ?? 'this qualification';
                $esig = (bool) Setting::get('esig_required', true);
                $fields = [
                    Placeholder::make('statement')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div style="font-size:13.5px;line-height:1.5;">By signing, I, <strong>' . e(Auth::user()?->name) .
                            '</strong>, certify that I have reviewed the qualification record for <strong>' . e($name) .
                            '</strong> and approve it as complete. This electronic signature is the legally binding equivalent of my handwritten signature.</div>'
                        )),
                    TextInput::make('meaning')->label('Signature Meaning')
                        ->default('Approved')->required(),
                ];
                if ($esig) {
                    $fields[] = TextInput::make('password')->label('Confirm Your Password')->password()->required()
                        ->helperText('Re-enter your password to apply your electronic signature.');
                }
                return $fields;
            })
            ->action(function (array $data, array $arguments) {
                if (! $this->canApprove()) {
                    Notification::make()->danger()->title('Not authorized')->send();
                    return;
                }
                // verify identity (manifestation) if e-sig required
                if ((bool) Setting::get('esig_required', true)) {
                    if (! Hash::check($data['password'] ?? '', Auth::user()->password)) {
                        Notification::make()->danger()->title('Signature failed')
                            ->body('Password did not match. Sign-off not applied.')->send();
                        return;
                    }
                }
                $q = Qualification::with('personnel')->find($arguments['id'] ?? null);
                if (! $q) return;

                // record the electronic signature
                ElectronicSignature::create([
                    'signable_type' => Qualification::class,
                    'signable_id' => $q->id,
                    'user_id' => Auth::id(),
                    'signer_name' => Auth::user()->name,
                    'meaning' => $data['meaning'] ?? 'Approved',
                    'statement' => 'QA sign-off: qualification approved as complete.',
                    'signed_at' => now(),
                ]);

                $q->workflow_stage = WorkflowStage::QaSignoff;
                $q->stage_changed_at = now();
                $q->status = 'qualified';
                if (! $q->qualified_date) $q->qualified_date = now();
                if (! $q->due_date) $q->due_date = now()->addMonths((int) Setting::get('cycle_months', 12));
                $q->save();
                \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::Qualified, ['personnel' => $q->personnel, 'qualification' => $q]);

                Notification::make()->success()->title('Signed off')
                    ->body(($q->personnel?->full_name ?? 'Qualification') . ' is now Qualified.')->send();
            });
    }

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
    public function recommendAction(): Action
    {
        return Action::make('recommend')
            ->label('QA Determination')
            ->icon('heroicon-m-scale')
            ->color('danger')
            ->modalHeading('QA Requalification Determination')
            ->modalWidth('lg')
            ->schema([
                \Filament\Forms\Components\Radio::make('recommendation')
                    ->label('Requalification Path')
                    ->options([
                        'requal_three' => 'Full requalification, 3 successful runs required',
                        'requal_one' => 'Annual requalification, 1 successful run required',
                    ])
                    ->default('requal_three')->required(),
                \Filament\Forms\Components\Toggle::make('require_retraining')
                    ->label('Require Gowning Class Retraining')
                    ->helperText('On = clears the class on file; the person must retake the gowning class before runs. Off = class stays on file (the normal case).')
                    ->default(false),
                TextInput::make('note')->label('Determination Note')->columnSpanFull(),
            ])
            ->action(function (array $data, array $arguments) {
                if (! $this->canApprove()) {
                    Notification::make()->danger()->title('Not authorized')->send();
                    return;
                }
                $q = Qualification::with('personnel')->find($arguments['id'] ?? null);
                if (! $q) return;

                $three = ($data['recommendation'] ?? 'requal_three') === 'requal_three';
                $retrain = (bool) ($data['require_retraining'] ?? false);
                $q->qa_recommendation = $data['recommendation'] ?? 'requal_three';
                $q->qa_recommendation_note = $data['note'] ?? null;
                $q->runs_required = $three
                    ? (int) Setting::get('initial_runs_required', 3)
                    : (int) Setting::get('annual_runs_required', 1);
                $q->runs_completed = 0;
                $q->status = 'in_progress';
                $q->cycle_started_at = now()->toDateString(); // fresh cycle: don't recount prior runs

                if ($retrain) {
                    // QA requires retraining: clear class on file, back to Class Pending
                    $q->class_on_file = false;
                    $q->class_on_file_date = null;
                    $q->workflow_stage = WorkflowStage::ClassPending;
                } else {
                    // class stays on file: straight to ready-to-book for the required run(s)
                    $q->workflow_stage = WorkflowStage::ClassComplete;
                }
                $q->stage_changed_at = now();
                $q->save();

                // record the determination as a signature event
                ElectronicSignature::create([
                    'signable_type' => Qualification::class,
                    'signable_id' => $q->id,
                    'user_id' => Auth::id(),
                    'signer_name' => Auth::user()->name,
                    'meaning' => 'QA Determination',
                    'statement' => 'Requalification: ' . ($three ? '3 runs' : '1 run')
                        . ($retrain ? ', class retraining required' : ', class stays on file') . '. ' . ($data['note'] ?? ''),
                    'signed_at' => now(),
                ]);

                // The failed run that triggered this determination is now terminal/complete.
                $failedRun = \App\Models\QualificationRun::where('personnel_id', $q->personnel_id)
                    ->whereNull('deleted_at')->latest('run_date')->latest('id')->first();
                if ($failedRun) {
                    $failedRun->update([
                        'qa_determination' => $three ? 'fail_requalify' : ($retrain ? 'fail_retrain' : 'fail_redo'),
                        'qa_determined_at' => now(),
                        'is_complete' => true,
                        'qa_signed_by' => Auth::id(),
                        'qa_notes' => $data['note'] ?? null,
                    ]);
                    // The next run this person performs descends from this failed run (child record).
                    $q->pending_parent_run_id = $failedRun->id;
                }

                // A fail determination opens a Non-Conformance for QA review (with a generated NC number),
                // unless the failed run already spawned one.
                $existingNc = $failedRun
                    ? \App\Models\NonConformance::where('qualification_run_id', $failedRun->id)->exists()
                    : false;
                if (! $existingNc) {
                    $nc = \App\Models\NonConformance::create([
                        'qualification_id' => $q->id,
                        'qualification_run_id' => $failedRun?->id,
                        'personnel_id' => $q->personnel_id,
                        'nc_type' => 'failed_run',
                        'status' => 'open',
                        'observed_date' => now()->toDateString(),
                        'created_by' => Auth::id(),
                        'summary' => 'Opened from QA determination (' . ($three ? '3-run' : '1-run') . ' requalification'
                            . ($retrain ? ', retraining required' : '') . '). ' . ($data['note'] ?? ''),
                    ]);
                    \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::NcOpened, ['personnel' => $q->personnel]);
                }

                // AUTOMATION: if no retraining needed, auto-book the requal run(s) into the next day
                $bookedMsg = '';
                if (! $retrain) {
                    $res = app(\App\Services\AutoScheduler::class)->bookNext($q->fresh());
                    if ($res) {
                        $bookedMsg = ' Auto-booked for the next available run day.';
                    }
                }

                Notification::make()->success()->title('Determination recorded')
                    ->body(($q->personnel?->full_name ?? 'Operator') . ': ' . ($three ? '3 runs' : '1 run')
                        . ($retrain ? ' + class retraining.' : '.') . $bookedMsg)->send();
            });
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

    public function markFailed(int $id): void
    {
        if (! $this->canApprove()) {
            Notification::make()->danger()->title('Not authorized')->send();
            return;
        }
        $q = Qualification::find($id);
        if (! $q) return;
        $q->workflow_stage = WorkflowStage::Failed;
        $q->stage_changed_at = now();
        $q->save();
        Notification::make()->warning()->title('Marked for determination')->send();
    }
}
