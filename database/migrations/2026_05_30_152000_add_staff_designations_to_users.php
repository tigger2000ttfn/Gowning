<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Designations that gate who can be assigned to run-sampling vs classroom training.
            if (! Schema::hasColumn('users', 'can_sample')) {
                $table->boolean('can_sample')->default(false); // qualified to perform Qual Run sampling
            }
            if (! Schema::hasColumn('users', 'can_teach')) {
                $table->boolean('can_teach')->default(false);  // qualified to deliver classroom training
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['can_sample', 'can_teach'] as $c) {
                if (Schema::hasColumn('users', $c)) $table->dropColumn($c);
            }
        });
    }
};
