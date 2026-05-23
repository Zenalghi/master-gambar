<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DJenisKendaraan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'd_jenis_kendaraan';

    // ID is now auto-increment integer (default behavior), so we remove $incrementing=false and $keyType='string'

    protected $fillable = ['jenis_kendaraan'];

    /**
     * Automatically convert 'jenis_kendaraan' to uppercase.
     */
    public function setJenisKendaraanAttribute($value)
    {
        $this->attributes['jenis_kendaraan'] = Str::upper($value);
    }

    public function masterVarians()
    {
        return $this->hasMany(MMasterVarian::class, 'd_jenis_kendaraan_id');
    }
}
