<?php

namespace App\Services;

use App\Enums\Capability;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;

/**
 * Sends Filament database notifications to all users holding a given capability.
 * This is what makes the header bell live (new run requests, failed runs, etc.).
 */
class Notifier
{
    public static function toCapability(Capability $cap, string $title, string $body, ?string $url = null, string $color = 'warning'): void
    {
        $recipients = User::where('is_active', true)->get()
            ->filter(fn ($u) => $u->hasCapability($cap));

        if ($recipients->isEmpty()) {
            return;
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-bell')
            ->color($color);

        if ($url) {
            $notification->actions([
                Action::make('view')->label('View')->url($url)->markAsRead(),
            ]);
        }

        foreach ($recipients as $user) {
            $notification->sendToDatabase($user);
        }
    }
}
