<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Special (one-off) run days created from a reschedule are private: they exist so an analyst can open a
 * dedicated session for a person to finish their runs, but they must NOT appear in the public self-service
 * booking queue without QCM permission. This flag marks them so the self-service / Book Run pickers can
 * exclude them while schedulers can still place bookings on them directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('run_slots', 'is_special')) {
            Schema::table('run_slots', function (Blueprint $table) {
                $table->boolean('is_special')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('run_slots', 'is_special')) {
            Schema::table('run_slots', function (Blueprint $table) {
                $table->dropColumn('is_special');
            });
        }
    }
};
