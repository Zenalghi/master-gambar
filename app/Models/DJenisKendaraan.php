<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DJenisKendaraan extends Model
{
    use HasFactory;

    protected $table = 'd_jenis_kendaraan';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'jenis_kendaraan'];

    /**
     * ACCESSOR: Membuat atribut 'type_chassis_id' dari 7 digit pertama 'id'.
     */
    public function getTypeChassisIdAttribute(): string
    {
        return substr($this->id, 0, 7);
    }

    /**
     * RELASI: Setiap Jenis Kendaraan dimiliki oleh satu Tipe Sasis.
     */
    public function typeChassis(): BelongsTo
    {
        return $this->belongsTo(CTypeChassis::class, 'type_chassis_id');
    }

    /**
     * RELASI: Satu Jenis Kendaraan bisa punya banyak Varian Body.
     */
    public function varianBody(): HasMany
    {
        return $this->hasMany(EVarianBody::class, 'jenis_kendaraan_id');
    }
}
