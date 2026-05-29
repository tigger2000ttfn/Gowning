<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->foreignId('personnel_id')->nullable()->constrained('personnel')->nullOnDelete();
            $table->string('name');                 // captured at signup (public)
            $table->string('email')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('status')->default('signed_up'); // signed_up | attended | no_show | cancelled
            $table->timestamp('signed_up_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('class_enrollments'); }
};
