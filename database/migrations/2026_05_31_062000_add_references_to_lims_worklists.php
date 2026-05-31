<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the QUAL REFERENCE (fail NC on the EM personnel-qual sample), INC REFERENCE (routine-EM NC on
 * the incubation sample - not used for quals), and TSA CONTACT PLATE 3 to the worklist catalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lims_worklists')) return;
        Schema::table('lims_worklists', function (Blueprint $table) {
            if (! Schema::hasColumn('lims_worklists', 'qual_reference')) $table->text('qual_reference')->nullable()->after('tsa_control_3');
            if (! Schema::hasColumn('lims_worklists', 'inc_reference')) $table->text('inc_reference')->nullable()->after('storage3_start');
            if (! Schema::hasColumn('lims_worklists', 'tsa_contact_plate_3')) $table->text('tsa_contact_plate_3')->nullable()->after('tsa_contact_plate_2');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lims_worklists')) return;
        Schema::table('lims_worklists', function (Blueprint $table) {
            foreach (['qual_reference', 'inc_reference', 'tsa_contact_plate_3'] as $c) {
                if (Schema::hasColumn('lims_worklists', $c)) $table->dropColumn($c);
            }
        });
    }
};
