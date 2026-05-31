<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->after('decided_at');
            }
        });
        Schema::table('class_enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('class_enrollments', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'acknowledged_at')) {
                $table->dropColumn('acknowledged_at');
            }
        });
        Schema::table('class_enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('class_enrollments', 'acknowledged_at')) {
                $table->dropColumn('acknowledged_at');
            }
        });
    }
};
