<?php

namespace App\Filament\Admin\Pages;

use App\Models\Reservation;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ReservationBoard extends Page
{
    public static function canAccessNavigation(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    public static function shouldRegisterNavigation(): bool
    {
        return false; // merged into Run Scheduler > Reservations tab
    }
    public static function canViewAny(): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        return (bool) ($u && $u->hasCapability(\App\Enums\Capability::ManageScheduling));
    }
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Run Reservation Board';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'Qualification Run Reservations';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.reservation-board';

    // Filters / search
    public string $search = '';
    public string $statusFilter = '';

    // Manual add-reservation modal state
    public bool $showAdd = false;
    public ?int $addSlotId = null;
    public ?int $addPersonnelId = null;

    /** Open run slots for the add-reservation dropdown. */
    public function openSlots(): array
    {
        return \App\Models\RunSlot::where('status', 'open')
            ->whereDate('slot_date', '>=', now()->toDateString())
            ->orderBy('slot_date')->get()
            ->mapWithKeys(fn ($s) => [$s->id => $s->slot_date->gmp() . ', ' . $s->cleanroom
                . ($s->start_time ? ' (' . \Illuminate\Support\Carbon::parse($s->start_time)->format('H:i') . ')' : '')])
            ->all();
    }

    /** People who can be booked (active personnel), searchable in the modal. */
    public function bookablePersonnel(): array
    {
        return \App\Models\Personnel::where('is_active', true)
            ->orderBy('last_name')->orderBy('first_name')->get()
            ->mapWithKeys(fn ($p) => [$p->id => $p->full_name . ' (' . $p->employee_id . ')'])
            ->all();
    }

    public function addReservation(): void
    {
        if (! $this->addSlotId || ! $this->addPersonnelId) {
            \Filament\Notifications\Notification::make()->danger()->title('Pick a person and a run day')->send();
            return;
        }
        $slot = \App\Models\RunSlot::find($this->addSlotId);
        $scheduler = app(\App\Services\AutoScheduler::class);
        if ($slot && $scheduler->seatsLeft($slot) <= 0) {
            \Filament\Notifications\Notification::make()->warning()->title('That run day is full')
                ->body('Pick another day or raise capacity.')->send();
            return;
        }
        // avoid double-booking the same active reservation
        $exists = Reservation::where('personnel_id', $this->addPersonnelId)
            ->whereIn('status', ['requested', 'approved'])->exists();
        Reservation::create([
            'run_slot_id' => $this->addSlotId,
            'personnel_id' => $this->addPersonnelId,
            'status' => 'approved',
            'requested_at' => now(),
            'decided_at' => now(),
            'notes' => 'Added by analyst',
        ]);
        // advance the person to Run Scheduled if they were waiting
        $q = \App\Models\Qualification::where('personnel_id', $this->addPersonnelId)->first();
        if ($q && in_array($q->workflow_stage?->value, ['class_complete', 'class_pending', null], true)) {
            $q->workflow_stage = \App\Enums\WorkflowStage::RunScheduled;
            $q->stage_changed_at = now();
            $q->save();
        }
        $this->showAdd = false;
        $this->addPersonnelId = null;
        \Filament\Notifications\Notification::make()->success()->title('Reservation added')
            ->body($exists ? 'Note: this person already had an active reservation.' : '')->send();
    }

    /** Reservations grouped by run-slot day, for the per-day list view. */
    public function getGroupedByDay(): array
    {
        return Reservation::with(['personnel', 'runSlot'])
            ->whereHas('runSlot')
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', fn ($q) => $q->whereHas('personnel', fn ($p) =>
                $p->where('first_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('last_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('employee_id', 'ilike', '%' . $this->search . '%')))
            ->get()
            ->sortBy(fn ($r) => $r->runSlot?->slot_date)
            ->groupBy(fn ($r) => $r->runSlot?->slot_date?->format('Y-m-d') ?? 'unscheduled')
            ->map(fn ($group, $day) => [
                'day' => $day === 'unscheduled' ? 'Unscheduled' : \Illuminate\Support\Carbon::parse($day)->gmpL(),
                'rows' => $group->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->personnel?->full_name ?? 'Unknown',
                    'employee_id' => $r->personnel?->employee_id,
                    'cleanroom' => $r->runSlot?->cleanroom,
                    'time' => $r->runSlot?->start_time ? \Illuminate\Support\Carbon::parse($r->runSlot->start_time)->format('H:i') : null,
                    'status' => $r->status instanceof \BackedEnum ? $r->status->value : $r->status,
                ])->values()->all(),
            ])->values()->all();
    }

    public array $lanes = [
        'requested' => 'Requested',
        'approved'  => 'Approved',
        'completed' => 'Completed',
        'no_show'   => 'No-Show',
    ];

    public function getColumns(): array
    {
        $out = [];
        foreach ($this->lanes as $key => $label) {
            $out[$key] = [
                'label' => $label,
                'cards' => Reservation::with(['personnel', 'runSlot'])
                    ->where('status', $key)
                    ->latest('updated_at')
                    ->get()
                    ->map(fn ($r) => [
                        'id' => $r->id,
                        'name' => $r->personnel?->full_name ?? 'Unknown',
                        'employee_id' => $r->personnel?->employee_id,
                        'slot' => $r->runSlot?->cleanroom,
                        'date' => $r->runSlot?->slot_date?->gmp(),
                    ])->all(),
            ];
        }
        return $out;
    }

    /** Called by drag-drop to move a card to a new lane (status). */
    public function moveCard(int $id, string $toStatus): void
    {
        if (! array_key_exists($toStatus, $this->lanes)) {
            return;
        }
        $r = Reservation::find($id);
        if (! $r) {
            return;
        }
        if ($toStatus === 'no_show') {
            app(\App\Services\AutoScheduler::class)->handleNoShow($r);
            return;
        }
        $r->status = $toStatus;
        if (in_array($toStatus, ['approved', 'completed'])) {
            $r->decided_by = Auth::id();
            $r->decided_at = now();
        }
        $r->save();
    }
}
