<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LIMS (LabWare) worklist catalog. A weekly EM export is loaded so each worklist's meta-sample data
 * (QC_EM_PERSONNEL_QUAL evaluation + status, QC_INC_META incubation status + timeline) can be tied
 * to a person's qualification run and drive the workflow: incubation-complete when INC_SAMPLE_STATUS=A,
 * QCM-result-ready when SAMPLE_STATUS=A + INC_SAMPLE_STATUS=A + WORKLIST_ALL_FINAL + evaluation=Pass.
 * Keyed by worklist. Free-text columns are TEXT to avoid varchar overflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lims_worklists')) return;
        Schema::create('lims_worklists', function (Blueprint $table) {
            $table->id();
            $table->string('worklist')->unique();        // EM-13MAY2026-03
            $table->text('worklist_description')->nullable();
            $table->string('sample_number')->nullable();  // QC_EM personnel-qual sample
            $table->string('sample_status')->nullable();  // A/C/I/P/X
            $table->integer('samples_on_worklist')->nullable();
            $table->integer('non_final_count')->nullable();
            $table->boolean('worklist_all_final')->nullable();
            $table->string('qualification_type')->nullable();
            $table->string('personnel')->nullable();       // LIMS username
            $table->string('initial_run_no')->nullable();
            $table->string('annual_requal')->nullable();
            $table->string('additional_requal')->nullable();
            $table->string('evaluation')->nullable();      // Pass / Fail / blank
            $table->text('em_area')->nullable();
            $table->string('cr_grade_1')->nullable();
            $table->string('cr_grade_2')->nullable();
            $table->string('cr_grade_3')->nullable();
            $table->string('grade_a_ops')->nullable();
            $table->string('grade_b_ops')->nullable();
            $table->string('qual_date_1')->nullable();
            $table->string('qual_date_2')->nullable();
            $table->string('qual_date_3')->nullable();
            $table->string('run2_rescheduled')->nullable();
            $table->string('run3_rescheduled')->nullable();
            $table->text('tsa_contact_plate')->nullable();
            $table->text('tsa_contact_plate_1')->nullable();
            $table->text('tsa_contact_plate_2')->nullable();
            $table->text('tsa_control_1')->nullable();
            $table->text('tsa_control_2')->nullable();
            $table->text('tsa_control_3')->nullable();
            // Incubation meta sample
            $table->string('inc_sample_number')->nullable();
            $table->string('inc_sample_status')->nullable(); // A/C/I/P/X
            $table->text('inc1_incubator')->nullable();
            $table->text('inc1_bin')->nullable();
            $table->string('inc1_start')->nullable();
            $table->string('inc1_end')->nullable();
            $table->string('inc1_due')->nullable();
            $table->string('inc1_total')->nullable();
            $table->text('inc2_incubator')->nullable();
            $table->string('inc2_start')->nullable();
            $table->string('inc2_end')->nullable();
            $table->string('inc2_due')->nullable();
            $table->string('inc2_total')->nullable();
            $table->text('storage3_location')->nullable();
            $table->string('storage3_start')->nullable();
            $table->timestamp('catalog_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lims_worklists');
    }
};
