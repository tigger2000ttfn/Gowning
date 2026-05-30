<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'event', 'in_app', 'email'];
    protected function casts(): array { return ['in_app' => 'boolean', 'email' => 'boolean']; }

    /** Whether a user wants a given channel for an event (falls back to enum defaults). */
    public static function wants(int $userId, \App\Enums\NotificationEvent $event, string $channel): bool
    {
        $pref = static::where('user_id', $userId)->where('event', $event->value)->first();
        if (! $pref) {
            return $channel === 'email' ? $event->defaultEmail() : $event->defaultInApp();
        }
        return $channel === 'email' ? (bool) $pref->email : (bool) $pref->in_app;
    }
}
