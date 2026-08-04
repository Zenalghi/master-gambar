<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migrasi: Dual-Mode SKRB
 *
 * Perubahan pada tabel `skrbs`:
 * 1. Drop foreign key + unique index lama pada `transaksi_id`
 * 2. Ubah `transaksi_id` menjadi NULLABLE
 * 3. Tambah partial unique index: UNIQUE(transaksi_id) WHERE transaksi_id IS NOT NULL
 *    → 1 transaksi_id tetap hanya bisa memiliki 1 SKRB, tapi bisa ada banyak SKRB dengan transaksi_id = NULL (Cara 2)
 * 4. Tambah kolom `master_data_id` (nullable) untuk Cara 2 (tanpa transaksi)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop foreign key jika ada
        try {
            Schema::table('skrbs', function (Blueprint $table) {
                $table->dropForeign(['transaksi_id']);
            });
        } catch (\Exception $e) {
            // Abaikan jika FK sudah tidak ada
        }

        // 2. Drop unique index lama pada transaksi_id jika ada
        try {
            Schema::table('skrbs', function (Blueprint $table) {
                $table->dropUnique(['transaksi_id']);
            });
        } catch (\Exception $e) {
            // Abaikan jika index tidak ada
        }

        // 3. Ubah transaksi_id menjadi nullable
        Schema::table('skrbs', function (Blueprint $table) {
            $table->string('transaksi_id')->nullable()->change();
        });

        // 4. Tambah kolom master_data_id untuk Cara 2
        Schema::table('skrbs', function (Blueprint $table) {
            if (!Schema::hasColumn('skrbs', 'master_data_id')) {
                $table->unsignedBigInteger('master_data_id')->nullable()->after('transaksi_id');
            }
            if (!Schema::hasColumn('skrbs', 'jenis_pengajuan_id')) {
                $table->unsignedBigInteger('jenis_pengajuan_id')->nullable()->after('master_data_id');
            }
        });

        // 5. Buat partial unique index: UNIQUE(transaksi_id) WHERE transaksi_id IS NOT NULL
        // Ini memastikan 1 transaksi_id hanya bisa punya 1 SKRB,
        // namun banyak row boleh memiliki transaksi_id = NULL (Cara 2)
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: Gunakan partial unique via expression (MySQL 8.0.13+)
            // Fallback: buat unique index biasa dan handle NULL di aplikasi
            // MySQL membolehkan multi-NULL dalam UNIQUE index secara default
            DB::statement('CREATE UNIQUE INDEX skrbs_transaksi_id_unique ON skrbs (transaksi_id)');
        } elseif ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX skrbs_transaksi_id_unique ON skrbs (transaksi_id) WHERE transaksi_id IS NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite juga membolehkan multi-NULL dalam UNIQUE index
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS skrbs_transaksi_id_unique ON skrbs (transaksi_id)');
        }
    }

    public function down(): void
    {
        // Drop partial unique index
        try {
            DB::statement('DROP INDEX IF EXISTS skrbs_transaksi_id_unique');
        } catch (\Exception $e) {
            try {
                Schema::table('skrbs', function (Blueprint $table) {
                    $table->dropUnique(['transaksi_id']);
                });
            } catch (\Exception $e2) {}
        }

        // Drop kolom tambahan
        Schema::table('skrbs', function (Blueprint $table) {
            if (Schema::hasColumn('skrbs', 'jenis_pengajuan_id')) {
                $table->dropColumn('jenis_pengajuan_id');
            }
            if (Schema::hasColumn('skrbs', 'master_data_id')) {
                $table->dropColumn('master_data_id');
            }
        });

        // Kembalikan transaksi_id ke NOT NULL
        Schema::table('skrbs', function (Blueprint $table) {
            $table->string('transaksi_id')->nullable(false)->change();
        });

        // Kembalikan unique + FK
        Schema::table('skrbs', function (Blueprint $table) {
            $table->unique('transaksi_id');
            $table->foreign('transaksi_id')->references('id')->on('z_transaksi')->onDelete('cascade');
        });
    }
};
