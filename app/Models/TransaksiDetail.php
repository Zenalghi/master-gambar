<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaksiDetail extends Model
{
    use HasFactory;

    protected $table = 'z_transaksi_details';

    protected $fillable = [
        'transaksi_id',
        'pemeriksa_id',
        'jumlah_gambar',
        'data_gambar_utama',
        'deskripsi_optional',
        'desc_space',
        'ordered_independent_ids',
        'i_gambar_kelistrikan_id',
        'snapshot_data',
    ];

    protected $casts = [
        'data_gambar_utama' => 'array',
        'ordered_independent_ids' => 'array',
        'snapshot_data' => 'array',
    ];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id', 'id');
    }
}
