<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualifications', function (Blueprint $table) {
            if (! Schema::hasColumn('qualifications', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('cycle_started_at');
            }
        });
    }
    public function down(): void
    {
        Schema::table('qualifications', function (Blueprint $table) {
            if (Schema::hasColumn('qualifications', 'archived_at')) $table->dropColumn('archived_at');
        });
    }
};
