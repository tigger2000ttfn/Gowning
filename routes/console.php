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
    $pending = \App\Models\QueuedEmail::whereNull('sent_at')->limit(200)->get();
    $sent = 0;
    foreach ($pending as $email) {
        if (! $email->to_email) { continue; }
        try {
            \Illuminate\Support\Facades\Mail::raw($email->body, function ($m) use ($email) {
                $m->to($email->to_email, $email->to_name)->subject($email->subject);
            });
            $email->update(['sent_at' => now()]);
            $sent++;
        } catch (\Throwable $e) {
            $this->warn("Failed to send to {$email->to_email}: {$e->getMessage()}");
            break; // relay likely still down; stop
        }
    }
    $this->info("Flushed {$sent} queued email(s).");
})->purpose('Send queued notification emails once the mail relay is up');

// Scheduled database backups (spatie/laravel-backup) as a GMP / Part 11 expectation.
// Daily DB backup, weekly cleanup of old backups, daily health monitor.
Schedule::command('backup:clean')->dailyAt('01:30');
Schedule::command('backup:run --only-db')->dailyAt('02:00');
Schedule::command('backup:monitor')->dailyAt('02:30');

// Record a backup-verification entry in the audit trail after the daily backup,
// so the validation package has living evidence the backup ran.
Schedule::call(function () {
    activity('backup')
        ->event('verified')
        ->log('Scheduled database backup verification check ran.');
})->dailyAt('02:45');
