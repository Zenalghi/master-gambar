<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str; // <-- Tambahkan import

class DJenisKendaraan extends Model
{
    use HasFactory;

    protected $table = 'd_jenis_kendaraan';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'jenis_kendaraan'];

    /**
     * Secara otomatis mengubah nilai 'jenis_kendaraan' menjadi huruf kapital.
     */
    public function setJenisKendaraanAttribute($value)
    {
        $this->attributes['jenis_kendaraan'] = Str::upper($value);
    }

    public function getTypeChassisIdAttribute(): string
    {
        return substr($this->id, 0, 7);
    }

    public function typeChassis(): BelongsTo
    {
        return $this->belongsTo(CTypeChassis::class, 'type_chassis_id');
    }

    public function varianBody(): HasMany
    {
        return $this->hasMany(EVarianBody::class, 'jenis_kendaraan_id');
    }
}
