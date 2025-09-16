<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'pj', // Penanggung Jawab
        'signature_pj', // Path ke file gambar tanda tangan penanggung jawab
    ];
}