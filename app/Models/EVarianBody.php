<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str; // <-- Tambahkan import

class EVarianBody extends Model
{
    use HasFactory;
    protected $table = 'e_varian_body';
    protected $fillable = ['jenis_kendaraan_id', 'varian_body'];

    public function setVarianBodyAttribute($value)
    {
        $this->attributes['varian_body'] = Str::upper($value);
    }
    // ------------------------------------

    public function jenisKendaraan(): BelongsTo
    {
        return $this->belongsTo(DJenisKendaraan::class, 'jenis_kendaraan_id');
    }
    // --- TAMBAHKAN DUA RELASI BARU INI ---
    /**
     * Mendefinisikan bahwa satu Varian Body memiliki satu set Gambar Utama.
     */
    public function gambarUtama(): HasOne
    {
        return $this->hasOne(GGambarUtama::class, 'e_varian_body_id');
    }

    /**
     * Mendefinisikan bahwa satu Varian Body memiliki satu Gambar Optional.
     */
    public function gambarOptional(): HasMany
    {
        return $this->hasMany(HGambarOptional::class, 'e_varian_body_id');
    }
}
