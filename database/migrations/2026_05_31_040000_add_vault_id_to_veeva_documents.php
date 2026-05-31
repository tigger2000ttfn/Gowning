<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds vault_id to veeva_documents. The column was added to the original create migration after it
 * had already run on some environments, so it was never created there. This corrective migration
 * adds it idempotently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('veeva_documents') && ! Schema::hasColumn('veeva_documents', 'vault_id')) {
            Schema::table('veeva_documents', function (Blueprint $table) {
                $table->string('vault_id')->nullable()->after('doc_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('veeva_documents') && Schema::hasColumn('veeva_documents', 'vault_id')) {
            Schema::table('veeva_documents', function (Blueprint $table) {
                $table->dropColumn('vault_id');
            });
        }
    }
};
