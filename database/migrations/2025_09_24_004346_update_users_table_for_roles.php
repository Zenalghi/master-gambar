<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hapus kolom 'role' yang lama (bertipe string)
            $table->dropColumn('role');
            // Tambahkan kolom 'role_id' yang baru sebagai foreign key
            // Default 3 adalah ID untuk 'drafter' dari RoleSeeder
            $table->foreignId('role_id')->after('password')->default(3)->constrained('roles');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Logika untuk rollback jika diperlukan
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
            $table->string('role')->default('drafter');
        });
    }
};
