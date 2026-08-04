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
        'master_data_id',
        'jenis_pengajuan_id',
        'customer_id',
        'bulan_tahun',
        'nomor_urut',
        'is_tdp_updated_by_admin',
        'foto_copy_skrb',
        'tanggal_permohonan',
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
        'master_data_id' => 'integer',
        'jenis_pengajuan_id' => 'integer',
        'fase' => 'integer',
    ];

    /**
     * Relasi ke Transaksi (nullable — hanya ada untuk Cara 1)
     */
    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    /**
     * Relasi ke MasterData (untuk Cara 2 — tanpa transaksi)
     */
    public function masterData()
    {
        return $this->belongsTo(\App\Models\MasterData::class, 'master_data_id')->withTrashed();
    }

    /**
     * Relasi ke FPengajuan (untuk Cara 2 — tanpa transaksi)
     */
    public function fPengajuan()
    {
        return $this->belongsTo(\App\Models\FPengajuan::class, 'jenis_pengajuan_id');
    }

    /**
     * Relasi ke Customer
     */
    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id');
    }

    /**
     * Helper untuk mendapatkan identifier folder storage (karena transaksi_id nullable pada Cara 2)
     */
    public function getStorageKey(): string
    {
        return !empty($this->transaksi_id) ? (string) $this->transaksi_id : ('standalone-' . $this->id);
    }

    public function histories()
    {
        return $this->hasMany(SkrbHistory::class, 'skrb_id')->latest();
    }
}

