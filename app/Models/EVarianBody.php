<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes; // <-- Tambah
use Illuminate\Support\Str; // <-- Tambahkan import

class EVarianBody extends Model
{
    use HasFactory, SoftDeletes; // <-- Tambah SoftDeletes
    protected $table = 'e_varian_body';
    protected $fillable = ['master_data_id', 'varian_body'];
    // ------------------------------------

    public function masterData()
    {
        return $this->belongsTo(MasterData::class, 'master_data_id')->withTrashed();
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

    public function latestGambarOptional(): HasOne
    {
        return $this->hasOne(HGambarOptional::class, 'e_varian_body_id')->where('tipe', 'independen')->latestOfMany();
    }
}
