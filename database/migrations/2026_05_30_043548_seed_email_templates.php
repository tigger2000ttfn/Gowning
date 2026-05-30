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

        $templates = [
            ['key' => 'run_scheduled', 'name' => 'Run Scheduled',
             'subject' => 'Your gowning qualification run is scheduled',
             'body_html' => $p('Hi {name},') . $p('You are booked for a cleanroom qualification run on <strong>{date}</strong>.') . $p($btn)],
            ['key' => 'run_result', 'name' => 'Run Result Recorded',
             'subject' => 'Your qualification run result',
             'body_html' => $p('Hi {name},') . $p('Your qualification run result has been recorded. Please sign in to review the outcome and any next steps.')],
            ['key' => 'due_soon', 'name' => 'Qualification Due Soon',
             'subject' => 'Your gowning qualification is due soon',
             'body_html' => $p('Hi {name},') . $p('Your gowning qualification is due on <strong>{due_date}</strong>. Please schedule your requalification run to stay current.') . $p($btn)],
            ['key' => 'lapsed', 'name' => 'Qualification Lapsed',
             'subject' => 'Your gowning qualification has lapsed',
             'body_html' => $p('Hi {name},') . $p('Your gowning qualification has lapsed and now requires full requalification. Please contact your supervisor or QC Micro to schedule.')],
            ['key' => 'qualified', 'name' => 'Qualified Confirmation',
             'subject' => 'You are now gowning qualified',
             'body_html' => $p('Hi {name},') . $p('Congratulations. Your cleanroom gowning qualification is complete and valid until <strong>{due_date}</strong>.')],
            ['key' => 'run_requested', 'name' => 'Run Requested (Approver)',
             'subject' => 'A qualification run slot was requested',
             'body_html' => $p('A run slot reservation was requested by <strong>{name}</strong> ({employee_id}). Please review and approve in the scheduling queue.')],
        ];

        foreach ($templates as $t) {
            if (! DB::table('email_templates')->where('key', $t['key'])->exists()) {
                DB::table('email_templates')->insert(array_merge($t, [
                    'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('email_templates')->whereIn('key', [
            'run_scheduled', 'run_result', 'due_soon', 'lapsed', 'qualified', 'run_requested',
        ])->delete();
    }
};
