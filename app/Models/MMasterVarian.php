<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MMasterVarian extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_master_varians';

    protected $fillable = [
        'd_jenis_kendaraan_id',
        'nama_varian',
    ];

    // Auto Uppercase
    public function setNamaVarianAttribute($value)
    {
        $this->attributes['nama_varian'] = Str::upper($value);
    }

    // Relasi ke DJenisKendaraan
    public function jenisKendaraan(): BelongsTo
    {
        return $this->belongsTo(DJenisKendaraan::class, 'd_jenis_kendaraan_id')->withTrashed();
    }
}
