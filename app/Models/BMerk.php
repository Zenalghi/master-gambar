<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BMerk extends Model
{
    use HasFactory, SoftDeletes; // <-- Tambah SoftDeletes

    protected $table = 'b_merks';
    protected $fillable = ['merk'];

    public function setMerkAttribute($value)
    {
        $this->attributes['merk'] = Str::upper($value);
    }

    // public function typeEngine(): BelongsTo
    // {
    //     return $this->belongsTo(ATypeEngine::class, 'a_type_engine_id')->withTrashed();
    // }
}
