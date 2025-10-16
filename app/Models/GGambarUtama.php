<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class GGambarUtama extends Model
{
    use HasFactory;
    protected $table = 'g_gambar_utama';
    protected $fillable = [
        'e_varian_body_id',
        'path_gambar_utama',
        'path_gambar_terurai',
        'path_gambar_kontruksi',
    ];

    /**
     * Mendefinisikan bahwa data Gambar Utama ini dimiliki oleh satu Varian Body.
     */
    public function varianBody(): BelongsTo
    {
        return $this->belongsTo(EVarianBody::class, 'e_varian_body_id');
    }

    public function gambarOptionals(): HasMany
    {
        return $this->hasMany(HGambarOptional::class, 'g_gambar_utama_id')->where('tipe', 'paket');
    }
}
