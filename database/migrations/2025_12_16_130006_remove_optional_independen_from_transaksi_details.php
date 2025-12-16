<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('z_transaksi_details', function (Blueprint $table) {
            $table->dropColumn('data_optional_independen');
        });
    }

    public function down()
    {
        Schema::table('z_transaksi_details', function (Blueprint $table) {
            $table->json('data_optional_independen')->nullable();
        });
    }
};
