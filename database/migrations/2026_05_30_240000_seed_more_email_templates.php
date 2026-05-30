<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $p = fn ($s) => '<p style="margin:0 0 14px;">' . $s . '</p>';
        $btn = 'Open the Gowning Qualification System to view details.';

        // A broad library of workflow templates. Seeded DISABLED so they can be
        // turned on as needed from Manage > Email Templates. Tokens available:
        // {name} {employee_id} {date} {time} {due_date} {class} {stage} {result}
        // {trainer} {reset_link} {worklist} {veeva} {nc_number}.
        $templates = [
            // ---- Classroom ----
            ['class_signup_confirmation', 'Class Sign-Up Confirmation',
             'You are signed up for a gowning class',
             $p('Hi {name},') . $p('You are signed up for <strong>{class}</strong> on <strong>{date}</strong> at {time}. ' . $btn)],
            ['class_reminder', 'Class Reminder',
             'Reminder: your gowning class is coming up',
             $p('Hi {name},') . $p('This is a reminder that <strong>{class}</strong> is scheduled for <strong>{date}</strong> at {time}.')],
            ['class_rescheduled', 'Class Rescheduled',
             'Your gowning class has been rescheduled',
             $p('Hi {name},') . $p('Your gowning class has been moved to <strong>{date}</strong> at {time}. No action is needed; your seat moved with it.')],
            ['class_cancelled', 'Class Cancelled',
             'Your gowning class was cancelled',
             $p('Hi {name},') . $p('The gowning class on {date} was cancelled. Please sign up for another available session.')],
            ['class_attendance_submitted', 'Class Attendance Submitted (QA)',
             'Class attendance is ready for QA review',
             $p('Attendance for <strong>{class}</strong> ({date}) was submitted by {trainer} and is awaiting QA classroom sign-off.')],
            ['class_completed', 'Classroom Completed',
             'Your gowning classroom training is complete',
             $p('Hi {name},') . $p('Your gowning classroom training for <strong>{class}</strong> has been QA-approved. You may now be scheduled for cleanroom qualification runs.')],

            // ---- Run scheduling / performance ----
            ['run_reminder', 'Run Day Reminder',
             'Reminder: your qualification run is coming up',
             $p('Hi {name},') . $p('Reminder: you are booked for a cleanroom qualification run on <strong>{date}</strong> at {time}.')],
            ['run_rescheduled', 'Run Day Rescheduled',
             'Your qualification run has been rescheduled',
             $p('Hi {name},') . $p('Your qualification run has moved to <strong>{date}</strong>. Your booking moved with it.')],
            ['run_day_cancelled', 'Run Day Cancelled',
             'Your qualification run day was cancelled',
             $p('Hi {name},') . $p('The run day on {date} was cancelled. You have been returned to the scheduling queue and will be rebooked.')],
            ['run_performed', 'Run Performed (Incubating)',
             'Your qualification run was performed',
             $p('Hi {name},') . $p('Your qualification run on {date} was recorded (worklist {worklist}) and samples are incubating. Results follow once plates are read.')],
            ['run_no_show', 'Run No-Show',
             'Missed qualification run',
             $p('Hi {name},') . $p('You were marked as a no-show for the qualification run on {date}. Please rebook to continue your qualification.')],

            // ---- Results / lab ----
            ['incubation_complete', 'Incubation Complete (Awaiting Results)',
             'Incubation complete, awaiting results',
             $p('Incubation has completed for <strong>{name}</strong> ({employee_id}). Results are ready to be entered in Lab Review.')],
            ['results_released', 'Results Released',
             'Qualification results released',
             $p('Hi {name},') . $p('Results for your qualification run have been released and are moving to QA review.')],
            ['results_failed', 'Run Failed / Excursion',
             'Action needed: qualification run excursion',
             $p('A qualification run for <strong>{name}</strong> ({employee_id}) did not meet specification. A nonconformance ({nc_number}) and QA determination are required.')],

            // ---- QA ----
            ['qa_review_pending', 'QA Review Pending',
             'A qualification is awaiting QA sign-off',
             $p('A qualification for <strong>{name}</strong> ({employee_id}) is in the QA Sign-off queue and awaiting review.')],
            ['qa_signed_off', 'QA Sign-Off Complete',
             'Qualification QA sign-off complete',
             $p('Hi {name},') . $p('QA has signed off your gowning qualification. You are qualified through <strong>{due_date}</strong>.')],
            ['qa_determination', 'QA Determination (Failure)',
             'QA determination on a failed run',
             $p('Hi {name},') . $p('QA has reviewed your failed qualification run and set the path forward. Please sign in for the required next steps.')],
            ['qa_assigned', 'QA Reviewer Assigned',
             'You have been assigned a QA sign-off',
             $p('You have been assigned ownership of a pending QA sign-off for <strong>{name}</strong> ({employee_id}).')],

            // ---- Account / system ----
            ['password_reset', 'Password Reset',
             'Reset your Gowning Qualification System password',
             $p('Hi {name},') . $p('A password reset was requested for your account. Use the link below to set a new password. If you did not request this, ignore this email.') . $p('{reset_link}')],
            ['password_expiring', 'Password Expiring',
             'Your password is expiring soon',
             $p('Hi {name},') . $p('Your Gowning Qualification System password will expire soon. Please sign in and change it to avoid losing access.')],
            ['account_pending', 'New Account Pending (Admin)',
             'A new account is awaiting approval',
             $p('A new account for <strong>{name}</strong> ({employee_id}) is awaiting administrator approval.')],
            ['account_approved', 'Account Approved',
             'Your account has been approved',
             $p('Hi {name},') . $p('Your Gowning Qualification System account has been approved. You can now sign in.')],

            // ---- Messaging / status ----
            ['new_message', 'New Message',
             'You have a new message',
             $p('Hi {name},') . $p('You have a new message in the Gowning Qualification System. ' . $btn)],
            ['announcement', 'Announcement',
             'Gowning Qualification System announcement',
             $p('Hi {name},') . $p('{stage}')],
            ['status_changed', 'Workflow Status Changed',
             'Your qualification status has changed',
             $p('Hi {name},') . $p('Your qualification status changed to <strong>{stage}</strong>. ' . $btn)],
            ['overdue', 'Qualification Overdue',
             'Your gowning qualification is overdue',
             $p('Hi {name},') . $p('Your gowning qualification is overdue as of <strong>{due_date}</strong>. Cleanroom access may be revoked until you requalify.')],
        ];

        foreach ($templates as $t) {
            [$key, $name, $subject, $body] = $t;
            if (! DB::table('email_templates')->where('key', $key)->exists()) {
                DB::table('email_templates')->insert([
                    'key' => $key, 'name' => $name, 'subject' => $subject, 'body_html' => $body,
                    'is_enabled' => false, // off by default; turn on as needed
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('email_templates')->whereIn('key', [
            'class_signup_confirmation','class_reminder','class_rescheduled','class_cancelled',
            'class_attendance_submitted','class_completed','run_reminder','run_rescheduled',
            'run_day_cancelled','run_performed','run_no_show','incubation_complete','results_released',
            'results_failed','qa_review_pending','qa_signed_off','qa_determination','qa_assigned',
            'password_reset','password_expiring','account_pending','account_approved','new_message',
            'announcement','status_changed','overdue',
        ])->delete();
    }
};
