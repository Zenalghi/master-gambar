<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CTypeChassis extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'c_type_chassis';
    // ID sekarang auto-increment integer
    protected $fillable = ['type_chassis'];

    public function setTypeChassisAttribute($value)
    {
        $this->attributes['type_chassis'] = Str::upper($value);
    }

    // Relasi ke Gambar Kelistrikan masih valid karena terhubung langsung
    public function gambarKelistrikan(): HasOne
    {
        return $this->hasOne(IGambarKelistrikan::class, 'c_type_chassis_id');
    }
    // Relasi ke File Fisik Kelistrikan
    public function fileKelistrikan()
    {
        return $this->hasOne(MasterKelistrikanFile::class, 'c_type_chassis_id');
    }
}
