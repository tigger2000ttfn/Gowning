<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Existing staff accounts (created before the approval gate) should not be
     * locked out. Approve any non-operator account that is still pending.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role', ['super_user','site_admin','power_user','qa_approver','qa','qcm_admin','qcm_scheduler','qcm','training_coordinator'])
            ->where('approval_status', 'pending')
            ->update(['approval_status' => 'approved', 'approved_at' => now()]);
    }

    public function down(): void
    {
        // no-op: we don't un-approve users on rollback
    }
};
