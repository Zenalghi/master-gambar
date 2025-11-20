<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ATypeEngine extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'a_type_engines';
    // ID sekarang auto-increment integer
    protected $fillable = ['type_engine'];

    public function setTypeEngineAttribute($value)
    {
        $this->attributes['type_engine'] = Str::upper($value);
    }
}
