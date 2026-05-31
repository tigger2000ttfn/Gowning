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
            [
                'name' => 'Notify scheduling when a class is completed',
                'trigger' => 'class_completed',
                'action' => 'notify_capability',
                'action_config' => ['capability' => 'manage_scheduling', 'title' => 'Class completed', 'message' => '{name} ({employee_id}) has completed the gowning class and can be booked for runs.'],
            ],
            [
                'name' => 'Alert QA when a nonconformance is opened',
                'trigger' => 'nc_opened',
                'action' => 'notify_capability',
                'action_config' => ['capability' => 'qa_review', 'title' => 'Nonconformance opened', 'message' => 'An NC was opened for {name} ({employee_id}). Review the excursion.'],
            ],
            [
                'name' => 'Confirm to the operator when a run passes',
                'trigger' => 'run_passed',
                'action' => 'notify_person',
                'action_config' => ['title' => 'Run passed', 'message' => 'Hi {name}, your gowning run passed. Results are recorded and pending QCM/QA review.'],
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
