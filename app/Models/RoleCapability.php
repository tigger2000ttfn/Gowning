<?php

namespace App\Models;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RoleCapability extends Model
{
    use Auditable;

    protected $fillable = ['role', 'capability'];

    /** Does this role have this capability? Super User always yes. Cached. */
    public static function roleHas(?Role $role, Capability $cap): bool
    {
        if (! $role) return false;
        if ($role->isSuperUser()) return true;

        $map = Cache::rememberForever('role_caps', function () {
            return static::all()->groupBy('role')->map(fn ($g) => $g->pluck('capability')->all())->all();
        });

        return in_array($cap->value, $map[$role->value] ?? [], true);
    }

    public static function flush(): void
    {
        Cache::forget('role_caps');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }
}
