<?php

namespace Database\Seeders;

use App\Models\AutomationRule;
use Illuminate\Database\Seeder;

class AutomationRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'name' => 'Alert QA when a run fails',
                'trigger' => 'run_failed',
                'action' => 'notify_capability',
                'action_config' => ['capability' => 'qa_review', 'title' => 'Run failed', 'message' => '{name} ({employee_id}) had a failed qualification run. Review for determination.'],
            ],
            [
                'name' => 'Announce new qualifications',
                'trigger' => 'qualified',
                'action' => 'post_announcement',
                'action_config' => ['title' => 'Newly qualified', 'message' => '{name} is now qualified for cleanroom gowning.'],
            ],
            [
                'name' => 'Remind people their qualification is due soon',
                'trigger' => 'due_soon',
                'action' => 'notify_person',
                'action_config' => ['title' => 'Qualification due soon', 'message' => 'Hi {name}, your gowning qualification is coming due. Please schedule your run.'],
            ],
            [
                'name' => 'Flag QA when a qualification lapses',
                'trigger' => 'lapsed',
                'action' => 'notify_capability',
                'action_config' => ['capability' => 'manage_scheduling', 'title' => 'Qualification lapsed', 'message' => '{name} ({employee_id}) has lapsed and needs requalification.'],
            ],
        ];

        foreach ($rules as $r) {
            AutomationRule::firstOrCreate(
                ['name' => $r['name']],
                array_merge($r, ['is_enabled' => true])
            );
        }
    }
}
