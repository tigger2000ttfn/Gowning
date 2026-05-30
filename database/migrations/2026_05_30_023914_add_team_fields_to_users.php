<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'team')) $table->string('team')->nullable();          // 'qcm' | 'qa' | null
            if (! Schema::hasColumn('users', 'is_team_manager')) $table->boolean('is_team_manager')->default(false);
        });
    }
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['team', 'is_team_manager']));
    }
};
