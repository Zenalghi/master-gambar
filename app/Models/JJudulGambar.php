<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JJudulGambar extends Model
{
    use HasFactory;

    protected $table = 'j_judul_gambars';

    protected $fillable = ['nama_judul'];
}
