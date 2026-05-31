<?php

namespace App\Console\Commands;

use App\Enums\QualificationStatus;
use App\Enums\WorkflowStage;
use App\Models\ClassCompletion;
use App\Models\Personnel;
use App\Models\Qualification;
use App\Models\QualificationRun;
use Illuminate\Console\Command;

/**
 * One-time fix for the single backfilled record (M. Baker) created before backfills were corrected to
 * never jump straight to Qualified. Restores the correct state: a backfilled record is In Progress at
 * Results Released awaiting QA (only QA sign-off makes someone Qualified and sets the qualified date).
 * Also records an INFERRED classroom completion (an annual requal implies the class was taken at some
 * point) which QA can edit/confirm on the Class Completions page.
 *
 * Idempotent. Dry-run by default; pass --force to apply.
 */
class FixBakerBackfill extends Command
{
    protected $signature = 'gqs:fix-baker-backfill {--employee= : Employee ID or last name to target (default: BAKER)} {--force}';
    protected $description = 'Repair the backfilled qualification that wrongly shows Qualified (M. Baker)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $needle = strtoupper(trim((string) ($this->option('employee') ?: 'BAKER')));

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

        foreach ($people as $p) {
            $this->line("Personnel: {$p->full_name} (#{$p->id}, {$p->employee_id})");
            $q = Qualification::where('personnel_id', $p->id)->whereNull('superseded_at')->latest('id')->first();
            if (! $q) { $this->warn('  No active qualification; skipping.'); continue; }

            // Recompute pass count from actual Pass runs in the current cycle.
            $passCount = QualificationRun::where('personnel_id', $p->id)
                ->where('result', \App\Enums\RunResult::Pass->value)
                ->when($q->cycle_started_at, fn ($query) => $query->whereDate('run_date', '>=', $q->cycle_started_at))
                ->count();

            $this->line("  Current: status={$this->val($q->status)} stage={$this->val($q->workflow_stage)} qualified_date=" . ($q->qualified_date?->toDateString() ?? 'null') . " runs_completed={$q->runs_completed} -> recomputed passes={$passCount}");

            // The corrected state: NOT qualified (QA never signed off). In Progress, Results Released,
            // qualified_date cleared (QA sets it), pass count corrected, and the wrongly-computed due_date
            // cleared - the backfill set due = backfilled-run + 1yr (e.g. 28-JAN-2027), implying he was
            // qualified through then. He is not. QA sign-off will set the real next due (approval + cycle).
            $changes = [
                'status' => QualificationStatus::InProgress,
                'qualified_date' => null,
                'due_date' => null,
                'runs_completed' => $passCount,
            ];
            // Only move stage to Results Released if it is currently sitting at/after qualified by mistake,
            // or already there. Do not pull back a record that legitimately reached QA review/sign-off via QA.
            if (in_array($this->val($q->workflow_stage), ['qa_signoff'], true)) {
                $changes['workflow_stage'] = WorkflowStage::ResultsReleased;
            }

            $this->line('  ' . ($force ? 'Applying' : 'Would apply') . ': status=In Progress, qualified_date=null, due_date=null, runs_completed=' . $passCount
                . (isset($changes['workflow_stage']) ? ', stage=Results Released' : ''));

            // Inferred classroom completion (editable by QA). Only add if none exists.
            $hasClass = ClassCompletion::where('personnel_id', $p->id)->exists();
            $willAddClass = ! $hasClass;
            if ($willAddClass) {
                $this->line('  ' . ($force ? 'Adding' : 'Would add') . ' inferred classroom completion (QA can edit on Class Completions).');
            } else {
                $this->line('  Classroom completion already on file; leaving as is.');
            }

            if ($force) {
                $q->fill($changes)->save();
                if ($willAddClass) {
                    ClassCompletion::create([
                        'personnel_id' => $p->id,
                        'employee_id' => $p->employee_id,
                        'class_name' => 'Gowning Qualification Class (Inferred)',
                        'completion_date' => ($q->class_on_file_date?->toDateString())
                            ?? ($q->cycle_started_at?->toDateString())
                            ?? now()->toDateString(),
                        'source' => 'inferred',
                    ]);
                    // mark class on file so the pipeline does not flag "missing class".
                    $q->class_on_file = true;
                    if (! $q->class_on_file_date) $q->class_on_file_date = now()->toDateString();
                    $q->save();
                }
            }
        }

        $this->info($force ? 'Done.' : 'Dry run complete. Re-run with --force to apply.');
        return self::SUCCESS;
    }

    protected function val($v): ?string
    {
        return $v instanceof \BackedEnum ? $v->value : (is_null($v) ? null : (string) $v);
    }
}
