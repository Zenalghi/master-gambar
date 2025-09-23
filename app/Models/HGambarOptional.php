<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HGambarOptional extends Model
{
    use HasFactory;
    protected $table = 'h_gambar_optional';
    protected $fillable = [
        'e_varian_body_id',
        'path_gambar_optional',
    ];

    /**
     * Mendefinisikan bahwa data Gambar Optional ini dimiliki oleh satu Varian Body.
     */
    public function varianBody(): BelongsTo
    {
        return $this->belongsTo(EVarianBody::class, 'e_varian_body_id');
    }
}

