<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes; // <-- Tambah
use Illuminate\Support\Str; // <-- Tambahkan import

class DJenisKendaraan extends Model
{
    use HasFactory, SoftDeletes; // <-- Tambah SoftDeletes

    protected $table = 'd_jenis_kendaraan';
    // public $incrementing = true; // <-- BERUBAH
    // protected $keyType = 'int'; // <-- BERUBAH
    protected $fillable = ['jenis_kendaraan'];

    /**
     * Secara otomatis mengubah nilai 'jenis_kendaraan' menjadi huruf kapital.
     */
    public function setJenisKendaraanAttribute($value)
    {
        $this->attributes['jenis_kendaraan'] = Str::upper($value);
    }

    // public function varianBody(): HasMany
    // {
    //     return $this->hasMany(EVarianBody::class, 'jenis_kendaraan_id');
    // }
}
