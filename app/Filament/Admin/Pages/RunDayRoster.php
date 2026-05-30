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
    public string $tab = 'overview';   // overview | schedule | reservations | roster

    // new-run-day form fields
    public ?string $newDate = null;
    public ?string $newStart = '09:00';
    public ?string $newEnd = '11:00';
    public ?string $newCleanroom = null;
    public ?int $newCapacity = null;
    public ?int $newAnalystId = null;
    public ?string $newNotes = null;
    public bool $showAddSlot = false;
    // recurrence
    public bool $repeat = false;
    public string $repeatPattern = 'weekly';   // weekly | biweekly | monthly
    public ?string $repeatUntil = null;
    // Run Days list sorting
    public string $sortField = 'slot_date';     // slot_date | cleanroom | capacity | booked
    public string $sortDir = 'asc';

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

    /** Upcoming + recent run days for the Schedule tab, with seat usage. Sortable. */
    public function scheduleDays()
    {
        $scheduler = app(\App\Services\AutoScheduler::class);
        $rows = RunSlot::with('analyst')
            ->where('status', '!=', 'cancelled')
            ->whereDate('slot_date', '>=', now()->subDays(7)->toDateString())
            ->get()
            ->map(function ($s) use ($scheduler) {
                $s->seats_left = $scheduler->seatsLeft($s);
                $s->booked = $s->reservations()->whereIn('status', ['approved', 'completed', 'requested'])->count();
                return $s;
            });

        $dir = $this->sortDir === 'desc' ? -1 : 1;
        $rows = $rows->sort(function ($a, $b) {
            return match ($this->sortField) {
                'cleanroom' => strcmp((string) $a->cleanroom, (string) $b->cleanroom),
                'capacity' => $a->capacity <=> $b->capacity,
                'booked' => $a->booked <=> $b->booked,
                default => ($a->slot_date->toDateString() . ($a->start_time ?? '')) <=> ($b->slot_date->toDateString() . ($b->start_time ?? '')),
            };
        })->values();
        if ($dir === -1) $rows = $rows->reverse()->values();
        return $rows;
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }
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

        $cap = $this->newCapacity ?: (int) \App\Models\Setting::get('runs_per_day_capacity', 10);
        $base = [
            'start_time' => $this->newStart ?: null,
            'end_time' => $this->newEnd ?: null,
            'cleanroom' => $this->newCleanroom,
            'capacity' => $cap,
            'assigned_analyst_id' => $this->newAnalystId ?: null,
            'notes' => $this->newNotes ?: null,
            'status' => 'open',
        ];

        // Build the set of dates (single or recurring up to an end date).
        $dates = [\Illuminate\Support\Carbon::parse($this->newDate)];
        if ($this->repeat && $this->repeatUntil) {
            $until = \Illuminate\Support\Carbon::parse($this->repeatUntil);
            $cursor = \Illuminate\Support\Carbon::parse($this->newDate);
            $guard = 0;
            while ($guard < 400) {
                $cursor = match ($this->repeatPattern) {
                    'biweekly' => $cursor->copy()->addWeeks(2),
                    'monthly' => $cursor->copy()->addMonth(),
                    default => $cursor->copy()->addWeek(),
                };
                if ($cursor->gt($until)) break;
                $dates[] = $cursor;
                $guard++;
            }
        }

        $created = 0;
        foreach ($dates as $d) {
            $exists = RunSlot::whereDate('slot_date', $d->toDateString())
                ->where('cleanroom', $this->newCleanroom)
                ->where('start_time', $this->newStart ?: null)
                ->where('status', '!=', 'cancelled')->exists();
            if ($exists) continue;
            RunSlot::create(array_merge($base, ['slot_date' => $d->toDateString()]));
            $created++;
        }

        $this->showAddSlot = false;
        $this->reset(['newCleanroom', 'newAnalystId', 'newCapacity', 'newNotes', 'repeat', 'repeatUntil']);
        Notification::make()->success()->title($created > 1 ? "{$created} run days added" : 'Run day added')
            ->body($created === 0 ? 'All matching days already existed.' : '')->send();
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

    // ===== Overview tab (mini-dashboard) =====
    public function overviewStats(): array
    {
        $booked = Reservation::whereIn('status', ['requested', 'approved'])->pluck('personnel_id')->filter()->unique()->all();
        $waiting = \App\Models\Qualification::where('workflow_stage', \App\Enums\WorkflowStage::ClassComplete->value)
            ->whereNotIn('personnel_id', $booked)->count();
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();
        $runDaysThisWeek = RunSlot::where('status', 'open')->whereBetween('slot_date', [$weekStart, $weekEnd])->count();
        $pendingReqs = Reservation::where('status', 'requested')->count();
        $incubating = \App\Models\Qualification::where('workflow_stage', \App\Enums\WorkflowStage::Incubating->value)->count();
        // total open seats across upcoming run days
        $scheduler = app(\App\Services\AutoScheduler::class);
        $openSeats = RunSlot::where('status', 'open')->whereDate('slot_date', '>=', now()->toDateString())->get()
            ->sum(fn ($s) => max(0, $scheduler->seatsLeft($s)));

        return [
            ['Awaiting Scheduling', $waiting, 'heroicon-o-clock', '#A4123F'],
            ['Pending Requests', $pendingReqs, 'heroicon-o-inbox-arrow-down', '#6B2C91'],
            ['Run Days This Week', $runDaysThisWeek, 'heroicon-o-calendar-days', '#2E7D5B'],
            ['Open Seats (Upcoming)', $openSeats, 'heroicon-o-user-plus', '#C79A2E'],
            ['Incubating', $incubating, 'heroicon-o-beaker', '#1F6FB2'],
        ];
    }

    /** People class-complete but with no run booked (the watchlist). */
    public function getWaiting(): array
    {
        $booked = Reservation::whereIn('status', ['requested', 'approved'])->pluck('personnel_id')->filter()->unique()->all();
        return \App\Models\Qualification::with('personnel')
            ->where('workflow_stage', \App\Enums\WorkflowStage::ClassComplete->value)
            ->whereNotIn('personnel_id', $booked)
            ->get()
            ->map(fn ($q) => [
                'qid' => $q->id,
                'personnel_id' => $q->personnel_id,
                'name' => $q->personnel?->full_name ?? 'Unknown',
                'employee_id' => $q->personnel?->employee_id,
                'department' => $q->personnel?->department,
                'runs_required' => $q->runs_required,
                'since' => $q->stage_changed_at?->diffForHumans(),
                'is_requal' => ($q->status instanceof \BackedEnum ? $q->status->value : $q->status) === 'lapsed' || $q->qa_recommendation !== null,
            ])->values()->all();
    }

    /** Book a waiting person into the next available run day (from the Overview watchlist). */
    public function bookWaiting(int $qid): void
    {
        if (! Auth::user()?->hasCapability(\App\Enums\Capability::ManageScheduling)) return;
        $q = \App\Models\Qualification::find($qid);
        if (! $q) return;
        $res = app(\App\Services\AutoScheduler::class)->bookNext($q);
        if ($res) {
            Notification::make()->success()->title('Booked')->body(($q->personnel?->full_name ?? 'Person') . ' booked into the next available run day.')->send();
        } else {
            Notification::make()->warning()->title('No open run day')->body('Add a run day with capacity, then book them.')->send();
        }
    }

    public function bookAllWaiting(): void
    {
        if (! Auth::user()?->hasCapability(\App\Enums\Capability::ManageScheduling)) return;
        $scheduler = app(\App\Services\AutoScheduler::class);
        $booked = 0; $skipped = 0;
        foreach ($this->getWaiting() as $w) {
            $q = \App\Models\Qualification::find($w['qid']);
            if ($q && $scheduler->bookNext($q)) { $booked++; } else { $skipped++; }
        }
        Notification::make()->success()->title('Bulk booking done')
            ->body("Booked {$booked}. Skipped {$skipped} (no open seats).")->send();
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
