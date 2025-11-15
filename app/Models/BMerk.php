<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // <-- Tambah
use Illuminate\Support\Str;

class BMerk extends Model
{
    use HasFactory, SoftDeletes; // <-- Tambah SoftDeletes

    protected $table = 'b_merks';
    public $incrementing = true; // <-- BERUBAH
    protected $keyType = 'int'; // <-- BERUBAH
    protected $fillable = ['merk'];

    public function setMerkAttribute($value)
    {
        $this->attributes['merk'] = Str::upper($value);
    }
}
