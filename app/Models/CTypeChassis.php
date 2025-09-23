<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CTypeChassis extends Model
{
    use HasFactory;

    protected $table = 'c_type_chassis';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'type_chassis'];

    /**
     * ACCESSOR: Membuat atribut virtual 'merk_id' dari 4 digit pertama 'id'.
     * Ini digunakan untuk relasi 'merk()'.
     */
    public function getMerkIdAttribute(): string
    {
        return substr($this->id, 0, 4);
    }

    /**
     * RELASI ELOQUENT (Induk): Setiap Tipe Sasis dimiliki oleh satu Merk.
     * Ini adalah relasi Eloquent yang sebenarnya.
     */
    public function merk(): BelongsTo
    {
        return $this->belongsTo(BMerk::class, 'merk_id');
    }

    /**
     * METHOD PEMBANTU (Anak): Untuk mendapatkan semua turunan Jenis Kendaraan.
     * Ini BUKAN relasi Eloquent standar, tapi sebuah method untuk mengambil data
     * berdasarkan pola ID.
     */
    public function getJenisKendaraanChildren()
    {
        // Langsung query ke model DJenisKendaraan dengan pola LIKE
        return DJenisKendaraan::where('id', 'like', $this->id . '%')->get();
    }

    public function gambarKelistrikan(): HasOne
    {
        return $this->hasOne(IGambarKelistrikan::class, 'c_type_chassis_id');
    }
}
