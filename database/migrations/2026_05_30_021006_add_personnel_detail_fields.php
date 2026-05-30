<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            if (! Schema::hasColumn('personnel', 'phone')) $table->string('phone')->nullable();
            if (! Schema::hasColumn('personnel', 'shift')) $table->string('shift')->nullable();
            if (! Schema::hasColumn('personnel', 'supervisor')) $table->string('supervisor')->nullable();
            if (! Schema::hasColumn('personnel', 'hire_date')) $table->date('hire_date')->nullable();
            if (! Schema::hasColumn('personnel', 'badge_id')) $table->string('badge_id')->nullable();
            if (! Schema::hasColumn('personnel', 'notes')) $table->text('notes')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('personnel', fn (Blueprint $t) =>
            $t->dropColumn(['phone', 'shift', 'supervisor', 'hire_date', 'badge_id', 'notes']));
    }
};
