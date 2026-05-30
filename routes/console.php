<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Time-based incubation automation: promote Incubating -> Awaiting Results daily.
Artisan::command('gqs:advance-incubation', function () {
    $moved = app(\App\Services\IncubationAdvancer::class)->run();
    $this->info("Incubation advancer: {$moved} qualification(s) moved to Awaiting Results.");
})->purpose('Advance qualifications past incubation when the period has elapsed');

Schedule::command('gqs:advance-incubation')->dailyAt('06:00');

// Yearly lifecycle: lapse anyone past their qualification due date into a 3-run requal.
Artisan::command('gqs:advance-lifecycle', function () {
    $n = app(\App\Services\LifecycleAdvancer::class)->run();
    $this->info("Lifecycle advancer: {$n} qualification(s) lapsed into requalification.");
})->purpose('Lapse qualifications past their due date into a 3-run requalification');

Schedule::command('gqs:advance-lifecycle')->dailyAt('06:05');

// Auto-schedule: book Class-Complete people into the next available run day.
Artisan::command('gqs:auto-schedule', function () {
    $n = app(\App\Services\AutoScheduler::class)->run();
    $this->info("Auto-scheduler: {$n} qualification run(s) booked.");
})->purpose('Auto-book qualification runs into the next available run day');

Schedule::command('gqs:auto-schedule')->dailyAt('06:10');

// Flush queued emails once the mail relay (Postfix) is configured.
// Until then rows sit in queued_emails with sent_at = null.
Artisan::command('gqs:flush-emails', function () {
    if (! (bool) \App\Models\Setting::get('email_enabled', false)) {
        $this->info('Email sending is disabled in Settings; emails remain queued.');
        return;
    }
    $pending = \App\Models\QueuedEmail::whereNull('sent_at')->limit(200)->get();
    $sent = 0;
    foreach ($pending as $email) {
        if (! $email->to_email) { continue; }
        try {
            if ($email->body_html) {
                $html = view('emails.layout', [
                    'subject' => $email->subject,
                    'bodyHtml' => $email->body_html,
                ])->render();
                \Illuminate\Support\Facades\Mail::html($html, function ($m) use ($email) {
                    $m->to($email->to_email, $email->to_name)->subject($email->subject);
                    if ($email->ics) {
                        $m->attachData($email->ics, $email->ics_filename ?: 'event.ics', ['mime' => 'text/calendar']);
                    }
                });
            } else {
                // plain-text fallback, still wrapped in the branded layout
                $html = view('emails.layout', [
                    'subject' => $email->subject,
                    'bodyHtml' => nl2br(e($email->body)),
                ])->render();
                \Illuminate\Support\Facades\Mail::html($html, function ($m) use ($email) {
                    $m->to($email->to_email, $email->to_name)->subject($email->subject);
                    if ($email->ics) {
                        $m->attachData($email->ics, $email->ics_filename ?: 'event.ics', ['mime' => 'text/calendar']);
                    }
                });
            }
            $email->update(['sent_at' => now()]);
            $sent++;
        } catch (\Throwable $e) {
            $this->warn("Failed to send to {$email->to_email}: {$e->getMessage()}");
            break; // relay likely still down; stop
        }
    }
    $this->info("Flushed {$sent} queued email(s).");
})->purpose('Send queued notification emails once the mail relay is up');


// Daily due-soon scan: fire the DueSoon automation trigger for qualifications
// coming due within the configured reminder window (Settings: notify_days_before).
Artisan::command('gqs:notify-due-soon', function () {
    $days = (int) \App\Models\Setting::get('notify_days_before', 14);
    $today = now()->startOfDay();
    $until = now()->addDays($days)->endOfDay();
    $due = \App\Models\Qualification::with('personnel')
        ->where('status', 'qualified')
        ->whereNotNull('due_date')
        ->whereBetween('due_date', [$today->toDateString(), $until->toDateString()])
        ->get();
    foreach ($due as $q) {
        \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::DueSoon, [
            'personnel' => $q->personnel, 'qualification' => $q,
        ]);
    }
    $this->info("Due-soon: fired for {$due->count()} qualification(s).");
})->purpose('Fire DueSoon automation rules for qualifications coming due');

Schedule::command('gqs:notify-due-soon')->dailyAt('06:20');

// Per-user scheduled-event reminders: for each operator with an upcoming run on
// exactly their personal "remind me X days before" lead time, queue a reminder email
// with an .ics calendar invite. Runs daily; each (reservation, day) reminds once.
Artisan::command('gqs:send-run-reminders', function () {
    $sent = 0;
    $reservations = \App\Models\Reservation::with(['personnel.user', 'runSlot'])
        ->where('status', 'approved')
        ->whereHas('runSlot', fn ($q) => $q->whereDate('slot_date', '>=', now()->toDateString()))
        ->get();

    foreach ($reservations as $res) {
        $slot = $res->runSlot;
        $person = $res->personnel;
        if (! $slot || ! $person || ! $person->email) { continue; }

        $user = $person->user; // linked staff/operator account, if any
        $lead = (int) ($user?->reminder_days_before ?? 2);
        $remindOn = \Carbon\Carbon::parse($slot->slot_date->toDateString())->subDays($lead)->toDateString();
        if ($remindOn !== now()->toDateString()) { continue; }

        // respect the user's RunScheduled email preference when we know the user
        $wantsEmail = $user
            ? \App\Models\NotificationPreference::wants($user->id, \App\Enums\NotificationEvent::RunScheduled, 'email')
            : true;
        if (! $wantsEmail) { continue; }

        $start = \Carbon\Carbon::parse($slot->slot_date->toDateString() . ' ' . ($slot->start_time ?: '09:00'));
        $ics = \App\Support\IcsBuilder::event(
            'run-' . $slot->id, 'Cleanroom Gowning Qualification Run', $start,
            $slot->end_time ? \Carbon\Carbon::parse($slot->slot_date->toDateString() . ' ' . $slot->end_time) : $start->copy()->addHour(),
            'Reminder: your gowning qualification run is coming up.', $slot->cleanroom, 60
        );

        \App\Models\QueuedEmail::create([
            'to_email' => $person->email,
            'to_name' => $person->full_name,
            'subject' => 'Reminder: your gowning run on ' . $slot->slot_date->format('M j'),
            'body_html' => '<p style="margin:0 0 14px;">Hi ' . e($person->first_name ?: $person->full_name) . ',</p>'
                . '<p style="margin:0 0 14px;">This is a reminder that your cleanroom gowning qualification run is on <strong>'
                . $slot->slot_date->format('l, M j, Y') . '</strong>' . ($slot->cleanroom ? ' in ' . e($slot->cleanroom) : '') . '.</p>'
                . '<p style="margin:0 0 14px;">A calendar invite is attached so you can add it to Outlook or your phone.</p>',
            'ics' => $ics,
            'ics_filename' => 'gowning-run.ics',
        ]);
        $sent++;
    }
    $this->info("Queued {$sent} run reminder(s).");
})->purpose('Send per-user run reminders with calendar invites');

Schedule::command('gqs:send-run-reminders')->dailyAt('07:00');
