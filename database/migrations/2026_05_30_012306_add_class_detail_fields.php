<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_classes', function (Blueprint $table) {
            if (! Schema::hasColumn('training_classes', 'default_capacity')) $table->integer('default_capacity')->default(20);
            if (! Schema::hasColumn('training_classes', 'duration_minutes')) $table->integer('duration_minutes')->nullable();
            if (! Schema::hasColumn('training_classes', 'default_location')) $table->string('default_location')->nullable();
            if (! Schema::hasColumn('training_classes', 'default_instructor')) $table->string('default_instructor')->nullable();
            if (! Schema::hasColumn('training_classes', 'validity_months')) $table->integer('validity_months')->nullable();
            if (! Schema::hasColumn('training_classes', 'category')) $table->string('category')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('training_classes', fn (Blueprint $t) =>
            $t->dropColumn(['default_capacity','duration_minutes','default_location','default_instructor','validity_months','category']));
    }
};
