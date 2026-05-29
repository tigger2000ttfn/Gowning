<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->string('type')->default('initial');     // initial | annual (current cycle)
            $table->string('status')->default('pending');   // pending|in_progress|qualified|lapsed
            $table->unsignedSmallInteger('runs_required')->default(3);
            $table->unsignedSmallInteger('runs_completed')->default(0); // passes in current cycle
            $table->date('qualified_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('personnel_id'); // one gowning qualification record per person
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualifications');
    }
};
