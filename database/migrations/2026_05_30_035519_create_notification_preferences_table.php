<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('event');          // NotificationEvent value
                $table->boolean('in_app')->default(true);
                $table->boolean('email')->default(false);
                $table->timestamps();
                $table->unique(['user_id', 'event']);
            });
        }
    }
    public function down(): void { Schema::dropIfExists('notification_preferences'); }
};
