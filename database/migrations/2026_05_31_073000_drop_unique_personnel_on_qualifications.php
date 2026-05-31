<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the obsolete UNIQUE(personnel_id) constraint on qualifications.
 *
 * The original 2026_01_02 schema assumed one qualification per person. The system has since moved to a
 * per-cycle model where EACH qualification cycle (initial, then each annual requal) is its own row,
 * linked by parent_qualification_id / cycle_number and resolved via Qualification::currentFor() (which
 * picks the active, non-superseded, highest-cycle row). Multiple rows per personnel_id are therefore
 * required and correct. The leftover unique constraint was causing duplicate-key failures (23505) when
 * backfilling a person who has more than one historic worklist (e.g. an initial plus annual requals).
 *
 * We keep a non-unique index on personnel_id for lookup performance.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the unique constraint/index if present. Postgres names it <table>_<column>_unique.
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Use IF EXISTS so this is safe whether the constraint exists as a constraint or an index.
            DB::statement('ALTER TABLE qualifications DROP CONSTRAINT IF EXISTS qualifications_personnel_id_unique');
            DB::statement('DROP INDEX IF EXISTS qualifications_personnel_id_unique');
        } else {
            // MySQL/SQLite: drop the unique index by its conventional name if it exists.
            try {
                Schema::table('qualifications', function (Blueprint $table) {
                    $table->dropUnique('qualifications_personnel_id_unique');
                });
            } catch (\Throwable $e) {
                // already gone / different driver - ignore
            }
        }

        // Keep a plain index for lookups (currentFor / per-person queries) if not already present.
        try {
            Schema::table('qualifications', function (Blueprint $table) {
                $table->index('personnel_id', 'qualifications_personnel_id_index');
            });
        } catch (\Throwable $e) {
            // index already exists - ignore
        }
    }

    public function down(): void
    {
        // Intentionally NOT restoring the unique constraint: the per-cycle model relies on multiple
        // qualification rows per person, so re-adding UNIQUE(personnel_id) would break the application
        // and could fail against existing multi-cycle data. Dropping the plain index is enough.
        try {
            Schema::table('qualifications', function (Blueprint $table) {
                $table->dropIndex('qualifications_personnel_id_index');
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
