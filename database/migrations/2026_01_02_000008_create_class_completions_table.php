<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->nullable()->constrained('personnel')->nullOnDelete();
            $table->string('employee_id');          // raw key from import, for matching
            $table->string('class_name');
            $table->date('completion_date');
            $table->string('source')->default('lms'); // lms | manual
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->timestamps();
            // dedup key for idempotent re-imports
            $table->unique(['employee_id', 'class_name', 'completion_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_completions');
    }
};
