<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FPengajuan extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terhubung dengan model ini.
     */
    protected $table = 'f_pengajuan';

    /**
     * Atribut yang dapat diisi secara massal.
     */
    protected $fillable = [
        // 'varian_body_id',
        'nama_pengajuan', // Kolom untuk 'BARU', 'VARIAN', 'REVISI'
    ];

    /**
     * RELASI: Setiap Pengajuan dimiliki oleh satu Varian Body.
     */
    // public function varianBody(): BelongsTo
    // {
    //     return $this->belongsTo(EVarianBody::class, 'varian_body_id');
    // }
}