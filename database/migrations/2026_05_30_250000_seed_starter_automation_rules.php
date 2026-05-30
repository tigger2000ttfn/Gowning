<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('automation_rules')) return;
        $now = now();

        // Starter rules, all DISABLED. Each is wired to a real firing point (trigger or a
        // specific workflow stage change) and queues the matching email template. Enable the
        // ones you want and turn the template on under Manage > Email Templates.
        // [name, trigger, trigger_stage, template_key]
        $rules = [
            ['Email · Qualified',            'qualified',     null,                'qualified'],
            ['Email · Lapsed',               'lapsed',        null,                'lapsed'],
            ['Email · Due Soon',             'due_soon',      null,                'due_soon'],
            ['Email · Run Failed',           'run_failed',    null,                'results_failed'],
            ['Email · Class Completed',      'class_completed', null,              'class_completed'],
            ['Email · Run Performed',        'stage_changed', 'incubating',        'run_performed'],
            ['Email · Incubation Complete',  'stage_changed', 'awaiting_results',  'incubation_complete'],
            ['Email · Results Released',     'stage_changed', 'results_released',  'results_released'],
            ['Email · QA Review Pending',    'stage_changed', 'qa_review',         'qa_review_pending'],
        ];

        foreach ($rules as [$name, $trigger, $stage, $key]) {
            $exists = DB::table('automation_rules')
                ->where('action', 'queue_email')
                ->whereRaw("action_config::text LIKE ?", ['%"template_key":"' . $key . '"%'])
                ->exists();
            if ($exists) continue;
            DB::table('automation_rules')->insert([
                'name' => $name,
                'trigger' => $trigger,
                'trigger_stage' => $stage,
                'action' => 'queue_email',
                'action_config' => json_encode(['template_key' => $key]),
                'is_enabled' => false,
                'run_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('automation_rules')->where('name', 'LIKE', 'Email · %')->delete();
    }
};
