<?php

namespace App\Services;

use App\Enums\QualificationType;
use App\Enums\RunResult;
use App\Enums\WorkflowStage;
use App\Models\Qualification;
use App\Models\QualificationRun;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Reconciles a manually-seeded qualification (entered during first-time Personnel
 * setup) into real run-history records, so the engine has something to replay and
 * the seed survives recompute. We do NOT back-enter every historical run; we create
 * just enough synthetic "seed" passes inside the current 12-month cycle to represent
 * the person's stated current status.
 */
class QualificationSeeder
{
    const SEED_NOTE = 'Seeded at manual setup';

    public function reconcile(Qualification $q): void
    {
        $status = $q->status instanceof \BackedEnum ? $q->status->value : $q->status;
        $wanted = max(0, (int) $q->runs_completed);

        // If the admin entered individual real runs (via the Run History repeater),
        // those are the source of truth. Recompute from them and do NOT lay down
        // synthetic seed passes from the count (which would otherwise duplicate history).
        $realRuns = QualificationRun::where('personnel_id', $q->personnel_id)
            ->where(function ($w) { $w->whereNull('is_seed')->orWhere('is_seed', false); })
            ->count();
        if ($realRuns > 0) {
            // Remove any old synthetic seeds so they don't double-count with real runs.
            QualificationRun::where('personnel_id', $q->personnel_id)->where('is_seed', true)->delete();
            // Anchor the cycle at the earliest real run so the engine replays the whole history.
            $first = QualificationRun::where('personnel_id', $q->personnel_id)->orderBy('run_date')->orderBy('id')->first();
            if ($first && ! $q->cycle_started_at) {
                $q->cycle_started_at = $first->run_date?->toDateString();
            }
            // Entering real passes implies the class was done.
            if (! $q->class_on_file) {
                $q->class_on_file = true;
                if (! $q->class_on_file_date) $q->class_on_file_date = now()->toDateString();
            }
            $q->save();
            $this->finish($q);
            return;
        }

        // Nothing to seed for a fresh/pending person with zero runs.
        if ($wanted === 0 && ! in_array($status, ['qualified', 'lapsed'], true)) {
            return;
        }

        // Being entered as qualified or in-progress with runs means classroom was done.
        if (in_array($status, ['qualified', 'in_progress'], true) && $wanted > 0) {
            if (! $q->class_on_file) {
                $q->class_on_file = true;
                if (! $q->class_on_file_date) $q->class_on_file_date = now()->toDateString();
            }
        }

        // Anchor the cycle so recompute only replays from here forward (won't recount
        // anything older, and the seed defines this cycle).
        $cycleMonths = (int) Setting::get('cycle_months', 12);

        // Determine the anchor date for the cycle:
        // - qualified: the qualifying pass = qualified_date (or due_date - cycle), default today
        // - in progress: spread recent passes ending ~now
        $qualifiedDate = $q->qualified_date ? Carbon::parse($q->qualified_date) : null;

        // How many seed runs already exist for this person?
        $existingSeeds = QualificationRun::where('personnel_id', $q->personnel_id)
            ->where('is_seed', true)->orderBy('run_date')->get();

        // If the desired count matches existing seeds, just recompute (idempotent re-save).
        if ($existingSeeds->count() === $wanted && $wanted > 0) {
            $this->finish($q);
            return;
        }

        // Rebuild: remove prior seed runs and lay down a clean set (avoids drift on edit).
        if ($existingSeeds->isNotEmpty()) {
            QualificationRun::where('personnel_id', $q->personnel_id)
                ->where('is_seed', true)->delete();
        }

        if ($wanted <= 0) {
            $this->finish($q);
            return;
        }

        // Date the passes. For a qualified person, the LAST pass lands on the qualifying
        // date; earlier passes step back a couple weeks each. For in-progress, end near today.
        $lastPassDate = $qualifiedDate
            ?? ($q->due_date ? Carbon::parse($q->due_date)->subMonths($cycleMonths) : now());
        if ($lastPassDate->isFuture()) $lastPassDate = now();

        $type = $q->type instanceof \BackedEnum ? $q->type->value : ($q->type ?? 'initial');
        $cycleType = $type === 'annual' ? QualificationType::Annual : QualificationType::Initial;

        $anchor = null;
        for ($i = $wanted - 1; $i >= 0; $i--) {
            $date = (clone $lastPassDate)->subWeeks(2 * $i);
            if ($anchor === null) $anchor = $date->toDateString();
            QualificationRun::create([
                'personnel_id' => $q->personnel_id,
                'run_date' => $date->toDateString(),
                'result' => RunResult::Pass,
                'cycle_type' => $cycleType,
                'notes' => self::SEED_NOTE, 'is_seed' => true,
            ]);
        }

        // Cycle starts at the first seeded pass so recompute stays within this cycle.
        $q->cycle_started_at = $anchor;
        $q->save();

        $this->finish($q);
    }

    /** Let the engine recompute status/due_date from the (now-seeded) run history. */
    protected function finish(Qualification $q): void
    {
        app(QualificationEngine::class)->recompute($q->fresh());
    }
}
