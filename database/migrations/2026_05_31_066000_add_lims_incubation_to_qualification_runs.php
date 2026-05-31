<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the LIMS incubation timeline on the run so the Active Runs / Lab Review pages can show the live
 * incubation detail (incubator, start/end/due) and a days-left indicator without re-reading the catalog.
 * Populated by WorklistSync from the linked worklist's incubation fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qualification_runs')) return;
        Schema::table('qualification_runs', function (Blueprint $table) {
            foreach ([
                'lims_inc1_incubator', 'lims_inc1_start', 'lims_inc1_end',
                'lims_inc2_incubator', 'lims_inc2_start', 'lims_inc2_end', 'lims_inc_due',
            ] as $col) {
                if (! Schema::hasColumn('qualification_runs', $col)) {
                    $table->string($col)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('qualification_runs')) return;
        Schema::table('qualification_runs', function (Blueprint $table) {
            foreach ([
                'lims_inc1_incubator', 'lims_inc1_start', 'lims_inc1_end',
                'lims_inc2_incubator', 'lims_inc2_start', 'lims_inc2_end', 'lims_inc_due',
            ] as $col) {
                if (Schema::hasColumn('qualification_runs', $col)) $table->dropColumn($col);
            }
        });
    }
};
