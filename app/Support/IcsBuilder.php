<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Builds a minimal RFC 5545 .ics (iCalendar) event. Outlook, Teams, Google,
 * and Apple Calendar all accept an .ics attachment / download to add an event,
 * so this gives "add to calendar" without any Teams/Graph API integration.
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
        $fmt = fn (CarbonInterface $d) => $d->copy()->utc()->format('Ymd\THis\Z');
        $esc = fn (?string $s) => $s === null ? '' : addcslashes(str_replace(["\r\n", "\n"], '\\n', $s), ",;");

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//MATC Gowning Qualification//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid . '@matcastellas.gowning',
            'DTSTAMP:' . $fmt(now()),
            'DTSTART:' . $fmt($start),
            'DTEND:' . $fmt($end),
            'SUMMARY:' . $esc($title),
        ];
        if ($description) $lines[] = 'DESCRIPTION:' . $esc($description);
        if ($location) $lines[] = 'LOCATION:' . $esc($location);

        if ($reminderMinutes !== null) {
            $lines[] = 'BEGIN:VALARM';
            $lines[] = 'TRIGGER:-PT' . max(0, (int) $reminderMinutes) . 'M';
            $lines[] = 'ACTION:DISPLAY';
            $lines[] = 'DESCRIPTION:' . $esc($title);
            $lines[] = 'END:VALARM';
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // RFC 5545 wants CRLF line endings
        return implode("\r\n", $lines) . "\r\n";
    }
}
