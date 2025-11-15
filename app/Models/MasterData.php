<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterData extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'master_data';
    protected $fillable = [
        'a_type_engine_id',
        'b_merk_id',
        'c_type_chassis_id',
        'd_jenis_kendaraan_id',
    ];

    // Definisikan relasi ke setiap tabel master
    public function typeEngine(): BelongsTo
    {
        return $this->belongsTo(ATypeEngine::class, 'a_type_engine_id')->withTrashed();
    }
    public function merk(): BelongsTo
    {
        return $this->belongsTo(BMerk::class, 'b_merk_id')->withTrashed();
    }
    public function typeChassis(): BelongsTo
    {
        return $this->belongsTo(CTypeChassis::class, 'c_type_chassis_id')->withTrashed();
    }
    public function jenisKendaraan(): BelongsTo
    {
        return $this->belongsTo(DJenisKendaraan::class, 'd_jenis_kendaraan_id')->withTrashed();
    }
}
