<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skrb extends Model
{
    use HasFactory;

    protected $table = 'skrbs';

    protected $fillable = [
        'id_skrb',
        'transaksi_id',
        'customer_id',
        'bulan_tahun',
        'nomor_urut',
        'is_tdp_updated_by_admin',
        'snapshot_documents',
        'custom_files',
        'hidden_flags',
        'fase',
    ];

    protected $casts = [
        'is_tdp_updated_by_admin' => 'boolean',
        'snapshot_documents' => 'array',
        'custom_files' => 'array',
        'hidden_flags' => 'array',
        'nomor_urut' => 'integer',
        'fase' => 'integer',
    ];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    public function histories()
    {
        return $this->hasMany(SkrbHistory::class, 'skrb_id')->latest();
    }
}
