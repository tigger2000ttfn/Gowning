<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // workflow stage on qualifications (the person's position in the GMP pipeline)
        if (! Schema::hasColumn('qualifications', 'workflow_stage')) {
            Schema::table('qualifications', function (Blueprint $table) {
                $table->string('workflow_stage')->default('class_pending')->after('status');
                $table->timestamp('stage_changed_at')->nullable()->after('workflow_stage');
            });
        }

        // reference lists managed from Settings (GMP-controlled vocab)
        foreach (['departments', 'job_titles', 'cleanrooms', 'sampling_sites', 'training_class_catalog'] as $tbl) {
            if (! Schema::hasTable($tbl)) {
                Schema::create($tbl, function (Blueprint $table) {
                    $table->id();
                    $table->string('name');
                    $table->string('code')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->integer('sort')->default(0);
                    $table->timestamps();
                });
            }
        }

        // incubation tracking on qualification runs
        if (! Schema::hasColumn('qualification_runs', 'incubation_started_at')) {
            Schema::table('qualification_runs', function (Blueprint $table) {
                $table->timestamp('incubation_started_at')->nullable();
                $table->timestamp('results_released_at')->nullable();
                $table->timestamp('qa_signed_at')->nullable();
                $table->foreignId('qa_signed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('qa_notes')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('qualifications', fn (Blueprint $t) => $t->dropColumn(['workflow_stage', 'stage_changed_at']));
        foreach (['departments', 'job_titles', 'cleanrooms', 'sampling_sites', 'training_class_catalog'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
