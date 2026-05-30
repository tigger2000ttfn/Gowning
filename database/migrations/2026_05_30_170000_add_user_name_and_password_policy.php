<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'first_name')) $table->string('first_name')->nullable();
            if (! Schema::hasColumn('users', 'last_name')) $table->string('last_name')->nullable();
            if (! Schema::hasColumn('users', 'must_change_password')) $table->boolean('must_change_password')->default(false);
            if (! Schema::hasColumn('users', 'password_changed_at')) $table->timestamp('password_changed_at')->nullable();
            if (! Schema::hasColumn('users', 'failed_login_attempts')) $table->unsignedInteger('failed_login_attempts')->default(0);
            if (! Schema::hasColumn('users', 'locked_until')) $table->timestamp('locked_until')->nullable();
        });

        // Backfill first/last name by splitting the existing single name field.
        foreach (DB::table('users')->get() as $u) {
            if (! empty($u->first_name) || empty($u->name)) continue;
            $parts = preg_split('/\s+/', trim($u->name), 2);
            DB::table('users')->where('id', $u->id)->update([
                'first_name' => $parts[0] ?? $u->name,
                'last_name' => $parts[1] ?? '',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['first_name', 'last_name', 'must_change_password', 'password_changed_at', 'failed_login_attempts', 'locked_until'] as $c) {
                if (Schema::hasColumn('users', $c)) $table->dropColumn($c);
            }
        });
    }
};
