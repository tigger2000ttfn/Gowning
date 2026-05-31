<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule_name')->nullable();      // denormalized snapshot in case the rule is later deleted
            $table->string('trigger')->nullable();
            $table->string('action')->nullable();
            $table->string('status')->default('success'); // success | failed
            $table->string('subject')->nullable();         // e.g. the person the rule acted on
            $table->text('detail')->nullable();            // outcome / error message
            $table->timestamps();

            $table->index(['automation_rule_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
