<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentCustomer extends Model
{
    protected $table = 'document_customers';

    protected $fillable = [
        'customer_id',
        'kop_surat',
        'data_umum',
        'tdp_files',
        'tdp_masa_berlaku',
        'permohonan_skrb',
        'permohonan_rekom',
        'alamat_permohonan',
        'bidang_usaha',
        'alamat_lengkap',
    ];

    protected $casts = [
        'tdp_files' => 'array',
        'tdp_masa_berlaku' => 'date',
    ];

    /**
     * Accessor: Kalkulasi Status TDP otomatis berdasarkan masa berlaku.
     *
     * - Aktif   : > 5 pekan sebelum expired
     * - WARNING : <= 5 pekan sebelum expired
     * - Expired : sudah lewat masa berlaku
     * - null    : masa berlaku belum diisi
     */
    protected $appends = ['status_tdp'];

    public function getStatusTdpAttribute(): ?string
    {
        if (!$this->tdp_masa_berlaku) {
            return null;
        }

        $now = Carbon::now()->startOfDay();
        $expiry = Carbon::parse($this->tdp_masa_berlaku)->startOfDay();

        if ($now->greaterThan($expiry)) {
            return 'Expired';
        }

        // 5 pekan = 35 hari
        $warningThreshold = $expiry->copy()->subWeeks(5);

        if ($now->greaterThanOrEqualTo($warningThreshold)) {
            return 'WARNING';
        }

        return 'Aktif';
    }

    /**
     * Relasi ke Customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
