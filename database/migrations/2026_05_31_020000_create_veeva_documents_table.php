<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Veeva document catalog. A weekly export from Veeva (doc number + permalink + metadata) is loaded
 * here so the system can auto-fill a report's Veeva link from just the doc number a QCM analyst
 * enters, and a sweep can backfill links on existing runs/sessions as the catalog grows. The
 * doc_number -> permalink mapping cannot be derived (the V0Z... permalink id is an opaque internal
 * Veeva key), so the catalog is the bridge.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('veeva_documents')) {
            Schema::create('veeva_documents', function (Blueprint $table) {
                $table->id();
                $table->string('doc_number')->unique();   // e.g. RPT-AST-150240 (business id)
                $table->string('doc_id')->nullable();      // e.g. V0Z0000000ZS022 (permalink surrogate key)
                $table->text('url')->nullable();           // full permalink URL
                $table->string('title')->nullable();
                $table->string('type')->nullable();
                $table->string('status')->nullable();
                $table->string('version')->nullable();
                $table->timestamp('catalog_synced_at')->nullable(); // when last seen in an import
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('veeva_documents');
    }
};
