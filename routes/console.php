<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Time-based incubation automation: promote Incubating -> Awaiting Results daily.
Artisan::command('gqs:advance-incubation', function () {
    $moved = app(\App\Services\RunCycleAdvancer::class)->sweep();
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

// Move Completed class enrollments to Historical after a retention period (Settings: class_history_days, default 30).
Artisan::command('gqs:archive-class-completions', function () {
    $days = (int) \App\Models\Setting::get('class_history_days', 30);
    $cutoff = now()->subDays($days);
    $n = \App\Models\ClassEnrollment::where('status', 'completed')
        ->where(function ($q) use ($cutoff) {
            $q->where('completed_at', '<=', $cutoff)
              ->orWhere(function ($q2) use ($cutoff) { $q2->whereNull('completed_at')->where('updated_at', '<=', $cutoff); });
        })
        ->update(['status' => 'historical']);
    $this->info("Archived {$n} completed class enrollment(s) to Historical.");
})->purpose('Move old Completed class enrollments into the Historical lane');

Schedule::command('gqs:archive-class-completions')->dailyAt('06:15');

// Archive sweep (run side): N days after QA approval, set archived_at on QA-Approved qualifications
// so they move to the Status Board Historical lane. This is a SECONDARY historic flag only - it does
// NOT change the QA-Approved stage or the qualified status; approval is preserved.
Artisan::command('gqs:archive-qualifications', function () {
    $days = (int) \App\Models\Setting::get('qual_history_days', 30);
    $cutoff = now()->subDays($days);
    $n = \App\Models\Qualification::query()
        ->whereNull('archived_at')
        ->whereNull('superseded_at')
        ->where('workflow_stage', \App\Enums\WorkflowStage::QaSignoff->value)
        ->where(function ($q) use ($cutoff) {
            $q->where('qualified_date', '<=', $cutoff)
              ->orWhere(function ($q2) use ($cutoff) {
                  $q2->whereNull('qualified_date')->where('stage_changed_at', '<=', $cutoff);
              });
        })
        ->update(['archived_at' => now()]);
    $this->info("Archived {$n} QA-Approved qualification(s) to Historical (approval preserved).");
})->purpose('Flag old QA-Approved qualifications as archived (historic) without changing approval');

Schedule::command('gqs:archive-qualifications')->dailyAt('06:16');

// Recurring catalog backfill: re-link any NC / Veeva records to the latest catalog data nightly,
// so links/status stay fresh as new weekly exports are imported.
Artisan::command('gqs:backfill-catalogs', function () {
    $nc = 0; $vv = 0;
    if (\Illuminate\Support\Facades\Schema::hasColumn('non_conformances', 'trackwise_id')) {
        foreach (\App\Models\NonConformance::whereNotNull('trackwise_id')->where('trackwise_id', '!=', '')->get() as $rec) {
            $doc = \App\Models\NcDocument::findByNumber($rec->trackwise_id);
            if (! $doc) continue;
            $changed = false;
            if (\Illuminate\Support\Facades\Schema::hasColumn('non_conformances', 'trackwise_url') && $doc->url && $rec->trackwise_url !== $doc->url) { $rec->trackwise_url = $doc->url; $changed = true; }
            if (\Illuminate\Support\Facades\Schema::hasColumn('non_conformances', 'trackwise_status') && $doc->workflow_status && $rec->trackwise_status !== $doc->workflow_status) { $rec->trackwise_status = $doc->workflow_status; $changed = true; }
            if ($changed) { $rec->save(); $nc++; }
        }
    }
    $this->info("Catalog backfill: {$nc} NC link(s) refreshed.");
})->purpose('Nightly re-link of NC/Veeva records from the latest catalog');

Schedule::command('gqs:backfill-catalogs')->dailyAt('06:17');

// Nightly LIMS worklist sweeper: populate/advance records that ALREADY have a worklist assigned
// (linked via the run picker or created by backfill). This is the UPDATE step - it pulls the latest
// LIMS state (sample/incubation status, evaluation, QCM-ready, NC links) onto linked runs and advances
// their stage as the LIMS feed progresses. It never CREATES records (that is the manual backfill).
Artisan::command('gqs:sync-worklists', function () {
    $n = app(\App\Services\WorklistSync::class)->syncAll();
    $this->info("LIMS worklist sync: {$n} linked run(s) updated.");
})->purpose('Nightly sync of LIMS data onto runs that already have a worklist assigned');

Schedule::command('gqs:sync-worklists')->dailyAt('06:18');

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
            'subject' => 'Reminder: your gowning run on ' . $slot->slot_date->format('d M'),
            'body_html' => '<p style="margin:0 0 14px;">Hi ' . e($person->first_name ?: $person->full_name) . ',</p>'
                . '<p style="margin:0 0 14px;">This is a reminder that your cleanroom gowning qualification run is on <strong>'
                . $slot->slot_date->format('l, d M Y') . '</strong>' . ($slot->cleanroom ? ' in ' . e($slot->cleanroom) : '') . '.</p>'
                . '<p style="margin:0 0 14px;">A calendar invite is attached so you can add it to Outlook or your phone.</p>',
            'ics' => $ics,
            'ics_filename' => 'gowning-run.ics',
        ]);
        $sent++;
    }
    $this->info("Queued {$sent} run reminder(s).");
})->purpose('Send per-user run reminders with calendar invites');

Schedule::command('gqs:send-run-reminders')->dailyAt('07:00');
