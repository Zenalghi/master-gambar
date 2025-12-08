<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IGambarKelistrikan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'i_gambar_kelistrikan';

    protected $fillable = [
        'master_data_id',
        'master_kelistrikan_file_id',
        'deskripsi',
    ];

    // Relasi ke Master Data (untuk tahu ini milik varian apa)
    public function masterData()
    {
        return $this->belongsTo(MasterData::class, 'master_data_id');
    }

    // Relasi ke File Fisik (untuk ambil PDF)
    public function fileKelistrikan()
    {
        return $this->belongsTo(MasterKelistrikanFile::class, 'master_kelistrikan_file_id');
    }

    // Helper untuk mendapatkan path dengan mudah
    public function getPathGambarKelistrikanAttribute()
    {
        return $this->fileKelistrikan->path_file ?? null;
    }
}
