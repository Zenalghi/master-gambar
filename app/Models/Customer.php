<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terhubung dengan model ini.
     * Laravel akan otomatis mengasumsikan 'customers' jika tidak didefinisikan.
     *
     * @var string
     */
    protected $table = 'customers';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama_pt',
        'pj',
        'jabatan',
        'signature_pj',
        'nama_drafter',
        'signature_drafter',
        'nama_pemeriksa',
        'signature_pemeriksa',
    ];

    protected function namaPt(): Attribute
    {
        return Attribute::make(
            set: fn($value) => strtoupper($value),
        );
    }

    protected function jabatan(): Attribute
    {
        return Attribute::make(
            set: fn($value) => $value ? strtoupper($value) : null,
        );
    }

    // --- TAMBAHAN OPSIONAL: Auto Uppercase ---
    protected function namaDrafter(): Attribute
    {
        return Attribute::make(
            set: fn($value) => $value ? strtoupper($value) : null,
        );
    }

    protected function namaPemeriksa(): Attribute
    {
        return Attribute::make(
            set: fn($value) => $value ? strtoupper($value) : null,
        );
    }

    /**
     * Relasi ke DocumentCustomer.
     */
    public function documentCustomer(): HasOne
    {
        return $this->hasOne(DocumentCustomer::class);
    }
}
