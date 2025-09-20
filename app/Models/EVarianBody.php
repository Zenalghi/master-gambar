<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EVarianBody extends Model
{
    use HasFactory;

    protected $table = 'e_varian_body';

    protected $fillable = ['jenis_kendaraan_id', 'varian_body'];

    /**
     * RELASI: Setiap Varian Body dimiliki oleh satu Jenis Kendaraan.
     */
    public function jenisKendaraan(): BelongsTo
    {
        return $this->belongsTo(DJenisKendaraan::class, 'jenis_kendaraan_id');
    }
    public function pengajuan(): HasMany
    {
        return $this->hasMany(FPengajuan::class, 'varian_body_id');
    }
}
