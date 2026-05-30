<?php

namespace App\Filament\Admin\Pages;

use App\Models\RunSlot;
use App\Models\Reservation;
use App\Models\Setting;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class RunDayRoster extends Page
{
    public function getHeading(): string { return ''; }
    public static function canAccessNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    public static function shouldRegisterNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    public static function canViewAny(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Run Day Roster';
    protected static string|\UnitEnum|null $navigationGroup = 'Scheduling';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Qualification Run Day Roster';

    protected string $view = 'filament.pages.run-day-roster';

    public ?string $date = null;

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public function slots()
    {
        return RunSlot::with(['reservations' => function ($q) {
                $q->whereIn('status', ['approved', 'completed'])->with('personnel');
            }])
            ->whereDate('slot_date', $this->date ?: now()->toDateString())
            ->orderBy('start_time')
            ->get();
    }


    /** Mark a run as performed (the run attendance sheet). Records the run + advances the stage. */
    public function markPerformed(int $reservationId): void
    {
        $res = Reservation::with('personnel')->find($reservationId);
        if (! $res || ! $res->personnel) {
            Notification::make()->danger()->title('Reservation not found')->send();
            return;
        }
        $res->update(['status' => 'completed']);
        // record the run through the engine (advances workflow_stage to RunPerformed)
        app(\App\Services\QualificationEngine::class)
            ->recordRun($res->personnel, \App\Enums\RunResult::Pass, [
                'run_date' => now()->toDateString(),
                'recorded_by' => Auth::id(),
            ]);
        // move straight into Incubating and stamp the incubation start (= performed date)
        $q = \App\Models\Qualification::where('personnel_id', $res->personnel_id)->first();
        $run = \App\Models\QualificationRun::where('personnel_id', $res->personnel_id)
            ->latest('run_date')->latest('id')->first();
        if ($run && ! $run->incubation_started_at) {
            $run->incubation_started_at = now();
            $run->save();
        }
        if ($q) {
            $q->workflow_stage = \App\Enums\WorkflowStage::Incubating;
            $q->stage_changed_at = now();
            $q->save();
        }
        $days = (int) \App\Models\Setting::get('incubation_days', 8);
        Notification::make()->success()->title('Run performed, incubation started')
            ->body(($res->personnel->full_name ?? 'Operator') . ': plates ready to read in ' . $days . ' days.')->send();
    }

    /** Enter LIMS results (worklist ID + overall pass/fail) for one reservation.
     *  Incubation happens in LIMS; this moves the card to QA Review (pass) or Failed. */
    public function enterResults(int $reservationId, string $overall, ?string $worklist = null): void
    {
        $res = Reservation::with('personnel')->find($reservationId);
        if (! $res) {
            Notification::make()->danger()->title('Reservation not found')->send();
            return;
        }
        $overall = $overall === 'fail' ? 'fail' : 'pass';
        $res->update(['lims_worklist_id' => $worklist]);

        $q = \App\Models\Qualification::where('personnel_id', $res->personnel_id)->first();
        $run = \App\Models\QualificationRun::where('personnel_id', $res->personnel_id)
            ->latest('run_date')->latest('id')->first();
        if ($run) {
            $run->lims_worklist_id = $worklist;
            $run->results_entered_at = now();
            $run->results_released_at = now();
            $run->result = $overall === 'fail' ? \App\Enums\RunResult::Fail : \App\Enums\RunResult::Pass;
            $run->save();
        }
        if ($q) {
            $q->workflow_stage = $overall === 'fail'
                ? \App\Enums\WorkflowStage::Failed
                : \App\Enums\WorkflowStage::QaReview;
            $q->stage_changed_at = now();
            $q->save();
        }
        if ($overall === 'fail') {
            \App\Models\NonConformance::firstOrCreate(
                ['qualification_run_id' => $run?->id, 'nc_type' => 'failed_run'],
                [
                    'qualification_id' => $q?->id,
                    'personnel_id' => $res->personnel_id,
                    'status' => 'open',
                    'observed_date' => now()->toDateString(),
                    'created_by' => Auth::id(),
                    'summary' => 'Auto-created from failed qualification run. Link TrackWise NC.',
                ]
            );
        }
        Notification::make()->success()->title('Results entered')
            ->body(($res->personnel?->full_name ?? 'Operator') . ': ' . ucfirst($overall) . ($overall === 'fail' ? ', sent to QA determination.' : ', sent to QA review.'))->send();
    }
}
