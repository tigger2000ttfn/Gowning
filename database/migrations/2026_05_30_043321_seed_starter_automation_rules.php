<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rules = [
            ['name' => 'Alert QA when a run fails', 'trigger' => 'run_failed', 'action' => 'notify_capability',
             'action_config' => json_encode(['capability' => 'qa_review', 'title' => 'Run failed', 'message' => '{name} ({employee_id}) had a failed qualification run. Review for determination.'])],
            ['name' => 'Announce new qualifications', 'trigger' => 'qualified', 'action' => 'post_announcement',
             'action_config' => json_encode(['title' => 'Newly qualified', 'message' => '{name} is now qualified for cleanroom gowning.'])],
            ['name' => 'Remind people their qualification is due soon', 'trigger' => 'due_soon', 'action' => 'notify_person',
             'action_config' => json_encode(['title' => 'Qualification due soon', 'message' => 'Hi {name}, your gowning qualification is coming due. Please schedule your run.'])],
            ['name' => 'Flag scheduling when a qualification lapses', 'trigger' => 'lapsed', 'action' => 'notify_capability',
             'action_config' => json_encode(['capability' => 'manage_scheduling', 'title' => 'Qualification lapsed', 'message' => '{name} ({employee_id}) has lapsed and needs requalification.'])],
        ];

        foreach ($rules as $r) {
            $exists = DB::table('automation_rules')->where('name', $r['name'])->exists();
            if (! $exists) {
                DB::table('automation_rules')->insert(array_merge($r, [
                    'is_enabled' => true, 'run_count' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('automation_rules')->whereIn('name', [
            'Alert QA when a run fails',
            'Announce new qualifications',
            'Remind people their qualification is due soon',
            'Flag scheduling when a qualification lapses',
        ])->delete();
    }
};
