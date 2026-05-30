<?php

namespace App\Enums;

enum AutomationAction: string
{
    case NotifyCapability = 'notify_capability';  // in-app notify all holders of a capability
    case NotifyPerson     = 'notify_person';      // in-app + queued email to the affected person
    case PostAnnouncement = 'post_announcement';  // create an announcement
    case QueueEmail       = 'queue_email';        // queue an email to the affected person

    public function label(): string
    {
        return match ($this) {
            self::NotifyCapability => 'Notify a role (in-app bell)',
            self::NotifyPerson     => 'Notify the affected person (in-app + email)',
            self::PostAnnouncement => 'Post an announcement',
            self::QueueEmail       => 'Queue an email to the affected person',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
