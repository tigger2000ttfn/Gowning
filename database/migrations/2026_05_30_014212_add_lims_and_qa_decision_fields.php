<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualification_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('qualification_runs', 'lims_worklist_id')) $table->string('lims_worklist_id')->nullable()->after('run_slot_id');
            if (! Schema::hasColumn('qualification_runs', 'results_entered_at')) $table->timestamp('results_entered_at')->nullable();
        });
        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'lims_worklist_id')) $table->string('lims_worklist_id')->nullable();
        });
        Schema::table('qualifications', function (Blueprint $table) {
            // QA requalification recommendation on the failed/requal path
            if (! Schema::hasColumn('qualifications', 'qa_recommendation')) $table->string('qa_recommendation')->nullable();   // requal_three / requal_one
            if (! Schema::hasColumn('qualifications', 'qa_recommendation_note')) $table->text('qa_recommendation_note')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('qualification_runs', fn (Blueprint $t) => $t->dropColumn(['lims_worklist_id', 'results_entered_at']));
        Schema::table('reservations', fn (Blueprint $t) => $t->dropColumn(['lims_worklist_id']));
        Schema::table('qualifications', fn (Blueprint $t) => $t->dropColumn(['qa_recommendation', 'qa_recommendation_note']));
    }
};
