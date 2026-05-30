<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\GqsActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassEnrollment extends Model
{
    use Auditable, GqsActivityLog;

    protected $fillable = [
        'class_session_id', 'personnel_id', 'name', 'email',
        'employee_id', 'status', 'signed_up_at',
        'attended_at', 'completed_at', 'marked_by', 'attendance_note',
        'qa_completed_by', 'qa_completed_at',
    ];

    /**
     * Robustly set attendance status, stamping who/when, and advancing the
     * person's qualification (class_on_file + ClassComplete + ClassCompleted automation)
     * when marked attended or completed. Centralized so every entry point behaves the same.
     */
    public function markStatus(string $status, ?int $byUserId = null): void
    {
        if (! in_array($status, ['signed_up', 'attended', 'completed', 'no_show', 'cancelled', 'historical'], true)) {
            return;
        }
        $this->status = $status;
        $this->marked_by = $byUserId;
        if ($status === 'attended' && ! $this->attended_at) $this->attended_at = now();
        if ($status === 'completed') {
            if (! $this->attended_at) $this->attended_at = now();
            $this->completed_at = now();
            $this->qa_completed_by = $byUserId;
            $this->qa_completed_at = now();
        }
        $this->save();

        // Only QA 'completed' (QA reviewed the signed classroom form) makes the class
        // official: class on file, a ClassCompletion record, and the person becomes
        // eligible to start qual runs (advance to Class Complete). 'attended' is just the
        // trainer's attendance mark and does NOT make them run-eligible.
        if ($status === 'completed' && $this->personnel) {
            $q = \App\Models\Qualification::firstOrCreate(
                ['personnel_id' => $this->personnel->id],
                ['type' => 'initial', 'status' => 'pending',
                 'runs_required' => (int) \App\Models\Setting::get('initial_runs_required', 3),
                 'runs_completed' => 0]
            );
            $q->class_on_file = true;
            if (! $q->class_on_file_date) $q->class_on_file_date = now()->toDateString();
            if (in_array($q->workflow_stage?->value, [null, 'class_pending'], true)) {
                $q->workflow_stage = \App\Enums\WorkflowStage::ClassComplete;
                $q->stage_changed_at = now();
            }
            $q->save();

            // Create the specific ClassCompletion record (its own record; QA-approved).
            $exists = \App\Models\ClassCompletion::where('personnel_id', $this->personnel->id)
                ->where('class_name', $this->classSession?->trainingClass?->name ?? 'Gowning Class')
                ->whereDate('completion_date', $this->completed_at?->toDateString())
                ->exists();
            if (! $exists) {
                \App\Models\ClassCompletion::create([
                    'personnel_id' => $this->personnel->id,
                    'employee_id' => $this->personnel->employee_id,
                    'class_name' => $this->classSession?->trainingClass?->name ?? 'Gowning Class',
                    'completion_date' => $this->completed_at?->toDateString() ?? now()->toDateString(),
                    'source' => 'manual',
                ]);
            }

            \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::ClassCompleted, ['personnel' => $this->personnel, 'qualification' => $q]);
        }
    }

    protected function casts(): array
    {
        return ['signed_up_at' => 'datetime', 'attended_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }
}
