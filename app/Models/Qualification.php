<?php

namespace App\Models;

use App\Enums\QualificationStatus;
use App\Enums\QualificationType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\GqsActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Qualification extends Model
{
    use Auditable, SoftDeletes, GqsActivityLog;

    protected static function booted(): void
    {
        // Central StageChanged automation: fire once whenever workflow_stage actually changes,
        // no matter which code path moved it (auto-scheduler, approve, perform, results, drag).
        static::updated(function (Qualification $q) {
            if ($q->wasChanged('workflow_stage') && $q->workflow_stage) {
                \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::StageChanged, [
                    'personnel' => $q->personnel,
                    'qualification' => $q,
                    'stage' => $q->workflow_stage instanceof \BackedEnum ? $q->workflow_stage->value : $q->workflow_stage,
                ]);
            }
        });
    }

    protected $fillable = [
        'personnel_id', 'type', 'status', 'runs_required',
        'runs_completed', 'qualified_date', 'due_date',
        'workflow_stage', 'stage_changed_at', 'qa_recommendation', 'qa_recommendation_note', 'class_on_file', 'class_on_file_date', 'qa_owner_id', 'cycle_started_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => QualificationType::class,
            'status' => QualificationStatus::class,
            'workflow_stage' => \App\Enums\WorkflowStage::class,
            'stage_changed_at' => 'datetime',
            'class_on_file' => 'boolean',
            'class_on_file_date' => 'date',
            'runs_required' => 'integer',
            'runs_completed' => 'integer',
            'qualified_date' => 'date',
            'due_date' => 'date',
            'cycle_started_at' => 'date',
        ];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(QualificationRun::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(QualificationComment::class)->latest();
    }

    public function isPastDue(): bool
    {
        return $this->due_date !== null && $this->due_date->isPast();
    }

    /** Days until due (negative if overdue); null if never qualified. */
    public function daysUntilDue(): ?int
    {
        if ($this->due_date === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->due_date, false);
    }

    public function qaOwner() { return $this->belongsTo(\App\Models\User::class, 'qa_owner_id'); }
}
