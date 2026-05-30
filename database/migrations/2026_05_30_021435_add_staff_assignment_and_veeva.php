<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('run_slots', function (Blueprint $table) {
            if (! Schema::hasColumn('run_slots', 'assigned_analyst_id'))
                $table->foreignId('assigned_analyst_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('class_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('class_sessions', 'assigned_instructor_id'))
                $table->foreignId('assigned_instructor_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('qualifications', function (Blueprint $table) {
            if (! Schema::hasColumn('qualifications', 'qa_owner_id'))
                $table->foreignId('qa_owner_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('qualification_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('qualification_runs', 'veeva_doc_number')) $table->string('veeva_doc_number')->nullable();
            if (! Schema::hasColumn('qualification_runs', 'veeva_url')) $table->string('veeva_url')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('run_slots', fn (Blueprint $t) => $t->dropColumn('assigned_analyst_id'));
        Schema::table('class_sessions', fn (Blueprint $t) => $t->dropColumn('assigned_instructor_id'));
        Schema::table('qualifications', fn (Blueprint $t) => $t->dropColumn('qa_owner_id'));
        Schema::table('qualification_runs', fn (Blueprint $t) => $t->dropColumn(['veeva_doc_number', 'veeva_url']));
    }
};
