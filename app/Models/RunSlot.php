<?php

namespace App\Models;

use App\Enums\RunSlotStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RunSlot extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'cleanroom', 'slot_date', 'start_time', 'end_time',
        'capacity', 'status', 'notes', 'created_by', 'assigned_analyst_id', 'attendance_submitted_at', 'attendance_submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'slot_date' => 'date',
            'capacity' => 'integer',
            'status' => RunSlotStatus::class,
            'attendance_submitted_at' => 'datetime',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedCount(): int
    {
        return $this->reservations()
            ->whereIn('status', ['approved', 'completed'])
            ->count();
    }

    public function hasCapacity(): bool
    {
        return $this->approvedCount() < $this->capacity;
    }

    public function analyst() { return $this->belongsTo(\App\Models\User::class, 'assigned_analyst_id'); }
}
