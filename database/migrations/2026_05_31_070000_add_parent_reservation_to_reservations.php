<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up bookings (created when a person does only some of their runs and the rest are rescheduled,
 * including to a special session) tie back to the original booking via parent_reservation_id, so the
 * relationship is explicit everywhere (scheduler, attendance, Active Runs) rather than encoded in notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reservations', 'parent_reservation_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->foreignId('parent_reservation_id')->nullable()->after('personnel_id')
                    ->constrained('reservations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reservations', 'parent_reservation_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('parent_reservation_id');
            });
        }
    }
};
