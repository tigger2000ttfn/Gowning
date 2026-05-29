<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualification_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->foreignId('qualification_id')->nullable()->constrained('qualifications')->nullOnDelete();
            $table->foreignId('run_slot_id')->nullable()->constrained('run_slots')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->date('run_date');
            $table->string('result');                    // pass | fail
            $table->string('cycle_type')->default('initial'); // type of cycle this run counted toward
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            // electronic signature (Part 11)
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_meaning')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualification_runs');
    }
};
