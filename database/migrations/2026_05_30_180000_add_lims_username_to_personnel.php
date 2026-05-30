<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            // LIMS (QC LabWare) username, used to match/populate the person from a LIMS upload.
            if (! Schema::hasColumn('personnel', 'lims_username')) {
                $table->string('lims_username')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            if (Schema::hasColumn('personnel', 'lims_username')) $table->dropColumn('lims_username');
        });
    }
};
