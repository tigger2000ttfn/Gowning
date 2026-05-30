<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualifications', function (Blueprint $table) {
            // PERSISTENT class marker: the gowning class is taken once, ever. This survives
            // every requalification cycle so annuals are NOT flagged to retake the class.
            // Only QA (after a failure determination) can clear it to require retraining.
            if (! Schema::hasColumn('qualifications', 'class_on_file')) $table->boolean('class_on_file')->default(false);
            if (! Schema::hasColumn('qualifications', 'class_on_file_date')) $table->date('class_on_file_date')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('qualifications', fn (Blueprint $t) => $t->dropColumn(['class_on_file', 'class_on_file_date']));
    }
};
