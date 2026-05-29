<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_slot_id')->constrained('run_slots')->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->string('status')->default('requested'); // requested|approved|rejected|completed|no_show
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['run_slot_id', 'personnel_id']); // no duplicate request per slot
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
