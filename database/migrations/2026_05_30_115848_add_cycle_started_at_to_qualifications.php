<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualifications', function (Blueprint $table) {
            if (! Schema::hasColumn('qualifications', 'cycle_started_at')) {
                $table->date('cycle_started_at')->nullable()->after('due_date');
            }
        });
    }
    public function down(): void
    {
        Schema::table('qualifications', function (Blueprint $table) {
            if (Schema::hasColumn('qualifications', 'cycle_started_at')) {
                $table->dropColumn('cycle_started_at');
            }
        });
    }
};
