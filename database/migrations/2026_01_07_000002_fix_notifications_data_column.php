<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The notifications.data column must be json (not text) so Postgres can use
     * the ->> JSON operator that Filament's notification queries rely on.
     */
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) return;
        // Postgres: convert text -> jsonb safely
        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) return;
        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
    }
};
