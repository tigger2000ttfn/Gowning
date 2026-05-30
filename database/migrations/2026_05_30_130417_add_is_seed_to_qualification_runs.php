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
            if (! Schema::hasColumn('qualification_runs', 'is_seed')) {
                $table->boolean('is_seed')->default(false)->after('result');
            }
        });
        // backfill: any existing run marked with the legacy seed note becomes is_seed
        if (Schema::hasColumn('qualification_runs', 'is_seed')) {
            DB::table('qualification_runs')->where('notes', 'Seeded at manual setup')->update(['is_seed' => true]);
        }
    }
    public function down(): void
    {
        Schema::table('qualification_runs', function (Blueprint $table) {
            if (Schema::hasColumn('qualification_runs', 'is_seed')) $table->dropColumn('is_seed');
        });
    }
};
