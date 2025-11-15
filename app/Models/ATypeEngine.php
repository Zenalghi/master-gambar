<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // <-- Tambah
use Illuminate\Support\Str;

class ATypeEngine extends Model
{
    use HasFactory, SoftDeletes; // <-- Tambah SoftDeletes

    protected $table = 'a_type_engines';
    public $incrementing = true; // <-- BERUBAH
    protected $keyType = 'int'; // <-- BERUBAH
    protected $fillable = ['type_engine']; // <-- BERUBAH (ID otomatis)

    // HAPUS SEMUA relasi 'merks()' dan 'accessor'
    // ...

    public function setTypeEngineAttribute($value)
    {
        $this->attributes['type_engine'] = Str::upper($value);
    }
}
