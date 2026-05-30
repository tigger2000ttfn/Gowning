<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('class_enrollments', 'draft_attendance')) {
                // Draft attendance intent only ('attended' | 'no_show' | null). This is NEVER
                // the real status: the real `status` stays signed_up until the trainer submits
                // with an e-signature (-> pending_qa) and QA approves (-> completed).
                $table->string('draft_attendance')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('class_enrollments', 'draft_attendance')) {
                $table->dropColumn('draft_attendance');
            }
        });
    }
};
