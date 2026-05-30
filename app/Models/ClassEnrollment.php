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
    ];

    /**
     * Robustly set attendance status, stamping who/when, and advancing the
     * person's qualification (class_on_file + ClassComplete + ClassCompleted automation)
     * when marked attended or completed. Centralized so every entry point behaves the same.
     */
    public function markStatus(string $status, ?int $byUserId = null): void
    {
        if (! in_array($status, ['signed_up', 'attended', 'completed', 'no_show', 'cancelled'], true)) {
            return;
        }
        $this->status = $status;
        $this->marked_by = $byUserId;
        if ($status === 'attended' && ! $this->attended_at) $this->attended_at = now();
        if ($status === 'completed') {
            if (! $this->attended_at) $this->attended_at = now();
            $this->completed_at = now();
        }
        $this->save();

        // attended or completed = class done; advance the qualification
        if (in_array($status, ['attended', 'completed'], true) && $this->personnel) {
            $q = \App\Models\Qualification::firstOrCreate(
                ['personnel_id' => $this->personnel->id],
                ['type' => 'initial', 'status' => 'in_progress',
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
            \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::ClassCompleted, ['personnel' => $this->personnel, 'qualification' => $q]);
        }
    }

    protected function casts(): array
    {
        return ['signed_up_at' => 'datetime'];
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
