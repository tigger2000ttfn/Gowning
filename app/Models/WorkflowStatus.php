<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class WorkflowStatus extends Model
{
    use Auditable;

    protected $fillable = ['domain', 'key', 'label', 'color', 'sort', 'is_active', 'is_system'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean', 'sort' => 'integer'];
    }

    /** label for a domain+key, DB override first, else the provided fallback. */
    public static function labelFor(string $domain, string $key, ?string $fallback = null): string
    {
        $row = static::resolve($domain, $key);
        return $row?->label ?? ($fallback ?? ucwords(str_replace('_', ' ', $key)));
    }

    /** color for a domain+key, DB override first, else the provided fallback. */
    public static function colorFor(string $domain, string $key, ?string $fallback = null): string
    {
        $row = static::resolve($domain, $key);
        return $row?->color ?? ($fallback ?? '#888888');
    }

    protected static array $cache = [];

    protected static function resolve(string $domain, string $key): ?WorkflowStatus
    {
        $ck = $domain . ':' . $key;
        if (array_key_exists($ck, static::$cache)) return static::$cache[$ck];
        try {
            $row = static::where('domain', $domain)->where('key', $key)->first();
        } catch (\Throwable $e) {
            $row = null; // table not migrated yet
        }
        return static::$cache[$ck] = $row;
    }

    /** Clear the in-request resolve cache (call after editing labels in Settings). */
    public static function flushCache(): void
    {
        static::$cache = [];
    }
}
