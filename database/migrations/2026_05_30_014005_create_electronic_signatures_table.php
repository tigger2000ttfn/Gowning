<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('electronic_signatures')) {
            Schema::create('electronic_signatures', function (Blueprint $table) {
                $table->id();
                $table->morphs('signable');                 // what was signed (qualification, run, etc.)
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('signer_name');               // printed name at time of signing
                $table->string('meaning');                   // e.g. "Approved", "Reviewed"
                $table->text('statement')->nullable();       // the manifestation text shown to signer
                $table->timestamp('signed_at');
                $table->timestamps();
            });
        }
    }
    public function down(): void { Schema::dropIfExists('electronic_signatures'); }
};
