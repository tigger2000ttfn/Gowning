<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('non_conformances', function (Blueprint $table) {
            if (! Schema::hasColumn('non_conformances', 'nc_number')) {
                $table->string('nc_number')->nullable()->after('id')->index();
            }
        });

        // Backfill numbers for any existing NCs that lack one.
        $rows = \DB::table('non_conformances')->whereNull('nc_number')->orderBy('id')->get();
        $year = now()->format('Y');
        $seq = (int) (\DB::table('non_conformances')->whereNotNull('nc_number')->count());
        foreach ($rows as $r) {
            $seq++;
            \DB::table('non_conformances')->where('id', $r->id)
                ->update(['nc_number' => 'NC-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT)]);
        }
    }

    public function down(): void
    {
        Schema::table('non_conformances', function (Blueprint $table) {
            if (Schema::hasColumn('non_conformances', 'nc_number')) {
                $table->dropColumn('nc_number');
            }
        });
    }
};
