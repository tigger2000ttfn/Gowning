<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queued_emails', function (Blueprint $table) {
            if (! Schema::hasColumn('queued_emails', 'ics')) $table->text('ics')->nullable();
            if (! Schema::hasColumn('queued_emails', 'ics_filename')) $table->string('ics_filename')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('queued_emails', function (Blueprint $table) {
            foreach (['ics', 'ics_filename'] as $c) {
                if (Schema::hasColumn('queued_emails', $c)) $table->dropColumn($c);
            }
        });
    }
};
