<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('domain');          // 'run' (qualification pipeline) | 'class' (classroom)
            $table->string('key');             // stable machine key (matches enum value where applicable)
            $table->string('label');           // editable display name
            $table->string('color', 9)->default('#888888'); // hex
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // system keys map to engine logic; key not editable
            $table->timestamps();
            $table->unique(['domain', 'key']);
        });
    }
    public function down(): void { Schema::dropIfExists('workflow_statuses'); }
};
