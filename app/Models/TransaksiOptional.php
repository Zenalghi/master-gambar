<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaksiOptional extends Model
{
    use HasFactory;
    protected $table = 'z_transaksi_optionals';
    protected $fillable = ['z_transaksi_detail_id', 'h_gambar_optional_id', 'urutan'];

    public function gambarOptional(): BelongsTo
    {
        return $this->belongsTo(HGambarOptional::class, 'h_gambar_optional_id');
    }
}