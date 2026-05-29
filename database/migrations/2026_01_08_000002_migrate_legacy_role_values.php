<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert legacy role values to the new role set so enum casting doesn't fail.
     * Old: system_admin, qc_micro, qa, operator. New role set defined in App\Enums\Role.
     */
    public function up(): void
    {
        $map = [
            'system_admin' => 'super_user',
            'qc_micro'     => 'qcm_admin',
            // 'qa' stays 'qa', 'operator' stays 'operator'
        ];
        foreach ($map as $old => $new) {
            DB::table('users')->where('role', $old)->update(['role' => $new]);
        }
        // any null/blank role -> operator (safe default)
        DB::table('users')->whereNull('role')->update(['role' => 'operator']);
        DB::table('users')->where('role', '')->update(['role' => 'operator']);
    }

    public function down(): void {}
};
