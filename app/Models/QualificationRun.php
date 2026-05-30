<?php

namespace App\Models;

use App\Enums\QualificationType;
use App\Enums\RunResult;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\GqsActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class QualificationRun extends Model implements HasMedia
{
    protected static function booted(): void
    {
        static::created(function ($run) {
            if (! $run->run_uid) {
                $run->run_uid = 'QRUN-' . str_pad((string) $run->id, 5, '0', STR_PAD_LEFT);
                $run->saveQuietly();
            }
            if (($run->result->value ?? $run->result) === 'fail') {
                $name = $run->personnel?->full_name ?? 'A trainee';
                \App\Services\Notifier::toCapability(
                    \App\Enums\Capability::QaReview,
                    'Failed Qualification Run',
                    "{$name} had a failed run on {$run->run_date?->gmp()} — QA determination needed.",
                    \App\Filament\Admin\Resources\QualificationResource::getUrl(),
                    'danger',
                );
            }
        });
    }

    use Auditable, SoftDeletes, GqsActivityLog, InteractsWithMedia;

    protected $fillable = [
        'run_uid',
        'personnel_id', 'qualification_id', 'run_slot_id', 'reservation_id',
        'parent_run_id', 'qa_determination', 'qa_determined_at', 'is_complete',
        'run_date', 'result', 'cycle_type', 'notes', 'recorded_by', 'is_seed',
        'signed_by', 'signed_at', 'signature_meaning',
        'lims_worklist_id', 'veeva_doc_number', 'veeva_url', 'results_entered_at', 'incubation_started_at', 'results_released_at', 'qa_signed_at', 'qa_signed_by', 'qa_notes', 'qcm_signed_at', 'qcm_signed_by',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'result' => RunResult::class,
            'is_seed' => 'boolean',
            'is_complete' => 'boolean',
            'qa_determined_at' => 'datetime',
            'cycle_type' => QualificationType::class,
            'signed_at' => 'datetime',
            'incubation_started_at' => 'datetime',
            'results_released_at' => 'datetime',
            'results_entered_at' => 'datetime',
            'qa_signed_at' => 'datetime',
        ];
    }

    /** The run this one descends from (e.g. a requal run spawned after a failed run's QA determination). */
    public function parentRun(): BelongsTo { return $this->belongsTo(QualificationRun::class, 'parent_run_id'); }
    public function childRuns(): HasMany { return $this->hasMany(QualificationRun::class, 'parent_run_id'); }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(Qualification::class);
    }

    public function runSlot(): BelongsTo
    {
        return $this->belongsTo(RunSlot::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isPass(): bool
    {
        return $this->result === RunResult::Pass;
    }
}
