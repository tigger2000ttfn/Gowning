<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        // Run pipeline statuses (mirror WorkflowStage label() + color()), all system keys
        $run = [
            ['class_pending', 'Class Pending', '#9A9AA4'],
            ['class_complete', 'Class Complete', '#6B2C91'],
            ['run_scheduled', 'Run Scheduled', '#1F6FB2'],
            ['run_performed', 'Run Performed', '#2A7DB5'],
            ['incubating', 'Incubating', '#B8860B'],
            ['awaiting_results', 'Awaiting Results', '#C79A2E'],
            ['results_released', 'Results Released', '#0E8A6E'],
            ['qa_review', 'QA Review', '#A4123F'],
            ['qa_signoff', 'QA Sign-off (Complete)', '#2E7D5B'],
            ['failed', 'Failed, QA Determination', '#C8102E'],
        ];
        // Classroom statuses (mirror Class Board lanes), system keys
        $class = [
            ['signed_up', 'Signed Up', '#1F6FB2'],
            ['attended', 'Attended', '#C79A2E'],
            ['completed', 'Completed', '#2E7D5B'],
            ['no_show', 'No-Show', '#C8102E'],
        ];

        $rows = [];
        foreach ($run as $i => [$key, $label, $color]) {
            $rows[] = ['domain' => 'run', 'key' => $key, 'label' => $label, 'color' => $color,
                'sort' => $i, 'is_active' => true, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($class as $i => [$key, $label, $color]) {
            $rows[] = ['domain' => 'class', 'key' => $key, 'label' => $label, 'color' => $color,
                'sort' => $i, 'is_active' => true, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now];
        }

        foreach ($rows as $r) {
            $exists = DB::table('workflow_statuses')->where('domain', $r['domain'])->where('key', $r['key'])->exists();
            if (! $exists) DB::table('workflow_statuses')->insert($r);
        }
    }
    public function down(): void
    {
        DB::table('workflow_statuses')->truncate();
    }
};
