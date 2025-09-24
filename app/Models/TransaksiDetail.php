<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaksiDetail extends Model
{
    use HasFactory;
    protected $table = 'z_transaksi_details';
    protected $fillable = ['z_transaksi_id', 'pemeriksa_id'];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'z_transaksi_id');
    }
    public function pemeriksa()
    {
        return $this->belongsTo(User::class, 'pemeriksa_id');
    }
    public function varians()
    {
        return $this->hasMany(TransaksiVarian::class, 'z_transaksi_detail_id');
    }
}
