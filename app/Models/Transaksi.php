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
        'customer_id',
        'd_jenis_kendaraan_id',
        'f_pengajuan_id',
        'user_id',
    ];

    // Relasi ke User yang membuat
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke Customer
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // Relasi ke Jenis Kendaraan (dan semua induknya)
    public function dJenisKendaraan()
    {
        return $this->belongsTo(DJenisKendaraan::class);
    }

    // Relasi ke Jenis Pengajuan
    public function fPengajuan()
    {
        return $this->belongsTo(FPengajuan::class);
    }
}
