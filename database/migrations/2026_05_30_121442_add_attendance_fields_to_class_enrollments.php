<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('class_enrollments', 'attended_at')) $table->timestamp('attended_at')->nullable();
            if (! Schema::hasColumn('class_enrollments', 'completed_at')) $table->timestamp('completed_at')->nullable();
            if (! Schema::hasColumn('class_enrollments', 'marked_by')) $table->foreignId('marked_by')->nullable();
            if (! Schema::hasColumn('class_enrollments', 'attendance_note')) $table->string('attendance_note')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('class_enrollments', function (Blueprint $table) {
            foreach (['attended_at', 'completed_at', 'marked_by', 'attendance_note'] as $c) {
                if (Schema::hasColumn('class_enrollments', $c)) $table->dropColumn($c);
            }
        });
    }
};
