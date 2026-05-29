<?php

namespace App\Models;

use App\Enums\QualificationStatus;
use App\Enums\QualificationType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Qualification extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'personnel_id', 'type', 'status', 'runs_required',
        'runs_completed', 'qualified_date', 'due_date',
    ];

    protected function casts(): array
    {
        return [
            'type' => QualificationType::class,
            'status' => QualificationStatus::class,
            'runs_required' => 'integer',
            'runs_completed' => 'integer',
            'qualified_date' => 'date',
            'due_date' => 'date',
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
}
