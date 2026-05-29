<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_capabilities', function (Blueprint $table) {
            $table->id();
            $table->string('role');        // Role enum value
            $table->string('capability');  // Capability enum value
            $table->timestamps();
            $table->unique(['role', 'capability']);
        });
    }

    public function down(): void { Schema::dropIfExists('role_capabilities'); }
};
