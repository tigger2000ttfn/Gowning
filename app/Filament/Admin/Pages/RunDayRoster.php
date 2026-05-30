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
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Run Scheduler';

    protected string $view = 'filament.pages.run-day-roster';

    public ?string $date = null;
    public string $tab = 'schedule';   // schedule | reservations | roster

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

    // ===== Reservations tab =====
    public bool $showAddRes = false;
    public ?int $addResSlotId = null;
    public ?int $addResPersonnelId = null;
    public string $resStatusFilter = '';

    public function openSlotsForBooking(): array
    {
        $scheduler = app(\App\Services\AutoScheduler::class);
        return RunSlot::where('status', 'open')
            ->whereDate('slot_date', '>=', now()->toDateString())
            ->orderBy('slot_date')->orderBy('start_time')->get()
            ->filter(fn ($s) => $scheduler->seatsLeft($s) > 0)
            ->mapWithKeys(fn ($s) => [$s->id => $s->slot_date->format('M j, Y') . ' · ' . ($s->cleanroom ?: 'Run Day')
                . ' (' . $scheduler->seatsLeft($s) . ' seats)'])->all();
    }

    public function bookablePersonnel(): array
    {
        return \App\Models\Personnel::where('is_active', true)
            ->orderBy('last_name')->orderBy('first_name')->get()
            ->mapWithKeys(fn ($p) => [$p->id => $p->full_name . ' (' . $p->employee_id . ')'])->all();
    }

    /** Reservations grouped by run day, for the Reservations tab. */
    public function reservationsByDay(): array
    {
        return Reservation::with(['personnel', 'runSlot'])
            ->whereHas('runSlot')
            ->when($this->resStatusFilter !== '', fn ($q) => $q->where('status', $this->resStatusFilter))
            ->get()
            ->sortBy(fn ($r) => $r->runSlot?->slot_date)
            ->groupBy(fn ($r) => $r->runSlot?->slot_date?->format('Y-m-d') ?? 'unscheduled')
            ->map(fn ($group, $day) => [
                'day' => $day === 'unscheduled' ? 'Unscheduled' : \Illuminate\Support\Carbon::parse($day)->format('l, M j, Y'),
                'date' => $day,
                'rows' => $group->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->personnel?->full_name ?? 'Unknown',
                    'employee_id' => $r->personnel?->employee_id,
                    'cleanroom' => $r->runSlot?->cleanroom,
                    'time' => $r->runSlot?->start_time ? \Illuminate\Support\Carbon::parse($r->runSlot->start_time)->format('g:i A') : null,
                    'status' => $r->status instanceof \BackedEnum ? $r->status->value : $r->status,
                ])->values()->all(),
            ])->values()->all();
    }

    public function addReservation(): void
    {
        if (! Auth::user()?->hasCapability(\App\Enums\Capability::ManageScheduling)) return;
        if (! $this->addResSlotId || ! $this->addResPersonnelId) {
            Notification::make()->danger()->title('Pick a person and a run day')->send();
            return;
        }
        $slot = RunSlot::find($this->addResSlotId);
        $scheduler = app(\App\Services\AutoScheduler::class);
        if ($slot && $scheduler->seatsLeft($slot) <= 0) {
            Notification::make()->warning()->title('That run day is full')->body('Pick another day or raise capacity.')->send();
            return;
        }
        Reservation::create([
            'run_slot_id' => $this->addResSlotId,
            'personnel_id' => $this->addResPersonnelId,
            'status' => 'approved',
            'requested_at' => now(),
            'decided_at' => now(),
            'decided_by' => Auth::id(),
            'notes' => 'Added by analyst',
        ]);
        $q = \App\Models\Qualification::where('personnel_id', $this->addResPersonnelId)->first();
        if ($q && in_array($q->workflow_stage?->value, ['class_complete', 'class_pending', null], true)) {
            $q->workflow_stage = \App\Enums\WorkflowStage::RunScheduled;
            $q->stage_changed_at = now();
            $q->save();
        }
        $this->showAddRes = false;
        $this->addResPersonnelId = null;
        Notification::make()->success()->title('Reservation added')->send();
    }

    public function approveReservation(int $id): void
    {
        if (! Auth::user()?->hasCapability(\App\Enums\Capability::ManageScheduling)) return;
        $r = Reservation::find($id);
        if (! $r) return;
        $r->update(['status' => 'approved', 'decided_by' => Auth::id(), 'decided_at' => now()]);
        $q = \App\Models\Qualification::where('personnel_id', $r->personnel_id)->first();
        if ($q && in_array($q->workflow_stage?->value, ['class_complete', 'class_pending', null], true)) {
            $q->workflow_stage = \App\Enums\WorkflowStage::RunScheduled;
            $q->stage_changed_at = now();
            $q->save();
        }
    }

    public function markNoShow(int $id): void
    {
        if (! Auth::user()?->hasCapability(\App\Enums\Capability::ManageScheduling)) return;
        $r = Reservation::find($id);
        if ($r) app(\App\Services\AutoScheduler::class)->handleNoShow($r);
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
