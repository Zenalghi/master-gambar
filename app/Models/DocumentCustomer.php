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
        'kop_surat_file',
        'data_umum_file',
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
        'tdp_masa_berlaku' => 'date:Y-m-d',
    ];

    /**
     * Accessor: Kalkulasi Status TDP otomatis berdasarkan masa berlaku.
     *
     * - Aktif   : > 5 pekan sebelum expired
     * - WARNING : <= 5 pekan sebelum expired
     * - Expired : sudah lewat masa berlaku
     * - null    : masa berlaku belum diisi
     */
    protected $appends = ['status_tdp', 'kop_surat_size', 'data_umum_size'];

    public function getKopSuratSizeAttribute(): int
    {
        if (!$this->kop_surat_file) {
            return 0;
        }
        $disk = \Illuminate\Support\Facades\Storage::disk('customer-documents');
        return $disk->exists($this->kop_surat_file) ? (int) $disk->size($this->kop_surat_file) : 0;
    }

    public function getDataUmumSizeAttribute(): int
    {
        if (!$this->data_umum_file) {
            return 0;
        }
        $disk = \Illuminate\Support\Facades\Storage::disk('customer-documents');
        return $disk->exists($this->data_umum_file) ? (int) $disk->size($this->data_umum_file) : 0;
    }

    public function getTdpFilesAttribute($value)
    {
        $files = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($files)) {
            return [];
        }
        $disk = \Illuminate\Support\Facades\Storage::disk('customer-documents');
        foreach ($files as &$file) {
            if (!isset($file['size']) || $file['size'] == 0) {
                $path = $file['path'] ?? null;
                $file['size'] = ($path && $disk->exists($path)) ? (int) $disk->size($path) : 0;
            }
        }
        return $files;
    }

    public function getStatusTdpAttribute(): ?string
    {
        if (!$this->tdp_masa_berlaku) {
            return null;
        }

        // Gunakan standar waktu WIB (Asia/Jakarta) dan reset ke awal hari (00:00:00)
        $now = Carbon::now('Asia/Jakarta')->startOfDay();
        $expiry = Carbon::parse($this->tdp_masa_berlaku, 'Asia/Jakarta')->startOfDay();

        if ($now->greaterThan($expiry)) {
            return 'Expired';
        }

        // 5 pekan = 35 hari
        $warningThreshold = $expiry->copy()->subDays(35);

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
