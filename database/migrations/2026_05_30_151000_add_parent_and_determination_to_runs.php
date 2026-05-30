<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualification_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('qualification_runs', 'parent_run_id')) {
                // Links a run spawned by a QA determination back to the original run it descends from.
                $table->unsignedBigInteger('parent_run_id')->nullable()->after('personnel_id')->index();
            }
            if (! Schema::hasColumn('qualification_runs', 'qa_determination')) {
                // Terminal QA outcome on this run: pass | fail_requalify | fail_retrain | redo
                $table->string('qa_determination')->nullable()->after('result');
            }
            if (! Schema::hasColumn('qualification_runs', 'qa_determined_at')) {
                $table->timestamp('qa_determined_at')->nullable();
            }
            if (! Schema::hasColumn('qualification_runs', 'is_complete')) {
                // A run is complete once QA has made its determination (terminal/historic).
                $table->boolean('is_complete')->default(false)->index();
            }
        });

        Schema::table('qualifications', function (Blueprint $table) {
            if (! Schema::hasColumn('qualifications', 'pending_parent_run_id')) {
                // When QA sends someone back for requalification, the next run they perform
                // should descend from the failed run; we stash that here until the run is created.
                $table->unsignedBigInteger('pending_parent_run_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('qualification_runs', function (Blueprint $table) {
            foreach (['parent_run_id', 'qa_determination', 'qa_determined_at', 'is_complete'] as $col) {
                if (Schema::hasColumn('qualification_runs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('qualifications', function (Blueprint $table) {
            if (Schema::hasColumn('qualifications', 'pending_parent_run_id')) {
                $table->dropColumn('pending_parent_run_id');
            }
        });
    }
};
