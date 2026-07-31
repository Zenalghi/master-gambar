<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkrbHistory extends Model
{
    use HasFactory;

    protected $table = 'skrb_histories';

    protected $fillable = [
        'skrb_id',
        'file_name',
        'storage_path',
        'file_size',
    ];

    protected $casts = [
        'skrb_id' => 'integer',
        'file_size' => 'integer',
    ];

    public function skrb()
    {
        return $this->belongsTo(Skrb::class, 'skrb_id');
    }
}
