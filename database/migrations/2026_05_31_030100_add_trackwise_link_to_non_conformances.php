<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the resolved TrackWise link + workflow status on each nonconformance, backfilled from the
 * NC catalog so the failed-run record, QA fail screen, and Status Board failed lane can show a
 * clickable NC link and whether the NC is still open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('non_conformances', function (Blueprint $table) {
            if (! Schema::hasColumn('non_conformances', 'trackwise_url')) {
                $table->text('trackwise_url')->nullable();
            }
            if (! Schema::hasColumn('non_conformances', 'trackwise_status')) {
                $table->string('trackwise_status')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('non_conformances', function (Blueprint $table) {
            foreach (['trackwise_url', 'trackwise_status'] as $c) {
                if (Schema::hasColumn('non_conformances', $c)) $table->dropColumn($c);
            }
        });
    }
};
