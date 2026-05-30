<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Capability;
use App\Models\Reservation;
use App\Models\RunSlot;
use App\Services\AutoScheduler;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Run Reservations - the end-user-facing companion to the Run Scheduler, mirroring
 * Class Reservations: a clean per-day booking view where you Move, Reschedule, or Cancel
 * a person's run booking. Attendance and approval live on the Run Scheduler; the internal
 * Run Reservation Board (different workflow) is kept separately.
 */
class RunReservations extends Page
{
    protected static function allowed(): bool
    {
        $u = Auth::user();
        if (! $u) return false;
        return $u->hasCapability(Capability::ManageScheduling)
            || $u->hasCapability(Capability::ViewQualifications)
            || static::viewerPersonnel() !== null;
    }
    public static function canAccessNavigation(): bool { return static::allowed(); }
    public static function shouldRegisterNavigation(): bool { return static::allowed(); }
    public static function canViewAny(): bool { return static::allowed(); }

    /** True when the viewer can manage everyone's bookings (vs self-serve only). */
    public function viewerIsManager(): bool
    {
        $u = Auth::user();
        return (bool) ($u && ($u->hasCapability(Capability::ManageScheduling) || $u->hasCapability(Capability::ViewQualifications)));
    }

    /** The personnel record for the current viewer (operator self-serve), if any. */
    public static function viewerPersonnel(): ?\App\Models\Personnel
    {
        $u = Auth::user();
        if (! $u) return null;
        return \App\Models\Personnel::where('user_id', $u->id)->orWhere('email', $u->email)->first();
    }

    protected function mayManageReservation(?Reservation $r): bool
    {
        if (! $r) return false;
        if ($this->viewerIsManager()) return true;
        return $r->personnel_id && $r->personnel_id === static::viewerPersonnel()?->id;
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Run Reservations';
    protected static string|\UnitEnum|null $navigationGroup = 'Qualifications';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Run Reservations';
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.run-reservations';

    protected function scheduler(): AutoScheduler { return app(AutoScheduler::class); }

    /** Reservations grouped by run day, booking-management view. */
    public function getGroupedByDay(): array
    {
        $manager = $this->viewerIsManager();
        $mineId = $manager ? null : (static::viewerPersonnel()?->id);
        return Reservation::with(['personnel', 'runSlot'])
            ->whereHas('runSlot')
            ->whereIn('status', ['requested', 'approved'])
            ->when(! $manager, fn ($q) => $q->where('personnel_id', $mineId))
            ->get()
            ->sortBy(fn ($r) => $r->runSlot?->slot_date)
            ->groupBy(fn ($r) => $r->runSlot?->slot_date?->format('Y-m-d') ?? 'unscheduled')
            ->map(fn ($group, $day) => [
                'title' => ($day === 'unscheduled' ? 'Unscheduled' : \Illuminate\Support\Carbon::parse($day)->format('l, M j, Y'))
                    . ' · ' . ($group->first()->runSlot?->cleanroom ?? ''),
                'rows' => $group->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->personnel?->full_name ?? 'Unknown',
                    'employee_id' => $r->personnel?->employee_id,
                    'status' => $r->status instanceof \BackedEnum ? $r->status->value : $r->status,
                ])->values()->all(),
            ])->values()->all();
    }

    /** Open future run days a booking can be moved to (id => label). */
    public function openSlots(): array
    {
        $sch = $this->scheduler();
        return RunSlot::where('status', 'open')
            ->whereDate('slot_date', '>=', now()->toDateString())
            ->orderBy('slot_date')->get()
            ->filter(fn ($s) => $sch->seatsLeft($s) > 0)
            ->mapWithKeys(fn ($s) => [$s->id => $s->slot_date->format('M j, Y') . ', ' . $s->cleanroom
                . ($s->start_time ? ' (' . \Illuminate\Support\Carbon::parse($s->start_time)->format('g:i A') . ')' : '')])
            ->all();
    }

    // ---- Booking management: move / reschedule / cancel ----

    public bool $showMove = false;
    public ?int $moveReservationId = null;
    public ?int $moveSlotId = null;
    public string $moveName = '';

    public array $confirm = [];
    public function askConfirm(string $method, $arg, string $title, string $body, ?string $confirmLabel = null, bool $danger = false): void
    {
        $this->confirm = compact('method', 'arg', 'title', 'body', 'danger') + ['label' => $confirmLabel ?? 'Confirm'];
    }
    public function runConfirm(): void
    {
        $m = $this->confirm['method'] ?? null;
        $allowed = ['reschedule', 'cancelBooking'];
        if ($m && in_array($m, $allowed, true)) {
            $this->{$m}($this->confirm['arg']);
        }
        $this->confirm = [];
    }
    public function cancelConfirm(): void { $this->confirm = []; }

    public function openMove(int $reservationId): void
    {
        $r = Reservation::with('personnel')->find($reservationId);
        if (! $this->mayManageReservation($r)) return;
        $this->moveReservationId = $reservationId;
        $this->moveName = $r->personnel?->full_name ?? 'Trainee';
        $this->moveSlotId = null;
        $this->showMove = true;
    }

    public function move(): void
    {
        $r = Reservation::find($this->moveReservationId);
        if (! $r || ! $this->moveSlotId) {
            Notification::make()->warning()->title('Pick A Run Day')->send();
            return;
        }
        if (! $this->mayManageReservation($r)) { Notification::make()->danger()->title('Not Your Booking')->send(); return; }
        $slot = RunSlot::find($this->moveSlotId);
        if (! $slot) return;
        if ($this->scheduler()->seatsLeft($slot) <= 0) {
            Notification::make()->warning()->title('Run Day Full')->send();
            return;
        }
        $r->update(['run_slot_id' => $slot->id, 'status' => 'approved', 'notes' => 'Moved']);
        $this->showMove = false;
        Notification::make()->success()->title('Booking Moved')->send();
    }

    /** Move to the next available run day automatically. */
    public function reschedule(int $reservationId): void
    {
        $r = Reservation::find($reservationId);
        if (! $this->mayManageReservation($r)) { Notification::make()->danger()->title('Not Your Booking')->send(); return; }
        $slot = $this->scheduler()->nextAvailableSlot();
        if (! $slot) {
            Notification::make()->warning()->title('No Open Run Day')->body('No run day with space was found.')->send();
            return;
        }
        $r->update(['run_slot_id' => $slot->id, 'status' => 'approved', 'notes' => 'Rescheduled']);
        Notification::make()->success()->title('Rescheduled')
            ->body('Moved to ' . $slot->slot_date->format('M j, Y') . '.')->send();
    }

    public function cancelBooking(int $reservationId): void
    {
        $r = Reservation::find($reservationId);
        if (! $this->mayManageReservation($r)) { Notification::make()->danger()->title('Not Your Booking')->send(); return; }
        // ReservationStatus has no 'cancelled'; 'rejected' removes it from the active booking list.
        $r->update(['status' => 'rejected']);
        Notification::make()->success()->title('Booking Cancelled')->send();
    }
}
