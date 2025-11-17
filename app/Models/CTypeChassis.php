<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CTypeChassis extends Model
{
    use HasFactory, SoftDeletes; // <-- Tambah SoftDeletes

    protected $table = 'c_type_chassis';
    public $incrementing = true; // <-- BERUBAH
    protected $keyType = 'int'; // <-- BERUBAH
    protected $fillable = ['type_chassis'];

    /**
     * Secara otomatis mengubah nilai 'type_chassis' menjadi huruf kapital
     * setiap kali akan disimpan ke database.
     */
    public function setTypeChassisAttribute($value)
    {
        $this->attributes['type_chassis'] = Str::upper($value);
    }

    public function gambarKelistrikan(): HasOne
    {
        return $this->hasOne(IGambarKelistrikan::class, 'c_type_chassis_id');
    }
    public function merk(): BelongsTo
    {
        return $this->belongsTo(BMerk::class, 'b_merk_id')->withTrashed();
    }
}
