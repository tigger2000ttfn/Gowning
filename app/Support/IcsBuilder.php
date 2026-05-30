<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

/**
 * Builds a robust RFC 5545 .ics using spatie/icalendar-generator, which handles
 * line-folding, escaping, timezones, and alarms correctly (vs a hand-rolled string).
 * Outlook, Teams, Google, and Apple Calendar all accept the resulting .ics.
 */
class IcsBuilder
{
    public static function event(
        string $uid,
        string $title,
        CarbonInterface $start,
        ?CarbonInterface $end = null,
        ?string $description = null,
        ?string $location = null,
        ?int $reminderMinutes = null,
    ): string {
        $end = $end ?? $start->copy()->addHour();

        $event = Event::create($title)
            ->uniqueIdentifier($uid . '@matcastellas.gowning')
            ->startsAt($start)
            ->endsAt($end);

        if ($description) $event->description($description);
        if ($location) $event->address($location);
        if ($reminderMinutes !== null) {
            $event->alertMinutesBefore(max(0, (int) $reminderMinutes), $title);
        }

        return Calendar::create('MATC Gowning Qualification')
            ->event($event)
            ->get();
    }
}
