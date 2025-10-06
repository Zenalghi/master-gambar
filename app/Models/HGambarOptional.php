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
        'tipe', // <-- Tambahkan
        'a_type_engine_id',
        'b_merk_id',
        'c_type_chassis_id',
        'd_jenis_kendaraan_id',
        'e_varian_body_id',
        'g_gambar_utama_id', // <-- Tambahkan
        'path_gambar_optional',
        'deskripsi',
    ];

    // Relasi untuk tipe 'independen'
    public function varianBody(): BelongsTo
    {
        return $this->belongsTo(EVarianBody::class, 'e_varian_body_id');
    }

    // Relasi untuk tipe 'dependen'
    public function gambarUtama(): BelongsTo
    {
        return $this->belongsTo(GGambarUtama::class, 'g_gambar_utama_id');
    }

    // Relasi lama (bisa dihapus jika tidak dipakai di tempat lain)
    public function transaksiOptionals()
    {
        return $this->hasMany(TransaksiOptional::class, 'h_gambar_optional_id');
    }
}
