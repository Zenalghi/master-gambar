<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CTypeChassis extends Model
{
    use HasFactory;

    protected $table = 'c_type_chassis';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'type_chassis'];

    /**
     * ACCESSOR: Membuat atribut 'merk_id' dari 4 digit pertama 'id'.
     */
    public function getMerkIdAttribute(): string
    {
        return substr($this->id, 0, 4);
    }

    /**
     * RELASI: Setiap Tipe Sasis dimiliki oleh satu Merk.
     */
    public function merk(): BelongsTo
    {
        return $this->belongsTo(BMerk::class, 'merk_id');
    }
}
