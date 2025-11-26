<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaksi extends Model
{
    use HasFactory;
    // use SoftDeletes; // Uncomment jika tabel z_transaksi punya kolom deleted_at

    protected $table = 'z_transaksi';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'master_data_id', // <-- KOLOM BARU YANG PENTING
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
            $prefix = date('my'); // Format MMYY

            // Cari ID terakhir dengan prefix yang sama
            $lastTransaksi = static::where('id', 'like', $prefix . '-%')
                ->orderBy('id', 'desc')
                ->first();

            $counter = 1;
            if ($lastTransaksi) {
                $counter = (int)substr($lastTransaksi->id, -4) + 1;
            }

            $model->id = $prefix . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
        });
    }

    // Relasi ke MasterData (PENTING untuk Controller)
    public function masterData(): BelongsTo
    {
        return $this->belongsTo(MasterData::class, 'master_data_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fPengajuan(): BelongsTo
    {
        return $this->belongsTo(FPengajuan::class, 'f_pengajuan_id');
    }

    public function detail(): HasOne
    {
        return $this->hasOne(TransaksiDetail::class, 'z_transaksi_id');
    }
}
