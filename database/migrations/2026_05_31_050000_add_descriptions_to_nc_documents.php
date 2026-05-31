<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NC export carries a long free-text Description and a Short Description; add both. Also widen the
 * free-text-ish columns (reference_numbers, workflow_status) to TEXT so long values do not overflow
 * varchar(255) on import.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nc_documents')) return;
        Schema::table('nc_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('nc_documents', 'description')) {
                $table->text('description')->nullable()->after('sub_group');
            }
            if (! Schema::hasColumn('nc_documents', 'short_description')) {
                $table->text('short_description')->nullable()->after('description');
            }
        });

        // Widen overflow-prone columns to text (Postgres-safe alter via raw, guarded by driver).
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            foreach (['reference_numbers', 'workflow_status', 'department', 'site', 'sub_group', 'qa_approver'] as $col) {
                if (Schema::hasColumn('nc_documents', $col)) {
                    Schema::getConnection()->statement("ALTER TABLE nc_documents ALTER COLUMN \"$col\" TYPE text");
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('nc_documents')) return;
        Schema::table('nc_documents', function (Blueprint $table) {
            foreach (['description', 'short_description'] as $c) {
                if (Schema::hasColumn('nc_documents', $c)) $table->dropColumn($c);
            }
        });
    }
};
