<?php

namespace App\Models;

use App\Enums\QualificationType;
use App\Enums\RunResult;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\GqsActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualificationRun extends Model
{
    protected static function booted(): void
    {
        static::created(function ($run) {
            if (($run->result->value ?? $run->result) === 'fail') {
                $name = $run->personnel?->full_name ?? 'A trainee';
                \App\Services\Notifier::toCapability(
                    \App\Enums\Capability::QaReview,
                    'Failed Qualification Run',
                    "{$name} had a failed run on {$run->run_date?->format('M j, Y')} — QA determination needed.",
                    \App\Filament\Admin\Resources\QualificationResource::getUrl(),
                    'danger',
                );
            }
        });
    }

    use Auditable, SoftDeletes, GqsActivityLog;

    protected $fillable = [
        'personnel_id', 'qualification_id', 'run_slot_id', 'reservation_id',
        'run_date', 'result', 'cycle_type', 'notes', 'recorded_by',
        'signed_by', 'signed_at', 'signature_meaning',
        'incubation_started_at', 'results_released_at', 'qa_signed_at', 'qa_signed_by', 'qa_notes',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'result' => RunResult::class,
            'cycle_type' => QualificationType::class,
            'signed_at' => 'datetime',
            'incubation_started_at' => 'datetime',
            'results_released_at' => 'datetime',
            'qa_signed_at' => 'datetime',
        ];
    }

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
