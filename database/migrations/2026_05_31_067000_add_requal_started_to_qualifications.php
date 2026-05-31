<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks when a qualified person's requalification was auto-kicked-off (30 days before due). Prevents the
 * nightly lifecycle job from re-kicking the same record every night, and records the kick-off moment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qualifications')) return;
        Schema::table('qualifications', function (Blueprint $table) {
            if (! Schema::hasColumn('qualifications', 'requal_started_at')) {
                $table->timestamp('requal_started_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('qualifications')) return;
        Schema::table('qualifications', function (Blueprint $table) {
            if (Schema::hasColumn('qualifications', 'requal_started_at')) $table->dropColumn('requal_started_at');
        });
    }
};
