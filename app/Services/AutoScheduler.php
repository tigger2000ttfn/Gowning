<?php

namespace App\Services;

use App\Enums\WorkflowStage;
use App\Models\Qualification;
use App\Models\Reservation;
use App\Models\RunSlot;
use App\Models\Setting;
use App\Services\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * Auto-scheduling engine for qualification runs.
 *
 *  - People who are Class Complete (ready) and have no active reservation get booked
 *    into the next available run day with capacity, a configurable lead time out.
 *  - A run day holds at most runs_per_day_capacity people (Settings).
 *  - When a day is full, the person bumps to the next available day.
 *  - When a run day is cancelled, its people are re-booked to the next available day
 *    with capacity, and each gets an in-app + queued-email notification.
 */
class AutoScheduler
{
    public function __construct(protected Notifier $notifier) {}

    public function enabled(): bool { return (bool) Setting::get('auto_schedule', true); }
    public function capacity(): int { return max(1, (int) Setting::get('runs_per_day_capacity', 6)); }
    public function weeksOut(): int { return max(0, (int) Setting::get('auto_schedule_weeks_out', 2)); }

    /** Remaining seats on a run day (capacity minus active reservations). */
    public function seatsLeft(RunSlot $slot): int
    {
        $taken = Reservation::where('run_slot_id', $slot->id)
            ->whereIn('status', ['requested', 'approved'])->count();
        return max(0, $this->capacity() - $taken);
    }

    /** The next open run day (on/after the lead-time date) that still has a seat. */
    public function nextAvailableSlot(?CarbonImmutable $after = null): ?RunSlot
    {
        $earliest = $after ?? CarbonImmutable::now()->addWeeks($this->weeksOut())->startOfDay();
        $slots = RunSlot::where('status', 'open')
            ->whereDate('slot_date', '>=', $earliest->toDateString())
            ->orderBy('slot_date')->orderBy('start_time')
            ->get();
        foreach ($slots as $slot) {
            if ($this->seatsLeft($slot) > 0) {
                return $slot;
            }
        }
        return null;
    }

    /** Book one person into the next available day. Returns the reservation or null. */
    public function bookNext(Qualification $q, ?CarbonImmutable $after = null): ?Reservation
    {
        if (! $q->personnel_id) return null;

        // already actively booked? skip
        $active = Reservation::where('personnel_id', $q->personnel_id)
            ->whereIn('status', ['requested', 'approved'])->exists();
        if ($active) return null;

        $slot = $this->nextAvailableSlot($after);
        if (! $slot) return null;

        $res = Reservation::create([
            'run_slot_id' => $slot->id,
            'personnel_id' => $q->personnel_id,
            'status' => 'approved',           // auto-approved booking
            'requested_at' => now(),
            'decided_at' => now(),
            'notes' => 'Auto-scheduled',
        ]);

        // advance the card to Run Scheduled
        if (in_array($q->workflow_stage?->value, ['class_complete', null], true)) {
            $q->workflow_stage = WorkflowStage::RunScheduled;
            $q->stage_changed_at = now();
            $q->save();
        }

        $this->notifier->toPersonnel(
            $q->personnel,
            'Qualification run scheduled',
            'You are booked for a qualification run on ' . $slot->slot_date->format('M j, Y')
                . ($slot->cleanroom ? ' in ' . $slot->cleanroom : '') . '.',
            \App\Enums\NotificationEvent::RunScheduled
        );

        return $res;
    }

    /** Sweep all Class-Complete people with no active booking into the next available days. */
    public function run(): int
    {
        if (! $this->enabled()) return 0;

        $booked = 0;
        $waiting = Qualification::with('personnel')
            ->where('workflow_stage', WorkflowStage::ClassComplete->value)
            ->get();

        foreach ($waiting as $q) {
            if ($this->bookNext($q)) {
                $booked++;
            }
        }
        return $booked;
    }

    /**
     * Handle a no-show: mark the reservation, alert scheduling, return the person
     * to a bookable state, and auto-rebook them into the next available run day.
     */
    public function handleNoShow(Reservation $res): void
    {
        $res->status = 'no_show';
        $res->decided_at = now();
        $res->save();

        $q = Qualification::with('personnel')->where('personnel_id', $res->personnel_id)->first();
        $person = $res->personnel;

        // return them to ready-to-book (respect class on file)
        if ($q) {
            $q->workflow_stage = $q->class_on_file
                ? \App\Enums\WorkflowStage::ClassComplete
                : \App\Enums\WorkflowStage::ClassPending;
            $q->stage_changed_at = now();
            $q->save();
        }

        // alert the scheduling team
        \App\Services\Notifier::toCapability(
            \App\Enums\Capability::ManageScheduling,
            'No-show recorded',
            ($person?->full_name ?? 'An operator') . ' did not attend their run day. They have been returned for rebooking.'
        );

        // auto-rebook if class is on file and a slot is available
        $rebooked = null;
        if ($q && $q->class_on_file) {
            $rebooked = $this->bookNext($q->fresh());
        }

        // notify the person
        if ($person) {
            $this->notifier->toPersonnel(
                $person,
                'Run day missed',
                $rebooked
                    ? 'You missed your scheduled run. You have been automatically rebooked for the next available run day.'
                    : 'You missed your scheduled run. Please schedule a new run day.',
                \App\Enums\NotificationEvent::RunScheduled
            );
        }

        // automation hook
        \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::StageChanged, [
            'personnel' => $person, 'qualification' => $q,
            'stage' => $q?->workflow_stage instanceof \BackedEnum ? $q->workflow_stage->value : ($q?->workflow_stage),
        ]);
    }

    /** Cancel a run day: reschedule everyone on it to the next available day + notify. */
    public function cancelSlot(RunSlot $slot): int
    {
        $moved = 0;
        $slot->status = 'cancelled';
        $slot->save();

        $reservations = Reservation::with('personnel')
            ->where('run_slot_id', $slot->id)
            ->whereIn('status', ['requested', 'approved'])
            ->get();

        foreach ($reservations as $res) {
            // find next available day AFTER the cancelled date
            $after = CarbonImmutable::parse($slot->slot_date)->addDay()->startOfDay();
            $newSlot = $this->nextAvailableSlot($after);

            if ($newSlot) {
                $res->run_slot_id = $newSlot->id;
                $res->status = 'approved';
                $res->notes = trim(($res->notes ? $res->notes . ' ' : '') . 'Rescheduled from cancelled ' . $slot->slot_date->format('M j') . '.');
                $res->save();
                $this->notifier->toPersonnel(
                    $res->personnel,
                    'Qualification run rescheduled',
                    'Your run day on ' . $slot->slot_date->format('M j') . ' was cancelled. You are re-booked for '
                        . $newSlot->slot_date->format('M j, Y') . '.',
                    \App\Enums\NotificationEvent::RunScheduled
                );
            } else {
                // no day available: hold for manual review
                $res->status = 'requested';
                $res->run_slot_id = null;
                $res->notes = trim(($res->notes ? $res->notes . ' ' : '') . 'Needs rebooking (no open day) after cancelled ' . $slot->slot_date->format('M j') . '.');
                $res->save();
                $this->notifier->toPersonnel(
                    $res->personnel,
                    'Qualification run needs rebooking',
                    'Your run day on ' . $slot->slot_date->format('M j') . ' was cancelled and no open day was available yet. Scheduling will rebook you.',
                    \App\Enums\NotificationEvent::RunScheduled
                );
            }
            $moved++;
        }
        return $moved;
    }
}
