<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualifications', function (Blueprint $table) {
            // One LIMS worklist per person per qualification cycle (all their runs batch onto it).
            if (! Schema::hasColumn('qualifications', 'lims_worklist_id')) {
                $table->string('lims_worklist_id')->nullable();
            }
        });

        Schema::table('class_enrollments', function (Blueprint $table) {
            // Completed is a QA movement: track who QA-approved the classroom training and when.
            if (! Schema::hasColumn('class_enrollments', 'qa_completed_by')) {
                $table->unsignedBigInteger('qa_completed_by')->nullable();
            }
            if (! Schema::hasColumn('class_enrollments', 'qa_completed_at')) {
                $table->timestamp('qa_completed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('qualifications', function (Blueprint $table) {
            if (Schema::hasColumn('qualifications', 'lims_worklist_id')) $table->dropColumn('lims_worklist_id');
        });
        Schema::table('class_enrollments', function (Blueprint $table) {
            foreach (['qa_completed_by', 'qa_completed_at'] as $c) {
                if (Schema::hasColumn('class_enrollments', $c)) $table->dropColumn($c);
            }
        });
    }
};
