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
        return (bool) ($u && ($u->hasCapability(Capability::ManageScheduling) || $u->hasCapability(Capability::QaReview)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationLabel = 'Incubation';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Incubation & Results';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.incubation-board';

    public function mount(): void
    {
        // opportunistic time-based advance whenever the board is viewed
        app(\App\Services\IncubationAdvancer::class)->run();
    }

    public function incubationDays(): int { return (int) Setting::get('incubation_days', 8); }

    public function getIncubating()
    {
        $days = $this->incubationDays();
        return Qualification::with('personnel')
            ->whereIn('workflow_stage', [WorkflowStage::Incubating->value, WorkflowStage::AwaitingResults->value])
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
                    'awaiting' => $q->workflow_stage === WorkflowStage::AwaitingResults,
                    'worklist' => $run?->lims_worklist_id,
                ];
            })
            ->sortBy('ready')->values();
    }

    /** Enter LIMS results (worklist + overall pass/fail), release, route to QA. */
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
                Select::make('overall')->label('Overall Result')->options(['pass' => 'Pass', 'fail' => 'Fail'])->required(),
            ])
            ->action(function (array $data, array $arguments) {
                $q = Qualification::with('personnel')->find($arguments['id'] ?? null);
                if (! $q) return;
                $overall = $data['overall'] ?? 'pass';
                $run = QualificationRun::where('personnel_id', $q->personnel_id)->latest('run_date')->latest('id')->first();
                if ($run) {
                    $run->lims_worklist_id = $data['lims_worklist_id'] ?? $run->lims_worklist_id;
                    $run->results_entered_at = now();
                    $run->results_released_at = now();
                    $run->result = $overall === 'fail' ? \App\Enums\RunResult::Fail : \App\Enums\RunResult::Pass;
                    $run->save();
                }
                $q->workflow_stage = $overall === 'fail' ? WorkflowStage::Failed : WorkflowStage::QaReview;
                $q->stage_changed_at = now();
                $q->save();
                Notification::make()->success()->title('Results released')
                    ->body(($q->personnel?->full_name ?? 'Operator') . ': ' . ucfirst($overall) . ($overall === 'fail' ? ', sent to QA determination.' : ', sent to QA review.'))->send();
            });
    }
}
