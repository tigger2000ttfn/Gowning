<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Enums\WorkflowStage;
use App\Models\Qualification;
use App\Models\QualificationRun;
use App\Models\Setting;
use App\Models\RunSample;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class IncubationBoard extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ManageScheduling)
            || $u->hasCapability(Capability::QaReview)));
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationLabel = 'Incubation';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Incubation & Results Release';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.incubation-board';

    public function incubationDays(): int
    {
        return (int) Setting::get('incubation_days', 5);
    }

    public function getIncubating()
    {
        $days = $this->incubationDays();
        return Qualification::with('personnel')
            ->where('workflow_stage', WorkflowStage::Incubating->value)
            ->get()
            ->map(function ($q) use ($days) {
                $run = QualificationRun::where('personnel_id', $q->personnel_id)
                    ->latest('run_date')->latest('id')->first();
                $started = $run?->incubation_started_at;
                $ready = $started ? $started->copy()->addDays($days) : null;
                return (object) [
                    'id' => $q->id,
                    'name' => $q->personnel?->full_name ?? 'Unknown',
                    'employee_id' => $q->personnel?->employee_id,
                    'started' => $started,
                    'ready' => $ready,
                    'remaining' => $ready ? now()->diffInDays($ready, false) : null,
                    'done' => $ready ? now()->greaterThanOrEqualTo($ready) : false,
                ];
            });
    }

    public function getAwaitingResults()
    {
        return Qualification::with('personnel')
            ->where('workflow_stage', WorkflowStage::ResultsReleased->value)
            ->get();
    }

    /** Release lab results: advance to Results Released, stamp the run, then into QA Review. */
    public function releaseResults(int $id): void
    {
        $q = Qualification::with('personnel')->find($id);
        if (! $q) return;

        $run = QualificationRun::where('personnel_id', $q->personnel_id)
            ->latest('run_date')->latest('id')->first();

        // are any samples failing?
        $hasFail = RunSample::where('personnel_id', $q->personnel_id)
            ->where('result', 'fail')->exists();

        if ($run) {
            $run->results_released_at = now();
            $run->save();
        }

        if ($hasFail) {
            $q->workflow_stage = WorkflowStage::Failed;
            $q->stage_changed_at = now();
            $q->save();
            Notification::make()->warning()->title('Results released, failing samples')
                ->body(($q->personnel?->full_name ?? 'Operator') . ' moved to Failed for QA determination.')->send();
            return;
        }

        // clean results, send to QA review queue
        $q->workflow_stage = WorkflowStage::QaReview;
        $q->stage_changed_at = now();
        $q->save();
        Notification::make()->success()->title('Results released')
            ->body(($q->personnel?->full_name ?? 'Operator') . ' sent to QA review queue.')->send();
    }
}
