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
    protected static ?string $slug = 'run-scheduler';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Run Scheduler';

    protected string $view = 'filament.pages.run-day-roster';

    public ?string $date = null;
    public string $tab = 'overview';   // overview | schedule | reservations | roster

    // Per-reservation LIMS worklist inputs on the Attendance tab (keyed by reservation id).
    public array $worklists = [];

    /** Deferred attendance intent per reservation: 'present' | 'no_show' | 'rescheduled'. Committed on submit. */
    public array $intent = [];
    /** Per-reservation flag: operator did ALL remaining cycle runs in one day (the common case). */
    public array $performAll = [];

    public function setIntent(int $reservationId, string $status): void
    {
        if (! in_array($status, ['present', 'no_show', 'rescheduled'], true)) return;
        // Tap the active status again to clear it.
        if (($this->intent[$reservationId] ?? null) === $status) {
            unset($this->intent[$reservationId]);
            unset($this->performAll[$reservationId]);
        } else {
            $this->intent[$reservationId] = $status;
            // Attending ALL remaining runs in one visit is the norm, so default it on when marking Present.
            // Uncheck it to record just one run and keep the others scheduled for their own days.
            if ($status === 'present') {
                $this->performAll[$reservationId] = true;
            } else {
                unset($this->performAll[$reservationId]);
            }
        }
    }

    /** Force an EM- prefix and trim; empty stays empty. */
    /**
     * Catalog worklists for the autocomplete datalist: the worklist id, the person it names, and
     * whether it is already linked to another run/qualification. Free-text entry is still allowed;
     * this only suggests.
     */
    public function worklistSuggestions(): array
    {
        return \App\Models\LimsWorklist::query()
            ->where('non_reportable', false)
            ->orderByDesc('catalog_synced_at')->limit(300)->get()
            ->map(function ($w) {
                $linked = \App\Models\Qualification::where('lims_worklist_id', $w->worklist)
                    ->orWhereHas('runs', fn ($q) => $q->where('lims_worklist_id', $w->worklist))->exists();
                $who = trim((string) $w->personnel);
                return [
                    'worklist' => $w->worklist,
                    'label' => $w->worklist . ($who ? '  -  ' . $who : '') . ($linked ? '  (already linked)' : ''),
                ];
            })->all();
    }

    /** Does this worklist's LIMS person match the reservation's person? Returns null if unknown. */
    protected function worklistPersonMatches(string $worklist, int $personnelId): ?bool
    {
        $wl = \App\Models\LimsWorklist::findByWorklist($worklist);
        if (! $wl) return null;
        $login = strtoupper(trim((string) $wl->personnel));
        if ($login === '') return null;
        $person = \App\Models\Personnel::find($personnelId);
        if (! $person) return null;
        if (strtoupper(trim((string) $person->lims_username)) === $login) return true;
        // name fallback: first-initial + last name (e.g. RRODRIGUEZ)
        $expected = strtoupper(substr(trim((string) $person->first_name), 0, 1) . trim((string) $person->last_name));
        return $expected === $login;
    }

    public function normalizeWorklist(?string $v): string
    {
        $v = trim((string) $v);
        if ($v === '') return '';
        $v = strtoupper($v);
        if (! str_starts_with($v, 'EM-')) {
            // allow them to type just the number, or 'EM 123', etc.
            $v = 'EM-' . ltrim(preg_replace('/^EM[-\s]*/i', '', $v), '-');
        }
        return $v;
    }

    /**
     * Save a person's LIMS worklist. Each person has ONE unique worklist for their whole
     * qualification cycle (all 3 or 1 runs batch onto it in LIMS), so it lives on the
     * qualification and propagates to this reservation and every run in the current cycle.
     */
    public function saveWorklist(int $reservationId): void
    {
        $res = Reservation::find($reservationId);
        if (! $res) return;
        $wl = $this->normalizeWorklist($this->worklists[$reservationId] ?? '');
        $res->update(['lims_worklist_id' => $wl]);
        $this->worklists[$reservationId] = preg_replace('/^EM-/i', '', $wl);

        // Warn (do not block) if the worklist's LIMS person does not match this reservation's person.
        if ($wl !== '') {
            $match = $this->worklistPersonMatches($wl, $res->personnel_id);
            if ($match === false) {
                $cat = \App\Models\LimsWorklist::findByWorklist($wl);
                Notification::make()->warning()->title('Worklist Person Mismatch')
                    ->body($wl . ' is for ' . ($cat?->personnel ?: 'another person') . ' in LIMS, not '
                        . (\App\Models\Personnel::find($res->personnel_id)?->full_name ?? 'this person') . '. Linked anyway. Confirm this is correct.')
                    ->persistent()->send();
            }
        }

        // store on the qualification (the per-person cycle worklist) and stamp all cycle runs
        $q = \App\Models\Qualification::currentFor($res->personnel_id);
        if ($q) {
            $q->lims_worklist_id = $wl;
            $q->save();
            $runsQuery = \App\Models\QualificationRun::where('personnel_id', $res->personnel_id);
            if ($q->cycle_started_at) {
                $runsQuery->whereDate('run_date', '>=', $q->cycle_started_at);
            }
            $runsQuery->update(['lims_worklist_id' => $wl]);
        }
    }


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
        // Only staff designated as qualified for run sampling are assignable to run days.
        return \App\Models\User::where('is_active', true)
            ->where('can_sample', true)
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
        $this->detailSlotId = null;
        Notification::make()->success()->title('Run Day Cancelled')
            ->body($moved > 0 ? "{$moved} reservation(s) were rescheduled/notified." : 'No bookings to move.')->send();
    }

    // ===== Run-day detail / reschedule modal =====
    public ?int $detailSlotId = null;
    public array $editSlot = [];

    // Person detail card (mirrors the Status Board / Class Kanban card), keyed on personnel.
    public ?array $personDetail = null;
    public function closePersonDetail(): void { $this->personDetail = null; }

    public function showPersonDetail(?int $personnelId): void
    {
        if (! $personnelId) { $this->personDetail = null; return; }
        $p = \App\Models\Personnel::find($personnelId);
        if (! $p) { $this->personDetail = null; return; }
        $q = \App\Models\Qualification::currentFor($p->id);

        $runs = \App\Models\QualificationRun::where('personnel_id', $p->id)
            ->latest('run_date')->latest('id')->limit(5)->get()
            ->map(fn ($r) => [
                'date' => $r->run_date?->gmp(),
                'result' => ucfirst($r->result instanceof \BackedEnum ? $r->result->value : (string) $r->result),
                'worklist' => $r->lims_worklist_id,
            ])->all();

        // class taken / QA approved signal from the latest classroom enrollment
        $enroll = \App\Models\ClassEnrollment::where('personnel_id', $p->id)
            ->latest('id')->first();
        $classStatus = $enroll ? ucwords(str_replace('_', ' ', (string) ($enroll->status instanceof \BackedEnum ? $enroll->status->value : $enroll->status))) : null;
        // class completion date: from class_on_file_date, else the latest class completion record.
        $classCompletion = \App\Models\ClassCompletion::where('personnel_id', $p->id)
            ->latest('completion_date')->first();
        $classDate = $q?->class_on_file_date?->gmp()
            ?? $classCompletion?->completion_date?->gmp();

        $this->personDetail = [
            'name' => $p->full_name,
            'employee_id' => $p->employee_id,
            'department' => $p->department,
            'job_title' => $p->job_title,
            'email' => $p->email,
            'stage' => $q?->workflow_stage ? \App\Models\WorkflowStatus::labelFor('run', $q->workflow_stage->value, $q->workflow_stage->label()) : null,
            'status' => $q ? ucwords(str_replace('_', ' ', (string) ($q->status instanceof \BackedEnum ? $q->status->value : $q->status))) : null,
            'type' => $q ? $q->sessionLabel() : null,
            'runs' => $q ? ((int) $q->runs_completed . ' / ' . (int) $q->runs_required) : null,
            'due' => $q?->due_date?->gmp(),
            'class_on_file' => (bool) ($q?->class_on_file),
            'class_status' => $classStatus,
            'class_date' => $classDate,
            'recent_runs' => $runs,
            'view_url' => $q
                ? \App\Filament\Admin\Resources\QualificationResource::getUrl('view', ['record' => $q->id])
                : \App\Filament\Admin\Resources\PersonnelResource::getUrl('edit', ['record' => $p->id]),
        ];
    }

    public function openRunDayDetail(int $slotId): void
    {
        $slot = RunSlot::find($slotId);
        if (! $slot) return;
        $this->detailSlotId = $slotId;
        $this->editSlot = [
            'slot_date' => $slot->slot_date?->toDateString(),
            'start_time' => $slot->start_time ? \Illuminate\Support\Carbon::parse($slot->start_time)->format('H:i') : null,
            'end_time' => $slot->end_time ? \Illuminate\Support\Carbon::parse($slot->end_time)->format('H:i') : null,
            'cleanroom' => $slot->cleanroom,
            'capacity' => $slot->capacity,
            'assigned_analyst_id' => $slot->assigned_analyst_id,
        ];
    }
    public function closeRunDayDetail(): void { $this->detailSlotId = null; }

    public function saveRunDayDetail(): void
    {
        if (! Auth::user()?->hasCapability(\App\Enums\Capability::ManageScheduling)) return;
        $slot = RunSlot::find($this->detailSlotId);
        if (! $slot) return;
        if ($slot->attendance_submitted_at) {
            Notification::make()->warning()->title('Locked')->body('Attendance has been submitted for this run day.')->send();
            return;
        }
        // Changing the date reschedules the run day; booked reservations stay attached and move with it.
        $slot->update([
            'slot_date' => $this->editSlot['slot_date'] ?: $slot->slot_date,
            'start_time' => $this->editSlot['start_time'] ?: null,
            'end_time' => $this->editSlot['end_time'] ?: null,
            'cleanroom' => $this->editSlot['cleanroom'] ?: $slot->cleanroom,
            'capacity' => $this->editSlot['capacity'] ?: $slot->capacity,
            'assigned_analyst_id' => $this->editSlot['assigned_analyst_id'] ?: null,
        ]);
        $this->closeRunDayDetail();
        Notification::make()->success()->title('Run Day Updated')->body('Everyone booked on this day moves with it.')->send();
    }

    /** Booked reservations on a run day, for the detail modal. */
    public function slotBookings(int $slotId): array
    {
        $slot = RunSlot::with('reservations.personnel')->find($slotId);
        if (! $slot) return [];
        return $slot->reservations
            ->filter(fn ($r) => ! in_array(($r->status instanceof \BackedEnum ? $r->status->value : $r->status), ['rejected', 'completed'], true))
            ->map(fn ($r) => $r->personnel?->full_name ?? 'Unknown')
            ->values()->all();
    }

    public function viewRoster(string $date): void
    {
        $this->date = $date;
        $this->tab = 'roster';
    }

    /** Human label for a person's current qualification cycle, for the roster (single source of truth on the model). */
    public function runContext(?\App\Models\Qualification $q): array
    {
        if (! $q) return ['label' => 'Qualification', 'tag' => '', 'pill' => 'gqs-pill-purple', 'retrain' => false];
        return [
            'label' => $q->sessionLabel(),
            'tag' => $q->sessionTag(),
            'pill' => $q->sessionPill(),
            'retrain' => $q->needsRetrainingFirst(),
        ];
    }

    // ===== Reservations tab =====
    public bool $showAddRes = false;
    public ?int $addResSlotId = null;
    public ?int $addResPersonnelId = null;
    public string $resStatusFilter = '';
    public bool $showCancelled = false;

    public function openSlotsForBooking(): array
    {
        $scheduler = app(\App\Services\AutoScheduler::class);
        return RunSlot::where('status', 'open')
            ->whereDate('slot_date', '>=', now()->toDateString())
            ->orderBy('slot_date')->orderBy('start_time')->get()
            ->filter(fn ($s) => $scheduler->seatsLeft($s) > 0)
            ->mapWithKeys(fn ($s) => [$s->id => $s->slot_date->gmp() . ' · ' . ($s->cleanroom ?: 'Run Day')
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
            // Cancelled/rejected (and, unless asked, rescheduled) bookings are hidden by default - the toggle
            // brings them back when you want the full history.
            ->when(! $this->showCancelled && $this->resStatusFilter === '', fn ($q) => $q->whereNotIn('status', ['cancelled', 'rejected', 'rescheduled']))
            ->get()
            ->sortBy(fn ($r) => $r->runSlot?->slot_date)
            ->groupBy(fn ($r) => $r->runSlot?->slot_date?->format('Y-m-d') ?? 'unscheduled')
            ->map(fn ($group, $day) => [
                'day' => $day === 'unscheduled' ? 'Unscheduled' : \Illuminate\Support\Carbon::parse($day)->gmpL(),
                'date' => $day,
                'rows' => $group->map(fn ($r) => [
                    'id' => $r->id,
                    'personnel_id' => $r->personnel_id,
                    'name' => $r->personnel?->full_name ?? 'Unknown',
                    'employee_id' => $r->personnel?->employee_id,
                    'cleanroom' => $r->runSlot?->cleanroom,
                    'time' => $r->runSlot?->start_time ? \Illuminate\Support\Carbon::parse($r->runSlot->start_time)->format('H:i') : null,
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
        $q = \App\Models\Qualification::currentFor($this->addResPersonnelId);
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
        $q = \App\Models\Qualification::currentFor($r->personnel_id);
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

    // ----- Move / edit a reservation to a different run day -----
    public bool $showMoveRes = false;
    public ?int $moveResId = null;
    public ?int $moveResSlotId = null;
    public ?string $moveResName = null;
    /** Special one-off (VIP) run day created inline from the move modal. */
    public bool $moveSpecial = false;
    public ?string $moveSpecialDate = null;
    public ?string $moveSpecialCleanroom = null;
    public ?int $moveSpecialAnalystId = null;

    public function openMoveRes(int $id): void
    {
        $r = Reservation::with('personnel')->find($id);
        if (! $r) return;
        $this->moveResId = $id;
        $this->moveResSlotId = $r->run_slot_id;
        $this->moveResName = $r->personnel?->full_name ?? 'Reservation';
        $this->showMoveRes = true;
    }

    public function moveReservation(): void
    {
        if (! Auth::user()?->hasCapability(\App\Enums\Capability::ManageScheduling)) return;
        $r = Reservation::find($this->moveResId);
        if (! $r) { $this->showMoveRes = false; return; }

        // Special one-off date (the VIP case): create a dedicated run day and move the booking
        // onto it. Optional analyst means a different analyst can own that day.
        if ($this->moveSpecial) {
            if (! $this->moveSpecialDate) {
                Notification::make()->warning()->title('Pick a date for the special run day')->send();
                return;
            }
            $special = RunSlot::create([
                'slot_date' => \Illuminate\Support\Carbon::parse($this->moveSpecialDate)->toDateString(),
                'cleanroom' => $this->moveSpecialCleanroom ?: null,
                'capacity' => 1,
                'assigned_analyst_id' => $this->moveSpecialAnalystId ?: null,
                'status' => 'open',
                'notes' => 'Special one-off run day (created from a reschedule).',
            ]);
            $r->update(['run_slot_id' => $special->id, 'status' => 'approved']);
            $when = \Illuminate\Support\Carbon::parse($this->moveSpecialDate)->format('d M Y');
            $this->reset(['showMoveRes', 'moveSpecial', 'moveSpecialDate', 'moveSpecialCleanroom', 'moveSpecialAnalystId']);
            Notification::make()->success()->title('Moved to a special run day')
                ->body($when . ($special->cleanroom ? ' · ' . $special->cleanroom : '') . '.')->send();
            return;
        }

        if (! $this->moveResSlotId) { $this->showMoveRes = false; return; }
        $slot = RunSlot::find($this->moveResSlotId);
        $scheduler = app(\App\Services\AutoScheduler::class);
        // allow move if the target has a seat (or it is the same slot)
        if ($slot && $r->run_slot_id !== $slot->id && $scheduler->seatsLeft($slot) <= 0) {
            Notification::make()->warning()->title('That run day is full')->send();
            return;
        }
        $r->update(['run_slot_id' => $this->moveResSlotId, 'status' => 'approved']);
        $this->showMoveRes = false;
        Notification::make()->success()->title('Reservation moved')->send();
    }

    public function cancelReservation(int $id): void
    {
        if (! Auth::user()?->hasCapability(\App\Enums\Capability::ManageScheduling)) return;
        $r = Reservation::find($id);
        if (! $r) return;
        $personId = $r->personnel_id;
        $r->delete();
        // if the person was Run Scheduled only because of this, drop them back so they can rebook
        $q = \App\Models\Qualification::currentFor($personId);
        if ($q && $q->workflow_stage === \App\Enums\WorkflowStage::RunScheduled) {
            $stillBooked = Reservation::where('personnel_id', $personId)->whereIn('status', ['requested', 'approved'])->exists();
            if (! $stillBooked) {
                $q->workflow_stage = $q->class_on_file ? \App\Enums\WorkflowStage::ClassComplete : \App\Enums\WorkflowStage::ClassPending;
                $q->stage_changed_at = now();
                $q->save();
            }
        }
        Notification::make()->success()->title('Reservation removed')->send();
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
            ['Awaiting Scheduling', $waiting, 'heroicon-o-clock', 'magenta'],
            ['Pending Requests', $pendingReqs, 'heroicon-o-inbox-arrow-down', 'purple'],
            ['Run Days This Week', $runDaysThisWeek, 'heroicon-o-calendar-days', 'green'],
            ['Open Seats (Upcoming)', $openSeats, 'heroicon-o-user-plus', 'gold'],
            ['Incubating', $incubating, 'heroicon-o-beaker', 'charcoal'],
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
    /** Roster attendance: reschedule a booked person (returns them for rebooking). */
    public function rosterReschedule(int $reservationId): void
    {
        if (! Auth::user()?->hasCapability(\App\Enums\Capability::ManageScheduling)) return;
        $res = Reservation::find($reservationId);
        if (! $res) return;
        // Detach from this day (leaves the roster) and let the scheduler self-find the next open day.
        $res->update(['status' => 'rescheduled']);
        $q = \App\Models\Qualification::currentFor($res->personnel_id);
        $rebooked = $q ? app(\App\Services\AutoScheduler::class)->bookNext($q->fresh()) : null;
        if ($rebooked) {
            Notification::make()->success()->title('Rescheduled')->body('Re-booked into the next open run day.')->send();
        } else {
            Notification::make()->warning()->title('Rescheduled, awaiting a day')
                ->body('No open run day right now. They are in the unscheduled queue and will be booked automatically when a day opens.')->send();
        }
    }

    /** Roster attendance: no-show (returns them for rebooking via the scheduler). */
    public function rosterNoShow(int $reservationId): void
    {
        if (! Auth::user()?->hasCapability(\App\Enums\Capability::ManageScheduling)) return;
        $res = Reservation::find($reservationId);
        if ($res) app(\App\Services\AutoScheduler::class)->handleNoShow($res);
        Notification::make()->success()->title('Marked no-show')->send();
    }

    /**
     * Submit a whole run day: verify every still-scheduled attendee has a LIMS worklist,
     * then mark them present (performed) in one action and lock the day. This is the gate
     * that checks the worklist entry was done before the run is recorded.
     */
    public function submitRunDay(int $slotId): void
    {
        $slot = RunSlot::with('reservations.personnel')->find($slotId);
        if (! $slot) return;
        if ($slot->attendance_submitted_at) {
            Notification::make()->warning()->title('Already Submitted')->send();
            return;
        }

        // Only people still scheduled (not yet present / no-show / rescheduled) need processing.
        $pending = $slot->reservations->filter(function ($r) {
            $st = $r->status instanceof \BackedEnum ? $r->status->value : $r->status;
            return ! in_array($st, ['completed', 'no_show', 'rescheduled'], true);
        });

        // Everyone must have an intent marked, and every Present must have a worklist.
        $unmarked = [];
        $missing = [];
        foreach ($pending as $r) {
            $intent = $this->intent[$r->id] ?? null;
            if (! $intent) { $unmarked[] = $r->personnel?->full_name ?? ('#' . $r->id); continue; }
            if ($intent === 'present') {
                $wl = $this->normalizeWorklist($this->worklists[$r->id] ?? $r->lims_worklist_id ?? '');
                if ($wl === '') $missing[] = $r->personnel?->full_name ?? ('#' . $r->id);
            }
        }
        if (! empty($unmarked)) {
            Notification::make()->warning()->title('Mark Everyone First')
                ->body('Mark Present, No-Show, or Reschedule for: ' . implode(', ', $unmarked) . ' before submitting.')
                ->persistent()->send();
            return;
        }
        if (! empty($missing)) {
            Notification::make()->warning()->title('LIMS Worklist Missing')
                ->body('Enter a worklist for: ' . implode(', ', $missing) . ' before submitting.')
                ->persistent()->send();
            return;
        }

        // Commit each person's marked intent.
        $blocked = [];
        foreach ($pending as $r) {
            $intent = $this->intent[$r->id] ?? null;
            if ($intent === 'present') {
                $q = \App\Models\Qualification::currentFor($r->personnel_id);
                if (! $q || ! $q->class_on_file) { $blocked[] = $r->personnel?->full_name ?? ('#' . $r->id); continue; }
                $runsToday = 1;
                if (! empty($this->performAll[$r->id])) {
                    $required = max(1, (int) ($q->runs_required ?? 1));
                    $done = app(\App\Services\RunCycleAdvancer::class)->cycleRuns($q)->count();
                    $runsToday = max(1, $required - $done);
                }
                $this->markPerformed($r->id, $runsToday);
                unset($this->performAll[$r->id]);
            } elseif ($intent === 'no_show') {
                $this->rosterNoShow($r->id);
            } elseif ($intent === 'rescheduled') {
                $this->rosterReschedule($r->id);
            }
            unset($this->intent[$r->id]);
        }
        if (! empty($blocked)) {
            Notification::make()->warning()->title('Some Not Classroom-Complete')
                ->body('Not submitted (classroom not QA-approved): ' . implode(', ', $blocked) . '. Others were submitted.')
                ->persistent()->send();
        }

        $slot->attendance_submitted_at = now();
        $slot->attendance_submitted_by = Auth::id();
        $slot->save();

        Notification::make()->success()->title('Run Day Submitted')
            ->body('Attendance committed and the day is locked.')->send();
    }

    /** Reopen a submitted run day to correct attendance. */
    public function reopenRunDay(int $slotId): void
    {
        $slot = RunSlot::find($slotId);
        if (! $slot) return;
        $slot->attendance_submitted_at = null;
        $slot->attendance_submitted_by = null;
        $slot->save();
        Notification::make()->success()->title('Run day reopened')->send();
    }

    public function markPerformed(int $reservationId, int $runsToday = 1): void
    {
        $res = Reservation::with('personnel')->find($reservationId);
        if (! $res || ! $res->personnel) {
            Notification::make()->danger()->title('Reservation not found')->send();
            return;
        }
        // GATE: classroom training must be QA-completed (class on file) before a person can
        // actually perform a qualification run. They are allowed to book a slot beforehand,
        // but cannot be marked present until QA has signed off the classroom training.
        $q = \App\Models\Qualification::currentFor($res->personnel_id);
        if (! $q || ! $q->class_on_file) {
            Notification::make()->warning()->title('Classroom training not completed')
                ->body($res->personnel->full_name . ' cannot perform a run until their gowning class is QA-approved (Completed) on the Class Board. They may stay booked until then.')
                ->persistent()->send();
            return;
        }
        // Require a LIMS worklist (EM-...) before the run can be marked performed.
        $wl = $this->normalizeWorklist($this->worklists[$reservationId] ?? $res->lims_worklist_id ?? '');
        if ($wl === '') {
            Notification::make()->warning()->title('LIMS worklist required')
                ->body('Enter the EM- worklist for this person before marking present.')->send();
            return;
        }
        $res->update(['status' => 'completed', 'lims_worklist_id' => $wl]);
        // Record the run(s) as PENDING (result unknown until incubation plates are read).
        // runsToday > 1 means the operator did several consecutive runs in one day; the SOP
        // allows all of an initial cycle's runs on a single day, each its own sample/incubation.
        $runsToday = max(1, $runsToday);
        for ($i = 0; $i < $runsToday; $i++) {
            app(\App\Services\QualificationEngine::class)
                ->recordRun($res->personnel, \App\Enums\RunResult::Pending, [
                    'run_date' => now()->toDateString(),
                    'recorded_by' => Auth::id(),
                    'lims_worklist_id' => $wl,
                ]);
            // stamp the just-created run's incubation start (= performed date)
            $newRun = \App\Models\QualificationRun::where('personnel_id', $res->personnel_id)
                ->latest('run_date')->latest('id')->first();
            if ($newRun && ! $newRun->incubation_started_at) {
                $newRun->incubation_started_at = now();
                $newRun->save();
            }
        }
        $q = \App\Models\Qualification::currentFor($res->personnel_id);
        // Holistic, multi-run aware: the person moves to Incubating now; they only advance
        // to results once the LAST required run's plates clear (handled by RunCycleAdvancer).
        if ($q) {
            $q->workflow_stage = \App\Enums\WorkflowStage::Incubating;
            $q->stage_changed_at = now();
            $q->save();
            app(\App\Services\RunCycleAdvancer::class)->advance($q->fresh());
        }
        $required = max(1, (int) ($q->runs_required ?? 1));
        $performed = $q ? app(\App\Services\RunCycleAdvancer::class)->cycleRuns($q->fresh())->count() : 1;
        $days = (int) \App\Models\Setting::get('incubation_days', 8);
        $msg = $performed < $required
            ? "Run {$performed} of {$required} performed, incubating. " . ($required - $performed) . ' more run(s) needed.'
            : "Final run performed, all {$required} run(s) incubating. Plates ready in {$days} days.";
        Notification::make()->success()->title('Run performed, incubation started')
            ->body(($res->personnel->full_name ?? 'Operator') . ': ' . $msg)->send();
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

        $q = \App\Models\Qualification::currentFor($res->personnel_id);
        // Release results for ALL pending runs in this cycle (plates all came in together).
        $adv = app(\App\Services\RunCycleAdvancer::class);
        $cycleRuns = $q ? $adv->cycleRuns($q) : collect();
        $pendingRuns = $cycleRuns->filter(fn ($r) => $r->result === \App\Enums\RunResult::Pending);
        if ($pendingRuns->isEmpty() && $cycleRuns->isNotEmpty()) {
            $pendingRuns = collect([$cycleRuns->last()]); // fallback to latest
        }
        $run = $cycleRuns->last();
        foreach ($pendingRuns as $r) {
            $r->lims_worklist_id = $r->lims_worklist_id ?: $worklist;
            $r->results_entered_at = now();
            $r->results_released_at = now();
            $r->result = $overall === 'fail' ? \App\Enums\RunResult::Fail : \App\Enums\RunResult::Pass;
            $r->save();
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
