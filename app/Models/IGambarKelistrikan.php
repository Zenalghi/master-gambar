<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IGambarKelistrikan extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'i_gambar_kelistrikan';
    protected $fillable = [
        'a_type_engine_id',
        'b_merk_id',
        'c_type_chassis_id',
        'path_gambar_kelistrikan',
        'deskripsi',
    ];

    public function typeEngine()
    {
        return $this->belongsTo(ATypeEngine::class, 'a_type_engine_id')->withTrashed();
    }
    public function merk()
    {
        return $this->belongsTo(BMerk::class, 'b_merk_id')->withTrashed();
    }
    public function typeChassis()
    {
        return $this->belongsTo(CTypeChassis::class, 'c_type_chassis_id')->withTrashed();
    }
}
