<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A legacy lock on a worklist row. Older LIMS samples (different QC_PERSONNEL_QUAL / INC_META layout)
 * are hand-fixed in the catalog; marking the row legacy means imports never touch it again, so the
 * manual edits survive re-import.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lims_worklists')) return;
        Schema::table('lims_worklists', function (Blueprint $table) {
            if (! Schema::hasColumn('lims_worklists', 'is_legacy')) {
                $table->boolean('is_legacy')->default(false)->index()->after('worklist');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lims_worklists')) return;
        Schema::table('lims_worklists', function (Blueprint $table) {
            if (Schema::hasColumn('lims_worklists', 'is_legacy')) $table->dropColumn('is_legacy');
        });
    }
};
