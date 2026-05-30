<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Class session attendance: draft until the trainer submits the signed roster.
        Schema::table('class_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('class_sessions', 'attendance_submitted_at')) {
                $table->timestamp('attendance_submitted_at')->nullable();
            }
            if (! Schema::hasColumn('class_sessions', 'attendance_submitted_by')) {
                $table->unsignedBigInteger('attendance_submitted_by')->nullable();
            }
        });

        // Run day attendance: draft until submitted (worklists verified), then locked.
        Schema::table('run_slots', function (Blueprint $table) {
            if (! Schema::hasColumn('run_slots', 'attendance_submitted_at')) {
                $table->timestamp('attendance_submitted_at')->nullable();
            }
            if (! Schema::hasColumn('run_slots', 'attendance_submitted_by')) {
                $table->unsignedBigInteger('attendance_submitted_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            foreach (['attendance_submitted_at', 'attendance_submitted_by'] as $c) {
                if (Schema::hasColumn('class_sessions', $c)) $table->dropColumn($c);
            }
        });
        Schema::table('run_slots', function (Blueprint $table) {
            foreach (['attendance_submitted_at', 'attendance_submitted_by'] as $c) {
                if (Schema::hasColumn('run_slots', $c)) $table->dropColumn($c);
            }
        });
    }
};
