<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_completions', function (Blueprint $table) {
            if (! Schema::hasColumn('class_completions', 'lms_number')) {
                $table->string('lms_number')->nullable()->after('class_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_completions', function (Blueprint $table) {
            if (Schema::hasColumn('class_completions', 'lms_number')) {
                $table->dropColumn('lms_number');
            }
        });
    }
};
