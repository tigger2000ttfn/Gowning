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

        // When a system user is LINKED to this personnel record, propagate that link everywhere:
        //  - sync identity (email/name) so the record matches the account,
        //  - re-link any class signups + run reservations the person made under their own account
        //    (matched by email) to this personnel record so their history is unified.
        static::saved(function (Personnel $p) {
            if (! $p->wasChanged('user_id') || ! $p->user_id) return;
            $p->propagateUserLink();
        });
    }

    /** Propagate a newly linked system user across the person's identity and prior self-service records. */
    public function propagateUserLink(): void
    {
        $user = $this->user;
        if (! $user) return;

        // 1) Identity sync: adopt the account email when the personnel record has none or differs.
        if ($user->email && $this->email !== $user->email) {
            $this->forceFill(['email' => $user->email])->saveQuietly();
        }

        // 2) Re-link self-service class enrollments made under the user's email but not yet tied to this
        //    personnel record (or tied to none).
        if ($user->email) {
            \App\Models\ClassEnrollment::query()
                ->where(fn ($q) => $q->whereNull('personnel_id')->orWhere('personnel_id', '!=', $this->id))
                ->where('email', $user->email)
                ->update(['personnel_id' => $this->id]);
        }
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
