<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');               // e.g. "Aseptic Gowning Qualification Class"
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_gowning_prerequisite')->default(true); // counts toward initial-run prereq
            $table->boolean('is_published')->default(true);            // visible on public page
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void { Schema::dropIfExists('training_classes'); }
};
