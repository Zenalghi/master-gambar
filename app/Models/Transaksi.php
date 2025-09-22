<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    use HasFactory;

    protected $table = 'z_transaksi';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'a_type_engine_id',         // <-- Tambahkan
        'b_merk_id',                // <-- Tambahkan
        'c_type_chassis_id',        // <-- Tambahkan
        'd_jenis_kendaraan_id',
        'f_pengajuan_id',
        'customer_id',
        'user_id',
    ];

    // Relasi yang sudah ada
    public function user() { return $this->belongsTo(User::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function dJenisKendaraan() { return $this->belongsTo(DJenisKendaraan::class, 'd_jenis_kendaraan_id'); }
    public function fPengajuan() { return $this->belongsTo(FPengajuan::class, 'f_pengajuan_id'); }

    // --- RELASI BARU ---
    public function aTypeEngine() { return $this->belongsTo(ATypeEngine::class, 'a_type_engine_id'); }
    public function bMerk() { return $this->belongsTo(BMerk::class, 'b_merk_id'); }
    public function cTypeChassis() { return $this->belongsTo(CTypeChassis::class, 'c_type_chassis_id'); }
}

