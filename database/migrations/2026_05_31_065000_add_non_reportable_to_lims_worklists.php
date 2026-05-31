<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-reportable worklist: a duplicate/abandoned worklist (e.g. an analyst created a second one for a
 * person by mistake). It still imports, but never links to a person and never drives any workflow -
 * it will never be finished/closed out. Sync, backfill, and person-matching all skip it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lims_worklists')) return;
        Schema::table('lims_worklists', function (Blueprint $table) {
            if (! Schema::hasColumn('lims_worklists', 'non_reportable')) {
                $table->boolean('non_reportable')->default(false)->index()->after('is_legacy');
            }
            if (! Schema::hasColumn('lims_worklists', 'non_reportable_reason')) {
                $table->string('non_reportable_reason')->nullable()->after('non_reportable');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lims_worklists')) return;
        Schema::table('lims_worklists', function (Blueprint $table) {
            foreach (['non_reportable', 'non_reportable_reason'] as $c) {
                if (Schema::hasColumn('lims_worklists', $c)) $table->dropColumn($c);
            }
        });
    }
};
