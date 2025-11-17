<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // <-- Import
use Illuminate\Database\Eloquent\SoftDeletes; // <-- Import
use Illuminate\Support\Str; // <-- Import

class Transaksi extends Model
{
    use HasFactory, SoftDeletes; // <-- Tambahkan SoftDeletes

    protected $table = 'z_transaksi';
    public $incrementing = false;
    protected $keyType = 'string';

    // Sesuaikan $fillable dengan migrasi baru
    protected $fillable = [
        'id',
        'master_data_id', // <-- BERUBAH
        'f_pengajuan_id',
        'customer_id',
        'user_id',
    ];

    /**
     * Boot logic untuk membuat ID mmyy-xxxx secara otomatis.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // 1. Dapatkan format mmyy (misal: 1125)
            $prefix = date('my');

            // 2. Cari ID terakhir di bulan & tahun ini
            $lastTransaksi = static::where('id', 'like', $prefix . '-%')
                ->orderBy('id', 'desc')
                ->first();

            $counter = 1;
            if ($lastTransaksi) {
                // 3. Ambil counter (xxxx) dari ID terakhir dan tambahkan 1
                $counter = (int)substr($lastTransaksi->id, -4) + 1;
            }

            // 4. Buat ID baru
            $model->id = $prefix . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
        });
    }

    public function masterData(): BelongsTo
    {
        return $this->belongsTo(MasterData::class, 'master_data_id')->withTrashed();
    }

    // Relasi yang sudah ada
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function fPengajuan()
    {
        return $this->belongsTo(FPengajuan::class, 'f_pengajuan_id');
    }

    public function detail(): HasOne
    {
        return $this->hasOne(TransaksiDetail::class, 'z_transaksi_id');
    }
}
