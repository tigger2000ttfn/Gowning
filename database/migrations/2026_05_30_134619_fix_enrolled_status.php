<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Earlier enrollments were created with a stray 'enrolled' status that matched no
        // kanban lane, so their cards never appeared. Normalize them to 'signed_up'.
        if (\Illuminate\Support\Facades\Schema::hasTable('class_enrollments')) {
            DB::table('class_enrollments')->where('status', 'enrolled')->update(['status' => 'signed_up']);
        }
    }
    public function down(): void {}
};
