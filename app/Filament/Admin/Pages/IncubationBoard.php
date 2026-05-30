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
    protected static string|\UnitEnum|null $navigationGroup = 'Review';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Lab Review';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.incubation-board';

    /** incubating | evaluation | history */
    public string $tab = 'incubating';
    public function setTab(string $t): void { $this->tab = in_array($t, ['incubating', 'evaluation', 'history'], true) ? $t : 'incubating'; }

    public function mount(): void
    {
        // opportunistic time-based advance whenever the page is viewed
        app(\App\Services\IncubationAdvancer::class)->run();
    }

    public function incubationDays(): int { return (int) Setting::get('incubation_days', 8); }

    /** Runs still in incubation (timer running), the lab holding area. */
    public function getIncubating()
    {
        $days = $this->incubationDays();
        return Qualification::with('personnel')
            ->where('workflow_stage', WorkflowStage::Incubating->value)
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
            ->where('workflow_stage', WorkflowStage::AwaitingResults->value)
            ->get()
            ->map(function ($q) {
                $run = QualificationRun::where('personnel_id', $q->personnel_id)->latest('run_date')->latest('id')->first();
                return (object) [
                    'id' => $q->id,
                    'name' => $q->personnel?->full_name ?? 'Unknown',
                    'employee_id' => $q->personnel?->employee_id,
                    'worklist' => $run?->lims_worklist_id,
                    'performed' => $run?->run_date,
                    'cycle' => $q->type?->label(),
                    'progress' => (int) $q->runs_completed . ' / ' . (int) $q->runs_required,
                ];
            })
            ->sortBy('performed')->values();
    }

    /** Recently evaluated runs (results entered), for the History tab. */
    public function getHistory()
    {
        return QualificationRun::with('personnel')
            ->whereNotNull('results_entered_at')
            ->where('result', '!=', \App\Enums\RunResult::Pending->value)
            ->latest('results_entered_at')->latest('id')->limit(60)->get()
            ->map(fn ($r) => (object) [
                'id' => $r->id,
                'name' => $r->personnel?->full_name ?? 'Unknown',
                'employee_id' => $r->personnel?->employee_id,
                'worklist' => $r->lims_worklist_id,
                'result' => $r->result instanceof \BackedEnum ? ucfirst($r->result->value) : ucfirst((string) $r->result),
                'entered_at' => $r->results_entered_at,
                'run_date' => $r->run_date,
            ]);
    }

    public function canEvaluate(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::RecordRuns) || $u->hasCapability(Capability::ManageScheduling)));
    }

    /** QCM enters LIMS results (worklist + overall pass/fail + optional NC note), routes to QA. */
    public function enterResultsAction(): Action
    {
        return Action::make('enterResultsInc')
            ->label('Enter Results')
            ->icon('heroicon-m-clipboard-document-check')
            ->color('success')
            ->modalHeading('Enter LIMS Results')
            ->modalWidth('md')
            ->fillForm(function (array $arguments) {
                $q = Qualification::find($arguments['id'] ?? null);
                $run = $q ? QualificationRun::where('personnel_id', $q->personnel_id)->latest('id')->first() : null;
                return ['lims_worklist_id' => $run?->lims_worklist_id];
            })
            ->schema([
                TextInput::make('lims_worklist_id')->label('LIMS Worklist ID')->columnSpanFull(),
                Select::make('overall')->label('Overall Result')->options(['pass' => 'Pass', 'fail' => 'Fail'])->required()->live(),
                TextInput::make('nc_note')->label('Non-Conformance Note (Fail)')->columnSpanFull()
                    ->helperText('On a fail, an NC is opened for QA. Add the observation / TrackWise note here.')
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
                $q->workflow_stage = $overall === 'fail' ? WorkflowStage::Failed : WorkflowStage::QaReview;
                $q->stage_changed_at = now();
                $q->save();
                // every failed run gets a non-conformance record (TrackWise to be linked)
                if ($overall === 'fail') {
                    \App\Models\NonConformance::firstOrCreate(
                        ['qualification_run_id' => $run?->id, 'nc_type' => 'failed_run'],
                        [
                            'qualification_id' => $q->id,
                            'personnel_id' => $q->personnel_id,
                            'status' => 'open',
                            'observed_date' => now()->toDateString(),
                            'created_by' => Auth::id(),
                            'summary' => $data['nc_note'] ?? 'Auto-created from failed qualification run. Link TrackWise NC.',
                        ]
                    );
                    \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::NcOpened, ['personnel' => $q->personnel]);
                }
                Notification::make()->success()->title('Results submitted to QA')
                    ->body(($q->personnel?->full_name ?? 'Operator') . ': ' . ucfirst($overall) . ($overall === 'fail' ? ', NC opened and sent to QA determination.' : ', sent to QA sign-off.'))->send();
            });
    }
}
