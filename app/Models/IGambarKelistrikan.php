<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IGambarKelistrikan extends Model
{
    use HasFactory;
    protected $table = 'i_gambar_kelistrikan';
    protected $fillable = [
        'a_type_engine_id', // <-- Tambahkan
        'b_merk_id',        // <-- Tambahkan
        'c_type_chassis_id',
        'path_gambar_kelistrikan',
        'deskripsi',
    ];

    public function typeChassis(): BelongsTo
    {
        return $this->belongsTo(CTypeChassis::class, 'c_type_chassis_id');
    }
}
