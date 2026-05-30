<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 'samples_taken' stage was removed from the pipeline (incubation is in LIMS).
        // Remap any existing records to 'incubating' so the enum cast never fails.
        DB::table('qualifications')->where('workflow_stage', 'samples_taken')->update(['workflow_stage' => 'incubating']);
    }
    public function down(): void {}
};
