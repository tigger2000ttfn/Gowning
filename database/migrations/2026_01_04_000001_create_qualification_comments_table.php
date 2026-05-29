<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualification_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qualification_id')->constrained('qualifications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name')->nullable();   // snapshot of who wrote it
            $table->text('body');
            $table->timestamps();
            // append-only in practice (no edits/deletes exposed in UI for the GxP trail)
        });
    }

    public function down(): void { Schema::dropIfExists('qualification_comments'); }
};
