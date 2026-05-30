<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A prior run of this migration failed on the personnel FK (wrong table name),
        // which can leave a partial run_samples table. Drop it so we recreate cleanly.
        Schema::dropIfExists('run_samples');

        Schema::create('run_samples', function (Blueprint $table) {
                $table->id();
                $table->foreignId('qualification_run_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('personnel_id')->nullable()->constrained('personnel')->nullOnDelete();
                $table->string('site');                 // Fingertips, Chest, Forearms, etc.
                $table->string('result')->nullable();    // pass / fail / pending
                $table->string('plate_id')->nullable();  // LIMS / plate identifier
                $table->integer('cfu_count')->nullable();// colony forming units
                $table->date('read_date')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('run_samples'); }
};
