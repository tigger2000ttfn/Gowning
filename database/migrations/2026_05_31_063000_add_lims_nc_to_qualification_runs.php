<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The QUAL REFERENCE NC number (a fail's nonconformance) flows from the LIMS worklist onto the run,
 * with a resolved TrackWise link if the NC is in the NC Catalog. NEW migration (does not edit the run
 * table create migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qualification_runs')) return;
        Schema::table('qualification_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('qualification_runs', 'lims_nc_number')) $table->string('lims_nc_number')->nullable()->after('lims_synced_at');
            if (! Schema::hasColumn('qualification_runs', 'lims_nc_url')) $table->text('lims_nc_url')->nullable()->after('lims_nc_number');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('qualification_runs')) return;
        Schema::table('qualification_runs', function (Blueprint $table) {
            foreach (['lims_nc_number', 'lims_nc_url'] as $c) {
                if (Schema::hasColumn('qualification_runs', $c)) $table->dropColumn($c);
            }
        });
    }
};
