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
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Run Scheduler';
    protected static string|\UnitEnum|null $navigationGroup = 'Scheduling';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Run Scheduler';

    protected string $view = 'filament.pages.run-day-roster';

    public ?string $date = null;
    public string $tab = 'schedule';   // schedule | roster

    // new-run-day form fields
    public ?string $newDate = null;
    public ?string $newStart = '09:00';
    public ?string $newEnd = '11:00';
    public ?string $newCleanroom = null;
    public ?int $newCapacity = null;
    public ?int $newAnalystId = null;
    public bool $showAddSlot = false;

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

    /** Upcoming + recent run days for the Schedule tab, with seat usage. */
    public function scheduleDays()
    {
        $scheduler = app(\App\Services\AutoScheduler::class);
        return RunSlot::with('analyst')
            ->where('status', '!=', 'cancelled')
            ->whereDate('slot_date', '>=', now()->subDays(7)->toDateString())
            ->orderBy('slot_date')->orderBy('start_time')
            ->get()
            ->map(function ($s) use ($scheduler) {
                $s->seats_left = $scheduler->seatsLeft($s);
                $s->booked = $s->reservations()->whereIn('status', ['approved', 'completed', 'requested'])->count();
                return $s;
            });
    }

    public function cleanroomOptions(): array
    {
        return \App\Models\Cleanroom::where('is_active', true)->orderBy('name')->pluck('name', 'name')->all();
    }

    public function analystOptions(): array
    {
        return \App\Models\User::where('is_active', true)
            ->where(fn ($q) => $q->where('team', 'qcm')->orWhere('is_team_manager', true))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public function addSlot(): void
    {
        if (! Auth::user()?->hasCapability(\App\Enums\Capability::ManageScheduling)) return;
        if (! $this->newDate) {
            Notification::make()->danger()->title('Pick a date')->send();
            return;
        }
        RunSlot::create([
            'slot_date' => $this->newDate,
            'start_time' => $this->newStart ?: null,
            'end_time' => $this->newEnd ?: null,
            'cleanroom' => $this->newCleanroom,
            'capacity' => $this->newCapacity ?: (int) \App\Models\Setting::get('runs_per_day_capacity', 10),
            'assigned_analyst_id' => $this->newAnalystId ?: null,
            'status' => 'open',
        ]);
        $this->showAddSlot = false;
        $this->newCleanroom = null; $this->newAnalystId = null; $this->newCapacity = null;
        Notification::make()->success()->title('Run day added')->send();
    }

    public function cancelSlotDay(int $slotId): void
    {
        if (! Auth::user()?->hasCapability(\App\Enums\Capability::ManageScheduling)) return;
        $slot = RunSlot::find($slotId);
        if (! $slot) return;
        $moved = app(\App\Services\AutoScheduler::class)->cancelSlot($slot);
        Notification::make()->success()->title('Run day cancelled')
            ->body($moved > 0 ? "{$moved} reservation(s) were rescheduled/notified." : 'No bookings to move.')->send();
    }

    public function viewRoster(string $date): void
    {
        $this->date = $date;
        $this->tab = 'roster';
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
        // record the run as PENDING (result unknown until incubation plates are read)
        app(\App\Services\QualificationEngine::class)
            ->recordRun($res->personnel, \App\Enums\RunResult::Pending, [
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

        // now that the real result is known, recompute the qualification so the pass
        // actually counts (the run was Pending from Mark Performed until this point)
        if ($q) {
            app(\App\Services\QualificationEngine::class)->recompute($q->fresh());
            $q = $q->fresh();
        }

        // fire the run-outcome automation at the real result moment
        \App\Services\AutomationEngine::fire(
            $overall === 'fail' ? \App\Enums\AutomationTrigger::RunFailed : \App\Enums\AutomationTrigger::RunPassed,
            ['personnel' => $res->personnel, 'qualification' => $q]
        );

        if ($q) {
            // recompute may have advanced/qualified; only set QA stages if still mid-cycle
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
            \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::NcOpened, ['personnel' => $res->personnel]);
        }
        Notification::make()->success()->title('Results entered')
            ->body(($res->personnel?->full_name ?? 'Operator') . ': ' . ucfirst($overall) . ($overall === 'fail' ? ', sent to QA determination.' : ', sent to QA review.'))->send();
    }
}
