<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BMerk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'b_merks';
    protected $fillable = ['merk'];
    public function setMerkAttribute($value)
    {
        $this->attributes['merk'] = Str::upper($value);
    }
}
