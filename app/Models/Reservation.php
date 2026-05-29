<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'run_slot_id', 'personnel_id', 'status',
        'requested_at', 'decided_by', 'decided_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function runSlot(): BelongsTo
    {
        return $this->belongsTo(RunSlot::class);
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
