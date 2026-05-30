<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'reminder_days_before')) {
                $table->unsignedTinyInteger('reminder_days_before')->default(2);
            }
        });
    }
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'reminder_days_before')) $table->dropColumn('reminder_days_before');
        });
    }
};
