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

            // AUTHORIZED + COMPLETED gate: we never infer/update from a worklist until it is authorized and
            // all samples are final in LIMS. Before that the run dates / count can't be determined.
            if (! ($wl->worklist_all_final && $wl->isAuthorized())) {
                $this->line("Holding (not authorized/final): {$r->personnel->full_name} - worklist {$wl->worklist}");
                $skipped++; continue;
            }

            // Is this worklist already recorded on a run for the person? Then nothing to do.
            $exists = QualificationRun::where('personnel_id', $r->personnel_id)
                ->where('lims_worklist_id', $wl->worklist)->exists();
            if ($exists) { $skipped++; continue; }

            // One worklist covers the whole run session. Infer how many runs and on which dates from the
            // QUAL DATE 1/2/3 + RUN 2/3 RESCHEDULED columns (same-day unless rescheduled).
            $required = max(1, (int) ($q->runs_required ?? 1));
            $dates = $wl->effectiveRunDates();
            $runDates = array_values(array_filter([
                $dates['run1']?->toDateString(),
                $required >= 2 ? ($dates['run2']?->toDateString()) : null,
                $required >= 3 ? ($dates['run3']?->toDateString()) : null,
            ]));
            if (empty($runDates)) $runDates = [$slotDate ?: now()->toDateString()];
            // Don't double-count runs already recorded for this cycle.
            $alreadyDone = app(\App\Services\RunCycleAdvancer::class)->cycleRuns($q)->count();
            $toRecord = max(0, min(count($runDates), $required) - $alreadyDone);
            if ($toRecord <= 0) { $skipped++; continue; }
            $reviewNote = $dates['needs_review'] ? ' [needs review: reschedule flag set but date missing]' : '';

            $this->line(($force ? 'Recording' : 'Would record') . " {$toRecord} run(s): {$r->personnel->full_name} - worklist {$wl->worklist} on " . implode(', ', array_slice($runDates, $alreadyDone, $toRecord)) . $reviewNote);
            if ($force) {
                $incStart = $wl->inc1_start ?: null;
                foreach (array_slice($runDates, $alreadyDone, $toRecord) as $rd) {
                    app(\App\Services\QualificationEngine::class)->recordRun($r->personnel, \App\Enums\RunResult::Pending, [
                        'run_date' => $rd ?: ($slotDate ?: now()->toDateString()),
                        'lims_worklist_id' => $wl->worklist,
                    ]);
                    $run = QualificationRun::where('personnel_id', $r->personnel_id)->latest('id')->first();
                    if ($run && ! $run->incubation_started_at) {
                        $run->incubation_started_at = $incStart ?: ($rd ? \Illuminate\Support\Carbon::parse($rd) : now());
                        $run->save();
                    }
                }
                $r->update(['status' => 'completed', 'lims_worklist_id' => $wl->worklist]);
                $latestRun = QualificationRun::where('personnel_id', $r->personnel_id)->latest('id')->first();
                if ($latestRun) app(\App\Services\WorklistSync::class)->syncRun($latestRun->fresh());
                app(\App\Services\RunCycleAdvancer::class)->advance($q->fresh());
            }
            $made += $toRecord;
        }

        $this->info(($force ? 'Reconciled ' : 'Would reconcile ') . "{$made} run(s) from authorized/final LIMS worklists; {$skipped} skipped.");
        if (! $force) $this->comment('Dry run. Re-run with --force to apply. QCM still signs off before submission; any mismatches are rectified in LIMS.');
        return self::SUCCESS;
    }
}
