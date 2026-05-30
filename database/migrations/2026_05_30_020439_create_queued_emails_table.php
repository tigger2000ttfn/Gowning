<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('queued_emails')) {
            Schema::create('queued_emails', function (Blueprint $table) {
                $table->id();
                $table->string('to_email')->nullable();
                $table->string('to_name')->nullable();
                $table->string('subject');
                $table->text('body');
                $table->timestamp('sent_at')->nullable();   // null = pending until relay is up
                $table->timestamps();
            });
        }
    }
    public function down(): void { Schema::dropIfExists('queued_emails'); }
};
