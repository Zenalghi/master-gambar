<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkrbSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipient_address',
        'ignore_names',
    ];

    protected $casts = [
        'ignore_names' => 'array',
    ];
}
