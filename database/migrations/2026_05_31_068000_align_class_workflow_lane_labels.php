<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Align the class-domain kanban lane labels with the actual class lifecycle and rename the final lane to
 * "QA Approved" (it was seeded as "Completed"). Also adds the qcm_reviewed and pending_qa lanes that were
 * missing from the original seed. Upsert-style so it is safe regardless of what is already stored, and it
 * does NOT overwrite a label an admin has since customised away from the old default "Completed".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('workflow_statuses')) return;

        $rows = [
            // key            label            color      sort
            ['signed_up',    'Scheduled',     '#1F6FB2', 1],
            ['attended',     'Attended',      '#C79A2E', 2],
            ['qcm_reviewed', 'QCM Reviewed',  '#2563EB', 3],
            ['pending_qa',   'Pending QA',    '#6B2C91', 4],
            ['completed',    'QA Approved',   '#2E7D5B', 5],
            ['no_show',      'No-Show',       '#C8102E', 6],
        ];

        foreach ($rows as [$key, $label, $color, $sort]) {
            $existing = DB::table('workflow_statuses')->where('domain', 'class')->where('key', $key)->first();
            if (! $existing) {
                DB::table('workflow_statuses')->insert([
                    'domain' => 'class', 'key' => $key, 'label' => $label, 'color' => $color,
                    'sort' => $sort, 'created_at' => now(), 'updated_at' => now(),
                ]);
                continue;
            }
            // Only fix the labels that were the old seeded defaults; never clobber a custom admin label.
            $oldDefaults = ['signed_up' => 'Signed Up', 'completed' => 'Completed'];
            $update = ['sort' => $sort, 'updated_at' => now()];
            if (isset($oldDefaults[$key]) && $existing->label === $oldDefaults[$key]) {
                $update['label'] = $label;
            }
            DB::table('workflow_statuses')->where('id', $existing->id)->update($update);
        }
    }

    public function down(): void
    {
        // No-op: lane labels are user-editable; we do not revert to the old "Completed" wording.
    }
};
