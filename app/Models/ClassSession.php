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
        'training_class_id', 'session_date', 'start_time', 'end_time',
        'location', 'instructor', 'capacity', 'status', 'assigned_instructor_id',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'capacity' => 'integer',
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
}
