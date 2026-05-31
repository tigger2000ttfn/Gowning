<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\GqsActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    protected static function booted(): void
    {
        static::created(function ($reservation) {
            if (($reservation->status->value ?? $reservation->status) === 'requested') {
                $name = $reservation->personnel?->full_name ?? 'Someone';
                \App\Services\Notifier::toCapability(
                    \App\Enums\Capability::ManageScheduling,
                    'New Run Request',
                    "{$name} requested a qualification run slot.",
                    \App\Filament\Admin\Resources\ReservationResource::getUrl(),
                );
            }
        });
    }

    use Auditable, SoftDeletes, GqsActivityLog;

    protected $fillable = [
        'run_slot_id', 'personnel_id', 'parent_reservation_id', 'status',
        'requested_at', 'decided_by', 'decided_at', 'notes', 'lims_worklist_id', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'acknowledged_at' => 'datetime',
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

    /** The original booking this follow-up was split from (for rescheduled remaining runs). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_reservation_id');
    }

    /** Follow-up bookings split off this one. */
    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent_reservation_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
