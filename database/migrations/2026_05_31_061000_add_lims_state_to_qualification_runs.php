<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LIMS-derived display/state on a run, populated by WorklistSync when a worklist is linked. These let
 * a card/list show "LIMS: Pass - Authorized - Incubation Complete" and let the QCM see the data before
 * building the cover page. NEW migration (never edits the qualification_runs create migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qualification_runs')) return;
        Schema::table('qualification_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('qualification_runs', 'lims_evaluation')) {
                $table->string('lims_evaluation')->nullable()->after('lims_worklist_id');     // Pass / Fail / blank
            }
            if (! Schema::hasColumn('qualification_runs', 'lims_sample_status')) {
                $table->string('lims_sample_status')->nullable()->after('lims_evaluation');    // A/C/I/P/X
            }
            if (! Schema::hasColumn('qualification_runs', 'lims_inc_status')) {
                $table->string('lims_inc_status')->nullable()->after('lims_sample_status');    // A/C/I/P/X
            }
            if (! Schema::hasColumn('qualification_runs', 'lims_all_final')) {
                $table->boolean('lims_all_final')->nullable()->after('lims_inc_status');
            }
            if (! Schema::hasColumn('qualification_runs', 'lims_qcm_ready')) {
                $table->boolean('lims_qcm_ready')->default(false)->after('lims_all_final');
            }
            if (! Schema::hasColumn('qualification_runs', 'lims_synced_at')) {
                $table->timestamp('lims_synced_at')->nullable()->after('lims_qcm_ready');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('qualification_runs')) return;
        Schema::table('qualification_runs', function (Blueprint $table) {
            foreach (['lims_evaluation', 'lims_sample_status', 'lims_inc_status', 'lims_all_final', 'lims_qcm_ready', 'lims_synced_at'] as $c) {
                if (Schema::hasColumn('qualification_runs', $c)) $table->dropColumn($c);
            }
        });
    }
};
