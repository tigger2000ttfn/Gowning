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
        'department', 'job_title', 'is_active', 'user_id', 'lims_username',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'hire_date' => 'date'];
    }

    protected static function booted(): void
    {
        // Personnel uses soft deletes, so DB-level cascade does NOT fire on delete(). Clean up the
        // person's active bookings and enrollments here so nothing dangling shows after they're removed.
        static::deleting(function (Personnel $p) {
            if ($p->isForceDeleting()) return; // hard delete handled below
            // Cancel active class enrollments (frees seats; submitted/historical stay as the record).
            \App\Models\ClassEnrollment::where('personnel_id', $p->id)
                ->whereNotIn('status', ['cancelled', 'completed', 'historical'])
                ->update(['status' => 'cancelled']);
            // Cancel active run reservations.
            \App\Models\Reservation::where('personnel_id', $p->id)
                ->whereIn('status', ['requested', 'approved'])
                ->update(['status' => 'cancelled']);
        });

        // Hard delete (super-user purge of test people): wipe ALL of the person's data. The DB cascade
        // removes reservations/qualifications/runs; enrollments + completions are nullOnDelete, so remove
        // them explicitly to avoid orphans.
        static::forceDeleting(function (Personnel $p) {
            \App\Models\ClassEnrollment::where('personnel_id', $p->id)->delete();
            \App\Models\ClassCompletion::where('personnel_id', $p->id)->delete();
            // reservations, qualifications, qualification_runs cascade on hard delete via FK.
        });
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
