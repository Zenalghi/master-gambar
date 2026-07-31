<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skrb_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('skrb_settings', 'ignore_names')) {
                $table->json('ignore_names')->nullable();
            }
        });

        // Update default ignore_names jika belum ada
        DB::table('skrb_settings')->whereNull('ignore_names')->update([
            'ignore_names' => json_encode(['(4x2)', '(6x2)', '(4x4)', 'M/T', 'A/T'])
        ]);
    }

    public function down(): void
    {
        Schema::table('skrb_settings', function (Blueprint $table) {
            if (Schema::hasColumn('skrb_settings', 'ignore_names')) {
                $table->dropColumn('ignore_names');
            }
        });
    }
};
