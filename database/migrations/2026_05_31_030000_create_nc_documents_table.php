<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TrackWise (Digital / Salesforce) nonconformance catalog. A weekly NC export (NC number +
 * Salesforce record id + status) is loaded so the system can auto-fill an NC link from just the
 * NC number entered at a failed run, and show the NC's workflow status (open/closed) wherever it
 * is referenced. The link is the Salesforce Lightning record URL built from the record id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nc_documents')) {
            Schema::create('nc_documents', function (Blueprint $table) {
                $table->id();
                $table->string('nc_number')->unique();      // e.g. NC-33670
                $table->string('record_id')->nullable();     // Salesforce id, e.g. a36QQ000004qsd7
                $table->text('url')->nullable();             // full Lightning record URL
                $table->string('workflow_status')->nullable(); // Evaluation / Closed / Canceled / ...
                $table->date('created_date')->nullable();
                $table->date('date_closed')->nullable();
                $table->string('qa_approver')->nullable();
                $table->string('department')->nullable();
                $table->string('reference_numbers')->nullable();
                $table->string('site')->nullable();
                $table->string('sub_group')->nullable();
                $table->timestamp('catalog_synced_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nc_documents');
    }
};
