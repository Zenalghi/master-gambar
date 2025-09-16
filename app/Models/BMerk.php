<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BMerk extends Model
{
    use HasFactory;

    protected $table = 'b_merks';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'merk'];

    /**
     * ACCESSOR: Secara otomatis membuat atribut 'type_engine_id'
     * dengan mengambil 2 digit pertama dari 'id'.
     */
    public function getTypeEngineIdAttribute(): string
    {
        return substr($this->id, 0, 2);
    }

    /**
     * RELASI: Setiap Merk (dengan ID komposit) dimiliki oleh satu Tipe Engine.
     */
    public function typeEngine(): BelongsTo
    {
        return $this->belongsTo(ATypeEngine::class, 'type_engine_id');
    }
}
