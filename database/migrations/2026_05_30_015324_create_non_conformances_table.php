<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('non_conformances')) {
            Schema::create('non_conformances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('qualification_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('qualification_run_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('personnel_id')->nullable()->constrained('personnel')->nullOnDelete();
                $table->string('trackwise_id')->nullable();      // link/reference to TrackWise NC (not transcribed)
                $table->string('nc_type')->default('failed_run'); // failed_run / mold_hit / bacteria_hit / other
                $table->string('organism')->nullable();           // e.g. mold / bacteria genus if known
                $table->string('site')->nullable();               // sampling site implicated
                $table->integer('cfu_count')->nullable();         // count for trending (sub-threshold included)
                $table->boolean('over_threshold')->default(false);// was it an action-limit excursion?
                $table->string('status')->default('open');        // open / investigating / closed
                $table->text('summary')->nullable();              // brief, not a transcription
                $table->date('observed_date')->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }
    public function down(): void { Schema::dropIfExists('non_conformances'); }
};
