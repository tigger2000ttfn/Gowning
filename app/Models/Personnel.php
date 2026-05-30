<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\GqsActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Personnel extends Model
{
    use Auditable, SoftDeletes, GqsActivityLog;

    protected $table = 'personnel';

    protected $fillable = [
        'employee_id', 'first_name', 'last_name', 'email',
        'phone', 'shift', 'supervisor', 'hire_date', 'badge_id', 'notes',
        'department', 'job_title', 'is_active', 'user_id',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'hire_date' => 'date'];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function qualification(): HasOne
    {
        return $this->hasOne(Qualification::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(QualificationRun::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function classCompletions(): HasMany
    {
        return $this->hasMany(ClassCompletion::class);
    }

    /** Whether this person has completed the prerequisite gowning class. */
    public function hasGowningClass(): bool
    {
        return $this->classCompletions()->exists();
    }
}
