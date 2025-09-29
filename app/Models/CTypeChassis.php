<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str; // <-- Tambahkan import ini

class CTypeChassis extends Model
{
    use HasFactory;

    protected $table = 'c_type_chassis';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'type_chassis'];

    /**
     * Secara otomatis mengubah nilai 'type_chassis' menjadi huruf kapital
     * setiap kali akan disimpan ke database.
     */
    public function setTypeChassisAttribute($value)
    {
        $this->attributes['type_chassis'] = Str::upper($value);
    }

    public function getMerkIdAttribute(): string
    {
        return substr($this->id, 0, 4);
    }

    public function merk(): BelongsTo
    {
        return $this->belongsTo(BMerk::class, 'merk_id');
    }

    public function getJenisKendaraanChildren()
    {
        return DJenisKendaraan::where('id', 'like', $this->id . '%')->get();
    }

    public function gambarKelistrikan(): HasOne
    {
        return $this->hasOne(IGambarKelistrikan::class, 'c_type_chassis_id');
    }
}
