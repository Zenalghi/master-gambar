<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKelistrikanFile extends Model
{
    use HasFactory;

    protected $table = 'master_kelistrikan_files';

    protected $fillable = [
        'a_type_engine_id',
        'b_merk_id',
        'c_type_chassis_id',
        'path_file'
    ];

    public function typeEngine()
    {
        return $this->belongsTo(ATypeEngine::class, 'a_type_engine_id')->withTrashed();
    }
    public function merk()
    {
        return $this->belongsTo(BMerk::class, 'b_merk_id')->withTrashed();
    }
    public function typeChassis()
    {
        return $this->belongsTo(CTypeChassis::class, 'c_type_chassis_id')->withTrashed();
    }
}
