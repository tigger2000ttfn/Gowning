<?php

namespace App\Models;

use App\Enums\QualificationType;
use App\Enums\RunResult;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualificationRun extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'personnel_id', 'qualification_id', 'run_slot_id', 'reservation_id',
        'run_date', 'result', 'cycle_type', 'notes', 'recorded_by',
        'signed_by', 'signed_at', 'signature_meaning',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'result' => RunResult::class,
            'cycle_type' => QualificationType::class,
            'signed_at' => 'datetime',
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
