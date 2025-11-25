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
        'tipe',
        'e_varian_body_id',
        'g_gambar_utama_id',
        'path_gambar_optional',
        'deskripsi',
    ];

    /**
     * Relasi ke Varian Body (Induk langsung).
     */
    public function varianBody(): BelongsTo
    {
        return $this->belongsTo(EVarianBody::class, 'e_varian_body_id')->withTrashed();
    }

    /**
     * Relasi ke Gambar Utama (Jika tipe paket).
     */
    public function gambarUtama(): BelongsTo
    {
        return $this->belongsTo(GGambarUtama::class, 'g_gambar_utama_id');
    }
}
