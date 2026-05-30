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

        // One-time cleanup: any draft "attended" that leaked into the REAL status on a
        // not-yet-submitted session is normalized back to a proper draft (status returns to
        // signed_up, the mark is preserved as a draft). Submitted sessions are left untouched.
        if (Schema::hasColumn('class_enrollments', 'draft_attendance')) {
            $leaked = \Illuminate\Support\Facades\DB::table('class_enrollments')
                ->where('status', 'attended')
                ->whereIn('class_session_id', function ($q) {
                    $q->select('id')->from('class_sessions')->whereNull('attendance_submitted_at');
                })
                ->pluck('id');
            if ($leaked->isNotEmpty()) {
                \Illuminate\Support\Facades\DB::table('class_enrollments')
                    ->whereIn('id', $leaked->all())
                    ->update(['draft_attendance' => 'attended', 'status' => 'signed_up']);
            }
        }
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
