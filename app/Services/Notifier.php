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

    /**
     * Notify a specific person: in-app (to their linked user account if any) plus a
     * queued email row (held until the mail relay is up, then flushed).
     */
    public function toPersonnel(?\App\Models\Personnel $personnel, string $title, string $body, ?\App\Enums\NotificationEvent $event = null): void
    {
        if (! $personnel) {
            return;
        }

        $userId = $personnel->user_id;
        $wantsInApp = true;
        $wantsEmail = true;
        if ($event && $userId) {
            $wantsInApp = \App\Models\NotificationPreference::wants($userId, $event, 'in_app');
            $wantsEmail = \App\Models\NotificationPreference::wants($userId, $event, 'email');
        }

        // in-app notification to their linked user, if any (strip any HTML from the body,
        // since templates store HTML which is fine for email but shows tags in-app)
        if ($userId && $wantsInApp) {
            $user = User::find($userId);
            if ($user) {
                $inApp = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $body)), ENT_QUOTES));
                Notification::make()->title($title)->body($inApp)
                    ->icon('heroicon-o-calendar-days')->color('success')
                    ->sendToDatabase($user);
            }
        }

        // queue an email for when the relay is ready
        if ($wantsEmail) {
            \App\Models\QueuedEmail::create([
                'to_email' => $personnel->email,
                'to_name' => $personnel->full_name,
                'subject' => $title,
                'body' => $body,
            ]);
        }
    }
}
