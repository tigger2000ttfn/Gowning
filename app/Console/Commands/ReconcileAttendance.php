<?php

namespace App\Console\Commands;

use App\Models\LimsWorklist;
use App\Models\Qualification;
use App\Models\QualificationRun;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Reconcile attendance the analyst may have forgotten to mark, using LIMS as the source of truth.
 *
 * For each approved reservation whose run day has already passed but that was never marked present (no run
 * recorded), if LIMS shows a worklist naming that person, record the run as performed (Pending result,
 * incubation stamped from LIMS) so the workflow advances. This catches forgotten second/third (re)scheduled
 * runs - LIMS knows the plate was run even if nobody clicked Present.
 *
 *   php artisan gqs:reconcile-attendance            # dry-run
 *   php artisan gqs:reconcile-attendance --force     # apply
 *   php artisan gqs:reconcile-attendance --days=30   # only look back this many days (default 60)
 */
class ReconcileAttendance extends Command
{
    protected $signature = 'gqs:reconcile-attendance {--days=60 : How many days back to scan} {--force : Apply (otherwise dry-run)}';
    protected $description = 'Reconcile forgotten run attendance from LIMS worklist data';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $since = now()->subDays((int) $this->option('days'))->toDateString();
        $today = now()->toDateString();

        // Approved bookings on a run day that has already passed (date < today) and is not the unscheduled queue.
        $reservations = Reservation::with(['personnel', 'runSlot'])
            ->where('status', 'approved')
            ->whereHas('runSlot', fn ($s) => $s->whereDate('slot_date', '<', $today)->whereDate('slot_date', '>=', $since))
            ->get();

        $made = 0; $skipped = 0;
        foreach ($reservations as $r) {
            if (! $r->personnel) { $skipped++; continue; }
            $q = Qualification::currentFor($r->personnel_id);
            if (! $q || ! $q->class_on_file) { $skipped++; continue; } // can't run without class on file

            // Does LIMS show a worklist for this person around/after the slot date?
            $slotDate = $r->runSlot?->slot_date?->toDateString();
            $wl = LimsWorklist::forPersonnel($r->personnel, $slotDate)->first();
            if (! $wl) { $skipped++; continue; }

            // Is this worklist already recorded on a run for the person? Then nothing to do.
            $exists = QualificationRun::where('personnel_id', $r->personnel_id)
                ->where('lims_worklist_id', $wl->worklist)->exists();
            if ($exists) { $skipped++; continue; }

            $this->line(($force ? 'Recording' : 'Would record') . ": {$r->personnel->full_name} - worklist {$wl->worklist} (run day {$slotDate})");
            if ($force) {
                app(\App\Services\QualificationEngine::class)->recordRun($r->personnel, \App\Enums\RunResult::Pending, [
                    'run_date' => $slotDate ?: now()->toDateString(),
                    'lims_worklist_id' => $wl->worklist,
                ]);
                $run = QualificationRun::where('personnel_id', $r->personnel_id)->latest('id')->first();
                if ($run && ! $run->incubation_started_at) {
                    $run->incubation_started_at = $wl->inc1_start ?: now();
                    $run->save();
                }
                $r->update(['status' => 'completed', 'lims_worklist_id' => $wl->worklist]);
                app(\App\Services\WorklistSync::class)->syncRun($run->fresh());
                app(\App\Services\RunCycleAdvancer::class)->advance($q->fresh());
            }
            $made++;
        }

        $this->info(($force ? 'Reconciled ' : 'Would reconcile ') . "{$made} run(s) from LIMS; {$skipped} skipped.");
        if (! $force) $this->comment('Dry run. Re-run with --force to apply.');
        return self::SUCCESS;
    }
}
