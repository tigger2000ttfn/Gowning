<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualification_runs', function (Blueprint $t) {
            if (! Schema::hasColumn('qualification_runs', 'qcm_signed_at'))   $t->timestamp('qcm_signed_at')->nullable();
            if (! Schema::hasColumn('qualification_runs', 'qcm_signed_by'))   $t->unsignedBigInteger('qcm_signed_by')->nullable();
        });
        Schema::table('qualifications', function (Blueprint $t) {
            if (! Schema::hasColumn('qualifications', 'lms_number')) $t->string('lms_number')->nullable();
        });
        Schema::table('class_sessions', function (Blueprint $t) {
            if (! Schema::hasColumn('class_sessions', 'lms_number'))       $t->string('lms_number')->nullable();
            if (! Schema::hasColumn('class_sessions', 'veeva_doc_number')) $t->string('veeva_doc_number')->nullable();
            if (! Schema::hasColumn('class_sessions', 'veeva_url'))        $t->string('veeva_url')->nullable();
            if (! Schema::hasColumn('class_sessions', 'qa_signed_at'))     $t->timestamp('qa_signed_at')->nullable();
            if (! Schema::hasColumn('class_sessions', 'qa_signed_by'))     $t->unsignedBigInteger('qa_signed_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('qualification_runs', fn (Blueprint $t) => $t->dropColumn(['qcm_signed_at', 'qcm_signed_by']));
        Schema::table('qualifications', fn (Blueprint $t) => $t->dropColumn('lms_number'));
        Schema::table('class_sessions', fn (Blueprint $t) => $t->dropColumn(['lms_number', 'veeva_doc_number', 'veeva_url', 'qa_signed_at', 'qa_signed_by']));
    }
};
