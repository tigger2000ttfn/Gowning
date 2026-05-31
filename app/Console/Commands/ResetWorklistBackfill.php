<?php

namespace App\Console\Commands;

use App\Models\ClassCompletion;
use App\Models\Qualification;
use App\Models\QualificationRun;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cleanly removes everything the LIMS worklist backfill created, so the (corrected) backfill can be
 * re-run from a clean slate. This is needed when an earlier backfill produced bad/partial data, e.g.
 * an annual requal that was wrongly created with 3 runs instead of 1.
 *
 * What it removes (only backfill-created data, never hand-entered records):
 *   - QualificationRun rows whose notes start with "Historic backfill from LIMS worklist"
 *   - Qualification rows that have a lims_worklist_id AND were left at the backfill stages
 *     (Results Released / Awaiting Results / Failed) with NO QA sign-off and NO QCM sign-off
 *   - ClassCompletion rows with source = 'inferred'
 *   - Resets the worklist_backfill_done flag so the bulk backfill can run again
 *
 * SAFETY: a qualification that has ANY QA-signed or QCM-signed run, or whose status is Qualified, is
 * left untouched (it is no longer purely backfill data). Dry-run by default; pass --force to apply.
 */
class ResetWorklistBackfill extends Command
{
    protected $signature = 'gqs:reset-worklist-backfill {--force : Apply the deletion (otherwise dry-run)}';
    protected $description = 'Wipe LIMS-backfill-created qualifications/runs/inferred classes and reset the backfill flag for a clean reimport';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        // Backfilled runs are tagged in their notes.
        $runQuery = QualificationRun::where('notes', 'like', 'Historic backfill from LIMS worklist%');
        $runCount = (clone $runQuery)->count();

        // Candidate qualifications: created by backfill (have a worklist id, sat at a backfill stage),
        // excluding any that have since been signed off (QA or QCM) or reached Qualified.
        $qualQuery = Qualification::query()
            ->whereNotNull('lims_worklist_id')
            ->whereIn('workflow_stage', ['results_released', 'awaiting_results', 'failed'])
            ->where('status', '!=', 'qualified')
            ->whereDoesntHave('runs', function ($q) {
                $q->whereNotNull('qa_signed_at')->orWhereNotNull('qcm_signed_at');
            });
        $qualCount = (clone $qualQuery)->count();

        $inferredQuery = ClassCompletion::where('source', 'inferred');
        $inferredCount = (clone $inferredQuery)->count();

        $this->info('LIMS backfill reset ' . ($force ? '(APPLYING)' : '(dry-run)'));
        $this->line("  Backfilled runs to delete:            {$runCount}");
        $this->line("  Backfill qualifications to delete:    {$qualCount}");
        $this->line("  Inferred class completions to delete: {$inferredCount}");
        $this->line('  worklist_backfill_done flag:          ' . (Setting::get('worklist_backfill_done', false) ? 'set -> will reset' : 'not set'));

        if (! $force) {
            $this->warn('Dry-run only. Re-run with --force to apply, then re-run the backfill from the Worklist Catalog.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($runQuery, $qualQuery, $inferredQuery) {
            // Delete runs first (FK to qualification), then the qualifications, then inferred classes.
            (clone $runQuery)->delete();

            // For the candidate qualifications, also delete any of their remaining runs, then the quals.
            $qualIds = (clone $qualQuery)->pluck('id');
            if ($qualIds->isNotEmpty()) {
                QualificationRun::whereIn('qualification_id', $qualIds)->delete();
                Qualification::whereIn('id', $qualIds)->delete();
            }

            (clone $inferredQuery)->delete();

            Setting::put('worklist_backfill_done', false);
        });

        $this->info('Done. Backfill data cleared and the flag reset. Now re-run the backfill from the Worklist Catalog (Backfill From LIMS).');
        return self::SUCCESS;
    }
}
