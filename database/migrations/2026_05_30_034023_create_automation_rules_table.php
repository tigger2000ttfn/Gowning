<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automation_rules')) {
            Schema::create('automation_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('trigger');                 // e.g. stage_changed, run_failed, due_soon
                $table->string('trigger_stage')->nullable(); // optional: which stage (for stage_changed)
                $table->string('action');                  // e.g. notify_capability, notify_person, send_announcement
                $table->json('action_config')->nullable(); // action params (capability, message, etc.)
                $table->boolean('is_enabled')->default(true);
                $table->integer('run_count')->default(0);  // times fired (for the rules page)
                $table->timestamp('last_fired_at')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }
    public function down(): void { Schema::dropIfExists('automation_rules'); }
};
