<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Auditable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Auditable, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active',
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
        ];
    }

    /** Filament panel access: active users only. */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    public function isStaff(): bool
    {
        return $this->role?->isStaff() ?? false;
    }
}
