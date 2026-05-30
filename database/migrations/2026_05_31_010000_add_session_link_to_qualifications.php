<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session-record model, step 1 (schema only, additive and inert).
 *
 * Each qualification cycle becomes its own record:
 *  - parent_qualification_id links a rerun/requal session to the session that spawned it,
 *    so a failed initial or annual requal can always be traced from its rerun.
 *  - cycle_number orders the chain (1 = the original).
 *  - superseded_at marks a cycle that is no longer the active session (kept as read-only history).
 *
 * Nothing is populated yet, so the live lookups still resolve to the single existing row.
 * Behaviour flips only once the call sites adopt Qualification::currentFor() and the QA
 * determination spawns a child (next steps).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualifications', function (Blueprint $table) {
            if (! Schema::hasColumn('qualifications', 'parent_qualification_id')) {
                $table->unsignedBigInteger('parent_qualification_id')->nullable();
                $table->index('parent_qualification_id');
            }
            if (! Schema::hasColumn('qualifications', 'cycle_number')) {
                $table->unsignedInteger('cycle_number')->default(1);
            }
            if (! Schema::hasColumn('qualifications', 'superseded_at')) {
                $table->timestamp('superseded_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('qualifications', function (Blueprint $table) {
            foreach (['parent_qualification_id', 'cycle_number', 'superseded_at'] as $c) {
                if (Schema::hasColumn('qualifications', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
