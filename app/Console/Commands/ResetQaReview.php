<?php

namespace App\Console\Commands;

use App\Enums\QualificationStatus;
use App\Enums\WorkflowStage;
use App\Models\ClassEnrollment;
use App\Models\Personnel;
use App\Models\Qualification;
use Illuminate\Console\Command;

/**
 * Clear a stuck QA-review / QA-signoff loop for a person, or reset their current qualification to a clean
 * earlier stage. For super-user recovery of records left in a bad state by earlier (pre-QA-gating) data.
 *
 * Examples:
 *   php artisan gqs:reset-qa A4050130                 # dry-run: show what would change
 *   php artisan gqs:reset-qa A4050130 --force         # send the qual back out of QA review
 *   php artisan gqs:reset-qa A4050130 --to=class --force   # reset all the way back to Class Pending
 *   php artisan gqs:reset-qa A4050130 --clear-enrollments --force  # also cancel stuck class enrollments
 */
class ResetQaReview extends Command
{
    protected $signature = 'gqs:reset-qa {employee : Employee ID, last name, or LIMS username}
        {--to=results : Where to send it - results (Results Released) | class (Class Pending) | scheduled (Run Scheduled)}
        {--clear-enrollments : Also cancel any active/pending class enrollments for this person}
        {--force : Apply the changes (otherwise dry-run)}';
    protected $description = 'Clear a stuck QA review / reset a person\'s current qualification stage (super-user recovery)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $needle = strtoupper(trim((string) $this->argument('employee')));
        $to = strtolower((string) $this->option('to'));

        $people = Personnel::query()
            ->where(fn ($q) => $q
                ->whereRaw('UPPER(employee_id) = ?', [$needle])
                ->orWhereRaw('UPPER(last_name) = ?', [$needle])
                ->orWhereRaw('UPPER(lims_username) = ?', [$needle]))
            ->get();

        if ($people->isEmpty()) {
            $this->error("No personnel matched '{$needle}'.");
            return self::FAILURE;
        }

        $targetStage = match ($to) {
            'class' => WorkflowStage::ClassPending,
            'scheduled' => WorkflowStage::RunScheduled,
            default => WorkflowStage::ResultsReleased,
        };

        foreach ($people as $p) {
            $this->line("Personnel: {$p->full_name} (#{$p->id}, {$p->employee_id})");
            $q = Qualification::where('personnel_id', $p->id)->whereNull('superseded_at')->latest('id')->first();
            if (! $q) { $this->warn('  No active qualification.'); }
            else {
                $this->line('  Current: stage=' . ($q->workflow_stage?->value ?? 'null') . ' status=' . ($q->status instanceof \BackedEnum ? $q->status->value : $q->status)
                    . ' qa_owner_id=' . ($q->qa_owner_id ?? 'null') . ' qa_recommendation=' . ($q->qa_recommendation ?? 'null'));
                $this->line('  ' . ($force ? 'Applying' : 'Would apply') . ': stage -> ' . $targetStage->value . ', status -> In Progress, clear QA owner/recommendation/qualified_date');

                if ($force) {
                    // Clear the run-level QA signature on this cycle's runs so nothing re-locks it.
                    $runsQ = \App\Models\QualificationRun::where('personnel_id', $p->id);
                    if ($q->cycle_started_at) $runsQ->whereDate('run_date', '>=', $q->cycle_started_at);
                    $runsQ->update(['qa_signed_at' => null, 'qa_signed_by' => null]);

                    $q->workflow_stage = $targetStage;
                    $q->stage_changed_at = now();
                    if ($targetStage === WorkflowStage::ClassPending) {
                        $q->status = QualificationStatus::Pending;
                    } else {
                        $q->status = QualificationStatus::InProgress;
                    }
                    $q->qualified_date = null;
                    $q->qa_owner_id = null;
                    $q->qa_recommendation = null;
                    $q->requal_started_at = null;
                    $q->save();
                }
            }

            if ($this->option('clear-enrollments')) {
                $enrolls = ClassEnrollment::where('personnel_id', $p->id)
                    ->whereIn('status', ['signed_up', 'attended', 'qcm_reviewed', 'pending_qa', 'completed'])
                    ->get();
                $this->line('  ' . ($force ? 'Cancelling' : 'Would cancel') . ' ' . $enrolls->count() . ' class enrollment(s).');
                if ($force) {
                    foreach ($enrolls as $e) { $e->markStatus('cancelled', \Illuminate\Support\Facades\Auth::id()); }
                }
            }
        }

        $this->info($force ? 'Done.' : 'Dry run complete. Re-run with --force to apply.');
        return self::SUCCESS;
    }
}
