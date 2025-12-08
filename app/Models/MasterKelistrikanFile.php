<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKelistrikanFile extends Model
{
    use HasFactory;
    protected $table = 'master_kelistrikan_files';
    protected $fillable = ['c_type_chassis_id', 'path_file'];

    public function chassis()
    {
        return $this->belongsTo(CTypeChassis::class, 'c_type_chassis_id');
    }
}
