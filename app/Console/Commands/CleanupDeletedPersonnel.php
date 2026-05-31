<?php

namespace App\Console\Commands;

use App\Models\ClassCompletion;
use App\Models\ClassEnrollment;
use App\Models\Personnel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time / ad-hoc cleanup of data that got stuck because Personnel uses soft deletes (so the DB
 * cascade never fired on delete). Hard-deletes already soft-deleted personnel and their child rows,
 * and clears orphaned enrollments/completions whose personnel_id was nulled by a prior delete.
 *
 *   php artisan gqs:cleanup-deleted          # dry run, shows what would be removed
 *   php artisan gqs:cleanup-deleted --force  # actually delete
 */
class CleanupDeletedPersonnel extends Command
{
    protected $signature = 'gqs:cleanup-deleted {--force : actually perform the deletions}';
    protected $description = 'Purge stuck soft-deleted personnel and orphaned class enrollments/completions.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $trashed = Personnel::onlyTrashed()->get();
        $orphanEnroll = ClassEnrollment::whereNull('personnel_id')->count();
        $orphanComplete = ClassCompletion::whereNull('personnel_id')->count();

        $this->info("Soft-deleted personnel to purge: {$trashed->count()}");
        foreach ($trashed as $p) {
            $this->line("  - #{$p->id} {$p->first_name} {$p->last_name} ({$p->employee_id})");
        }
        $this->info("Orphaned class enrollments (no personnel): {$orphanEnroll}");
        $this->info("Orphaned class completions (no personnel): {$orphanComplete}");

        if (! $force) {
            $this->warn('Dry run. Re-run with --force to delete.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($trashed) {
            foreach ($trashed as $p) {
                // forceDelete triggers the model's forceDeleting hook (enrollments + completions) and
                // the DB cascade (reservations/qualifications/runs).
                $p->forceDelete();
            }
            // Clear any remaining orphans (from deletes that happened before the hook existed).
            ClassEnrollment::whereNull('personnel_id')->delete();
            ClassCompletion::whereNull('personnel_id')->delete();
        });

        $this->info('Cleanup complete.');
        return self::SUCCESS;
    }
}
