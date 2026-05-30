<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassSession extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'session_uid',
        'training_class_id', 'session_date', 'start_time', 'end_time',
        'location', 'instructor', 'capacity', 'status', 'assigned_instructor_id', 'attendance_submitted_at', 'attendance_submitted_by',
    ];

    protected static function booted(): void
    {
        static::created(function ($s) {
            if (! $s->session_uid) {
                $s->session_uid = 'GCLS-' . str_pad((string) $s->id, 5, '0', STR_PAD_LEFT);
                $s->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'capacity' => 'integer',
            'attendance_submitted_at' => 'datetime',
        ];
    }

    public function trainingClass(): BelongsTo
    {
        return $this->belongsTo(TrainingClass::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->enrollments()->whereIn('status', ['signed_up', 'attended'])->count());
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && $this->seatsLeft() > 0 && !$this->session_date->isPast();
    }

    public function instructorUser() { return $this->belongsTo(\App\Models\User::class, 'assigned_instructor_id'); }
    public function submittedBy() { return $this->belongsTo(\App\Models\User::class, 'attendance_submitted_by'); }
}
