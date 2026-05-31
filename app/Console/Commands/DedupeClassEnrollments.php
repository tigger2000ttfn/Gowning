<?php

namespace App\Console\Commands;

use App\Models\ClassEnrollment;
use Illuminate\Console\Command;

/**
 * Finds and removes duplicate ACTIVE class enrollments: the same person enrolled more than once in the
 * same session (e.g. an admin booking plus a self-signup that did not match the existing row). Keeps the
 * most-progressed / earliest row and cancels the extras. Dry-run by default.
 */
class DedupeClassEnrollments extends Command
{
    protected $signature = 'gqs:dedupe-class-enrollments {--force : Actually cancel the duplicates (default is dry-run)}';
    protected $description = 'Find and clean duplicate active class enrollments (same person, same session)';

    // status priority: keep the most-progressed enrollment.
    protected array $priority = ['completed' => 5, 'pending_qa' => 4, 'qcm_reviewed' => 3, 'attended' => 2, 'signed_up' => 1];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $active = ClassEnrollment::query()
            ->whereIn('status', ClassEnrollment::ACTIVE_STATUSES)
            ->whereNotNull('personnel_id')
            ->get();

        // Group by person + session; any group with >1 is a duplicate set.
        $groups = $active->groupBy(fn ($e) => $e->personnel_id . ':' . $e->class_session_id)
            ->filter(fn ($g) => $g->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicate active class enrollments found.');
            return self::SUCCESS;
        }

        $removed = 0;
        foreach ($groups as $key => $g) {
            // Keep the highest-priority status; tie-break on earliest id (the original).
            $keep = $g->sortByDesc(fn ($e) => [$this->priority[$e->status] ?? 0, -$e->id])->first();
            $name = $keep->personnel?->full_name ?? $keep->name ?? ('person ' . $keep->personnel_id);
            $this->line("Duplicate: {$name} in session {$keep->class_session_id} -> keep #{$keep->id} ({$keep->status})");
            foreach ($g as $e) {
                if ($e->id === $keep->id) continue;
                $this->line("    " . ($force ? 'cancelling' : 'would cancel') . " #{$e->id} ({$e->status})");
                if ($force) { $e->markStatus('cancelled', null); }
                $removed++;
            }
        }

        $this->info(($force ? 'Cancelled' : 'Would cancel') . " {$removed} duplicate enrollment(s) across {$groups->count()} person/session group(s).");
        if (! $force) $this->comment('Dry run. Re-run with --force to apply.');
        return self::SUCCESS;
    }
}
