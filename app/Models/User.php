<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Enums\Role;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\GqsActivityLog;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{

    protected static function booted(): void
    {
        static::created(function ($user) {
            if (($user->approval_status ?? null) === 'pending') {
                \App\Services\Notifier::toCapability(
                    \App\Enums\Capability::ManageUsers,
                    'New Account Pending Approval',
                    "{$user->name} ({$user->email}) requested access.",
                    \App\Filament\Admin\Resources\UserResource::getUrl(),
                );
            }
        });
    }
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Auditable, HasFactory, Notifiable, GqsActivityLog;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active',
        'approval_status', 'approved_at', 'approved_by',
        'team', 'is_team_manager',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'is_team_manager' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Filament panel access: active AND approved.
     * Part 11: self-registered accounts stay out of the admin panel until an
     * administrator approves them. Set APP self-approval only if QA permits.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->approval_status === 'approved';
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    public function isStaff(): bool
    {
        return $this->role?->isStaff() ?? false;
    }

    public function personnel(): HasOne
    {
        return $this->hasOne(Personnel::class);
    }

    public function hasCapability(\App\Enums\Capability $cap): bool
    {
        return \App\Models\RoleCapability::roleHas($this->role, $cap);
    }
}
