<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaksiVarian extends Model
{
    use HasFactory;
    protected $table = 'z_transaksi_varians';
    protected $fillable = ['z_transaksi_detail_id', 'e_varian_body_id', 'j_judul_gambar_id', 'urutan'];

    public function detail()
    {
        return $this->belongsTo(TransaksiDetail::class, 'z_transaksi_detail_id');
    }
    public function varianBody()
    {
        return $this->belongsTo(EVarianBody::class, 'e_varian_body_id');
    }

    public function judulGambar()
    {
        return $this->belongsTo(JJudulGambar::class, 'j_judul_gambar_id');
    }
}
