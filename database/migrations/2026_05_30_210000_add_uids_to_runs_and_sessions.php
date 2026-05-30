<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualification_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('qualification_runs', 'run_uid')) {
                $table->string('run_uid')->nullable()->unique()->after('id');
            }
        });
        Schema::table('class_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('class_sessions', 'session_uid')) {
                $table->string('session_uid')->nullable()->unique()->after('id');
            }
        });

        // Backfill existing rows from their primary key (ids are never reused).
        foreach (DB::table('qualification_runs')->whereNull('run_uid')->pluck('id') as $id) {
            DB::table('qualification_runs')->where('id', $id)
                ->update(['run_uid' => 'QRUN-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT)]);
        }
        foreach (DB::table('class_sessions')->whereNull('session_uid')->pluck('id') as $id) {
            DB::table('class_sessions')->where('id', $id)
                ->update(['session_uid' => 'GCLS-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT)]);
        }
    }

    public function down(): void
    {
        Schema::table('qualification_runs', fn (Blueprint $t) => $t->dropColumn('run_uid'));
        Schema::table('class_sessions', fn (Blueprint $t) => $t->dropColumn('session_uid'));
    }
};
