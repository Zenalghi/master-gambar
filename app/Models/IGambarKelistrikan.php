<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IGambarKelistrikan extends Model
{
    use HasFactory;
    protected $table = 'i_gambar_kelistrikan';

    protected $fillable = [
        'master_data_id',
        'master_kelistrikan_file_id',
        'deskripsi',
    ];

    public function masterData()
    {
        return $this->belongsTo(MasterData::class, 'master_data_id');
    }
    public function fileKelistrikan()
    {
        return $this->belongsTo(MasterKelistrikanFile::class, 'master_kelistrikan_file_id');
    }
    public function getPathGambarKelistrikanAttribute()
    {
        return $this->fileKelistrikan->path_file ?? null;
    }
}
