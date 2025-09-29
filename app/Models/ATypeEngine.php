<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ATypeEngine extends Model
{
    use HasFactory;

    protected $table = 'a_type_engines';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'type_engine'];

    // Satu tipe engine bisa dimiliki oleh banyak merk (dalam ID gabungan)
    public function merks(): HasMany
    {
        // Relasi ini lebih konseptual, sulit di-query langsung
        // Tapi kita siapkan untuk kemungkinan pengembangan
        return $this->hasMany(BMerk::class, 'type_engine_id');
    }

    public function setTypeEngineAttribute($value)
    {
        $this->attributes['type_engine'] = Str::upper($value);
    }
}
