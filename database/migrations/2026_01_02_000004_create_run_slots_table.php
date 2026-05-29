<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('run_slots', function (Blueprint $table) {
            $table->id();
            $table->string('cleanroom');                 // which cleanroom / suite
            $table->date('slot_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->string('status')->default('open');   // open | closed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_slots');
    }
};
