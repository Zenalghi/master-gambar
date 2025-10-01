<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HGambarOptional extends Model
{
    use HasFactory;
    protected $table = 'h_gambar_optional';
    protected $fillable = [
        'a_type_engine_id', // <-- Tambahkan
        'b_merk_id',        // <-- Tambahkan
        'c_type_chassis_id', // <-- Tambahkan
        'd_jenis_kendaraan_id', // <-- Tambahkan
        'e_varian_body_id',
        'path_gambar_optional',
        'deskripsi',
    ];

    /**
     * Mendefinisikan bahwa data Gambar Optional ini dimiliki oleh satu Varian Body.
     */
    public function varianBody(): BelongsTo
    {
        return $this->belongsTo(EVarianBody::class, 'e_varian_body_id');
    }

    public function transaksiOptionals()
    {
        return $this->hasMany(TransaksiOptional::class, 'h_gambar_optional_id');
    }
}
