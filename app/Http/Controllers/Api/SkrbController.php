<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentCustomer;
use App\Models\Skrb;
use App\Models\SkrbHistory;
use App\Models\SkrbSetting;
use App\Models\Transaksi;
use App\Support\MasterPdf;
use App\Support\SKRB_template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SkrbController extends Controller
{
    private function syncSnapshotIfOpen(Skrb $skrb, bool $force = false, bool $rebuildGambarUtama = false, bool $asPendingMetadata = false): void
    {
        if ($skrb->fase == 2 && !$force) {
            return; // Jika Fase 2 (Tersimpan & Terkunci), jangan ubah snapshot history yang ter-save kecuali saat simpan ulang
        }

        if (!$skrb->relationLoaded('transaksi')) {
            $skrb->load(['transaksi.customer', 'transaksi.fPengajuan', 'transaksi.masterData.typeEngine', 'transaksi.masterData.merk', 'transaksi.masterData.typeChassis', 'transaksi.masterData.jenisKendaraan']);
        }
        if (!$skrb->relationLoaded('customer') && $skrb->customer_id) {
            $skrb->load('customer');
        }
        if (!$skrb->relationLoaded('masterData') && $skrb->master_data_id) {
            $skrb->load(['masterData.typeEngine', 'masterData.merk', 'masterData.typeChassis', 'masterData.jenisKendaraan']);
        }
        if (!$skrb->relationLoaded('fPengajuan') && $skrb->jenis_pengajuan_id) {
            $skrb->load('fPengajuan');
        }

        $trx = $skrb->transaksi;
        $customer = $skrb->customer ?: ($trx ? $trx->customer : null);
        $md = $skrb->masterData ?: ($trx ? $trx->masterData : null);
        $fPengajuan = $skrb->fPengajuan ?: ($trx ? $trx->fPengajuan : null);
        $customerId = $skrb->customer_id ?: ($customer ? $customer->id : null);

        $doc = $customerId ? DocumentCustomer::where('customer_id', $customerId)->first() : null;
        $snapshot = $skrb->snapshot_documents ?? [];
        $originalSnapshot = $snapshot;

        if ($customer) {
            $snapshot['customer_name'] = $customer->nama_pt ?: '-';
            $snapshot['customer_pj'] = $customer->pj ?: '-';
            $snapshot['customer_jabatan'] = $customer->jabatan ?: null;
            $snapshot['customer_alamat_kantor'] = $customer->alamat_kantor ?: null;
        }

        if ($doc) {
            $snapshot['alamat_permohonan'] = $doc->alamat_permohonan ?: null;
            $snapshot['alamat_lengkap'] = $doc->alamat_lengkap ?: null;
            $snapshot['customer_alamat'] = $doc->alamat_permohonan ?: ($doc->alamat_lengkap ?: ($snapshot['customer_alamat_kantor'] ?? null));
            $snapshot['bidang_usaha'] = $doc->bidang_usaha ?: null;
            $snapshot['data_umum_file'] = $doc->data_umum_file;
            $snapshot['tdp_files'] = is_array($doc->tdp_files) ? $doc->tdp_files : [];
            unset($snapshot['tdp_list'], $snapshot['data_umum'], $snapshot['kop_surat'], $snapshot['sut_pdf_path']);
            $snapshot['kop_surat_file'] = $doc->kop_surat_file ?? null;
            $snapshot['tdp_masa_berlaku_saved'] = $doc->tdp_masa_berlaku ? \Carbon\Carbon::parse($doc->tdp_masa_berlaku)->format('Y-m-d') : null;
        }

        if ($md) {
            $snapshot['type_engine'] = $md->typeEngine ? ($md->typeEngine->type_engine ?: '-') : '-';
            $snapshot['merk'] = $md->merk ? ($md->merk->merk ?: null) : null;
            if ($md->typeChassis) {
                $snapshot['sut_file'] = $md->typeChassis->sut_file;
                $snapshot['type_chassis'] = $md->typeChassis->type_chassis ?: null;
                $snapshot['merek_dagang'] = $md->typeChassis->merek_dagang ?: null;
                $snapshot['nomor_sut'] = $md->typeChassis->nomor_sut ?: null;
                $snapshot['jenis_tipe'] = $md->typeChassis->jenis_tipe ?: null;
            }
            if ($md->jenisKendaraan) {
                $snapshot['jenis_kendaraan'] = $md->jenisKendaraan->jenis_kendaraan ?: null;
                $snapshot['alias_kendaraan'] = $md->jenisKendaraan->alias_kendaraan ?: null;
            }
        }

        if ($fPengajuan) {
            $snapshot['jenis_pengajuan'] = $fPengajuan->jenis_pengajuan ?: 'Varian';
        }

        if ($trx && $rebuildGambarUtama) {
            // [LOGIKA BARU]: Langsung ambil metadata (teks judul & varian body) tanpa proses background / generate file fisik:
            $snapshot['gambar_utama_list'] = ProsesTransaksiController::extractGambarUtamaMetadataForSkrb($trx);
            $snapshot['gambar_status'] = 'ready';
        }

        if ($originalSnapshot !== $snapshot || $force) {
            $skrb->snapshot_documents = $snapshot;
            $skrb->saveQuietly();
        }
    }

    /**
     * Best practice naming: pembersihan nama file merge murni urusan backend berdasarkan konfigurasi DB.
     */
    private function getCleanMergedFileName(Skrb $skrb, array $snapshot): array
    {
        $idSkrb = $skrb->id_skrb ?? '';
        $prefixStr = now()->format('d-m-y'); // Fallback jika format ID tidak dikenali
        $debugSource = 'FALLBACK_DATE';

        if (!empty($idSkrb)) {
            $parts = explode('/', $idSkrb);
            $c = count($parts);
            
            // Minimal ada 3 atau 4 bagian (contoh: 29/SJM-SKRB/IX/2026 -> 4 bagian)
            if ($c >= 3) {
                // Nomor urut selalu di awal
                $nomor = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                
                // Bulan romawi selalu di elemen ke-2 dari belakang
                $romawi = $parts[$c - 2];
                $mapRomawi = [
                    'I' => '01', 'II' => '02', 'III' => '03', 'IV' => '04',
                    'V' => '05', 'VI' => '06', 'VII' => '07', 'VIII' => '08',
                    'IX' => '09', 'X' => '10', 'XI' => '11', 'XII' => '12'
                ];
                $bulan = $mapRomawi[strtoupper($romawi)] ?? now()->format('m');
                
                // Tahun selalu di elemen terakhir
                $tahun = $parts[$c - 1];
                $tahun2Digit = strlen($tahun) >= 2 ? substr($tahun, -2) : str_pad($tahun, 2, '0', STR_PAD_LEFT);
                
                $prefixStr = sprintf("%s-%s-%s", $nomor, $bulan, $tahun2Digit);
                $debugSource = 'ID_SKRB_PARSED';
            } else {
                $debugSource = 'ID_SKRB_FORMAT_INVALID';
            }
        }

        // \Illuminate\Support\Facades\Log::info("DEBUG_SKRB_FILENAME", [
        //     'id_skrb' => $idSkrb,
        //     'source' => $debugSource,
        //     'prefixStr' => $prefixStr
        // ]);

        $pengajuanStr = strtoupper($snapshot['jenis_pengajuan'] ?? 'VARIAN');
        $chassisStr = strtoupper($snapshot['type_chassis'] ?? '');
        $dagangVal = trim((string) ($snapshot['merek_dagang'] ?? ''));
        $dagangStr = !empty($dagangVal) ? strtoupper($dagangVal) : null;
        $kendaraanStr = strtoupper($snapshot['jenis_kendaraan'] ?? '');

        if ($dagangStr) {
            $rawName = sprintf("%s PERMOHONAN SKRB (%s) %s %s (%s)", $prefixStr, $pengajuanStr, $chassisStr, $dagangStr, $kendaraanStr);
        } else {
            $rawName = sprintf("%s PERMOHONAN SKRB (%s) %s (%s)", $prefixStr, $pengajuanStr, $chassisStr, $kendaraanStr);
        }
        
        // Ambil murni dari DB skrb_settings Tanpa hardcode fallback
        $skrbSetting = SkrbSetting::first();
        $ignoreNames = ($skrbSetting && is_array($skrbSetting->ignore_names)) ? $skrbSetting->ignore_names : [];
        
        foreach ($ignoreNames as $ign) {
            if (!empty(trim($ign))) {
                $rawName = str_ireplace(trim($ign), '', $rawName);
            }
        }
        $rawName = trim(preg_replace('/\s+/', ' ', $rawName));

        $userDownloadName = $rawName . '.pdf';
        $cleanDownloadName = preg_replace('/[\\\\:*?"<>|]/', '_', $userDownloadName);

        // Karena bisa di-edit, maka perbedaan dalam storage BE tambahi belakangnya Ymd-His.pdf
        $storageFileName = $rawName . ' ' . now()->format('Ymd-His') . '.pdf';
        $cleanStorageFileName = preg_replace('/[\\\\:*?"<>|]/', '_', $storageFileName);

        return [
            'raw_name' => $rawName,
            'download_name' => $cleanDownloadName,
            'storage_name' => $cleanStorageFileName,
        ];
    }

    private function formatSkrb(Skrb $skrb)
    {
        $this->syncSnapshotIfOpen($skrb);
        if (!$skrb->relationLoaded('transaksi')) {
            $skrb->load(['transaksi.customer', 'transaksi.fPengajuan', 'transaksi.masterData.typeEngine', 'transaksi.masterData.merk', 'transaksi.masterData.typeChassis', 'transaksi.masterData.jenisKendaraan', 'histories']);
        }
        if (!$skrb->relationLoaded('customer') && $skrb->customer_id) {
            $skrb->load('customer');
        }
        if (!$skrb->relationLoaded('masterData') && $skrb->master_data_id) {
            $skrb->load(['masterData.typeEngine', 'masterData.merk', 'masterData.typeChassis', 'masterData.jenisKendaraan']);
        }
        if (!$skrb->relationLoaded('fPengajuan') && $skrb->jenis_pengajuan_id) {
            $skrb->load('fPengajuan');
        }
        $trx = $skrb->transaksi;
        $customer = $skrb->customer ?: ($trx ? $trx->customer : null);
        $md = $skrb->masterData ?: ($trx ? $trx->masterData : null);
        $fPengajuan = $skrb->fPengajuan ?: ($trx ? $trx->fPengajuan : null);
        $customerId = $skrb->customer_id ?: ($customer ? $customer->id : null);
        $snapshot = $skrb->snapshot_documents ?? [];
        
        $doc = $customerId ? DocumentCustomer::where('customer_id', $customerId)->first() : null;
        $hasDoc = $doc !== null;
        $statusTdp = '-';
        if ($doc) {
            $statusTdp = $doc->status_tdp ?: 'Tanpa TDP';
        }
        $masaBerlaku = $doc && $doc->tdp_masa_berlaku ? \Carbon\Carbon::parse($doc->tdp_masa_berlaku)->format('Y-m-d') : null;

        // Cek status Kop Surat
        $hasKopSurat = false;
        $kopSource = 'missing';
        $kopPath = $snapshot['kop_surat_file'] ?? ($snapshot['kop_surat'] ?? ($doc ? $doc->kop_surat_file : null));
        if (!empty($kopPath) && Storage::disk('customer-documents')->exists($kopPath)) {
            $hasKopSurat = true;
            $kopSource = 'customer';
        } elseif (file_exists("C:\\Nova\\Rekayasa\\Modul\\KOP.pdf")) {
            $hasKopSurat = true;
            $kopSource = 'local_fallback';
        }

        // Cek apakah TDP / Document Customer sudah di-update oleh admin
        $isTdpOutdated = (bool) $skrb->is_tdp_updated_by_admin;
        if (!$isTdpOutdated && $skrb->fase == 2) {
            $savedMasa = $snapshot['tdp_masa_berlaku_saved'] ?? null;
            if ($savedMasa !== $masaBerlaku && $savedMasa !== null) {
                $isTdpOutdated = true;
            }
        }
        if ($isTdpOutdated) {
            $statusTdp = 'Diperbarui Admin';
        }

        $fileNames = $this->getCleanMergedFileName($skrb, $snapshot);

        return [
            'id' => $skrb->id,
            'id_skrb' => $skrb->id_skrb,
            'transaksi_id' => $skrb->transaksi_id,
            'master_data_id' => $skrb->master_data_id ?: ($trx ? $trx->master_data_id : null),
            'jenis_pengajuan_id' => $skrb->jenis_pengajuan_id ?: ($trx ? $trx->f_pengajuan_id : null),
            'customer_id' => $skrb->customer_id ?: ($customer ? $customer->id : null),
            'customer_name' => $customer ? $customer->nama_pt : ($snapshot['customer_name'] ?? '-'),
            'type_engine' => ($md && $md->typeEngine) ? $md->typeEngine->type_engine : ($snapshot['type_engine'] ?? '-'),
            'merk' => ($md && $md->merk) ? $md->merk->merk : ($snapshot['merk'] ?? '-'),
            'type_chassis' => ($md && $md->typeChassis) ? $md->typeChassis->type_chassis : ($snapshot['type_chassis'] ?? '-'),
            'merek_dagang' => ($md && $md->typeChassis) ? $md->typeChassis->merek_dagang : ($snapshot['merek_dagang'] ?? null),
            'nomor_sut' => ($md && $md->typeChassis) ? $md->typeChassis->nomor_sut : ($snapshot['nomor_sut'] ?? null),
            'jenis_kendaraan' => ($md && $md->jenisKendaraan) ? $md->jenisKendaraan->jenis_kendaraan : ($snapshot['jenis_kendaraan'] ?? '-'),
            'jenis_pengajuan' => $fPengajuan ? $fPengajuan->jenis_pengajuan : ($snapshot['jenis_pengajuan'] ?? 'Varian'),
            'status_tdp' => $statusTdp,
            'tdp_masa_berlaku' => $masaBerlaku,
            'is_tdp_outdated' => $isTdpOutdated,
            'document_customer_id' => $doc ? $doc->id : ($snapshot['document_customer_id'] ?? null),
            'has_document_customer' => $hasDoc || !empty($snapshot['document_customer_id']) || !empty($snapshot['kop_surat_file']) || !empty($snapshot['data_umum_file']),
            'has_kop_surat' => $hasKopSurat,
            'kop_source' => $kopSource,
            'fase' => $skrb->fase,
            'foto_copy_skrb' => $skrb->foto_copy_skrb ?? ($snapshot['foto_copy_skrb'] ?? null),
            'tanggal_permohonan' => $skrb->tanggal_permohonan ? \Carbon\Carbon::parse($skrb->tanggal_permohonan)->format('Y-m-d') : ($snapshot['tanggal_permohonan'] ?? null),
            'suggested_file_name' => $fileNames['download_name'],
            'custom_files' => $skrb->custom_files ?? [],
            'hidden_flags' => $skrb->hidden_flags ?? [],
            'snapshot_documents' => $snapshot,
            'histories' => $skrb->histories,
            'created_at' => $skrb->created_at ? $skrb->created_at->format('Y-m-d H:i:s') : '-',
            'updated_at' => $skrb->updated_at ? $skrb->updated_at->format('Y-m-d H:i:s') : '-',
        ];
    }

    /**
     * Daftar semua Permohonan SKRB untuk tabel di Flutter
     */
    public function index(Request $request)
    {
        $skrbs = Skrb::with(['transaksi.customer', 'transaksi.fPengajuan', 'transaksi.masterData.typeEngine', 'transaksi.masterData.merk', 'transaksi.masterData.typeChassis', 'transaksi.masterData.jenisKendaraan', 'histories'])->latest()->get();

        $data = $skrbs->map(function ($skrb) {
            return $this->formatSkrb($skrb);
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Daftar transaksi yang sudah punya detail dan belum dibuatkan SKRB (untuk DropdownSearch di Header)
     * - Exclude transaksi yang Jenis Pengajuannya adalah GAMBAR TU (id = 4)
     * - Exclude transaksi yang transaksi_id-nya sudah digunakan pada tabel skrbs
     */
    public function availableTransactions()
    {
        // Hanya ambil transaksi_id yang tidak null (Cara 1)
        $usedIds = Skrb::whereNotNull('transaksi_id')->pluck('transaksi_id')->toArray();
        $transactions = Transaksi::with(['customer', 'fPengajuan', 'masterData.typeEngine', 'masterData.merk', 'masterData.typeChassis', 'masterData.jenisKendaraan', 'detail'])
            ->whereHas('detail')
            ->whereNotIn('id', $usedIds)
            ->where(function ($q) {
                // Exclude jenis pengajuan GAMBAR TU (id=4)
                $q->whereHas('fPengajuan', function ($q2) {
                    $q2->whereNotIn('id', [4]);
                })->orWhereNull('f_pengajuan_id');
            })
            ->latest()
            ->get();

        $data = $transactions->map(function ($trx) {
            return [
                'id' => $trx->id,
                'customer_id' => $trx->customer_id,
                'master_data_id' => $trx->master_data_id,
                'jenis_pengajuan_id' => $trx->f_pengajuan_id,
                'customer_name' => $trx->customer ? $trx->customer->nama_pt : '-',
                'type_engine' => $trx->masterData && $trx->masterData->typeEngine ? $trx->masterData->typeEngine->type_engine : '-',
                'merk' => $trx->masterData && $trx->masterData->merk ? $trx->masterData->merk->merk : '-',
                'type_chassis' => $trx->masterData && $trx->masterData->typeChassis ? $trx->masterData->typeChassis->type_chassis : '-',
                'merek_dagang' => $trx->masterData && $trx->masterData->typeChassis ? $trx->masterData->typeChassis->merek_dagang : null,
                'nomor_sut' => $trx->masterData && $trx->masterData->typeChassis ? $trx->masterData->typeChassis->nomor_sut : null,
                'jenis_kendaraan' => $trx->masterData && $trx->masterData->jenisKendaraan ? $trx->masterData->jenisKendaraan->jenis_kendaraan : '-',
                'jenis_pengajuan' => $trx->fPengajuan ? $trx->fPengajuan->jenis_pengajuan : 'Varian',
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Mengecek apakah SKRB untuk ID transaksi tertentu sudah dibuat atau belum.
     * Jika sudah ada, kembalikan data SKRB. Jika belum, kembalikan null.
     */
    public function getByTransaksi($transaksiId)
    {
        $existing = Skrb::where('transaksi_id', $transaksiId)->first();
        if ($existing) {
            $formatted = $this->formatSkrb($existing);
            $formatted['already_exists'] = true;
            return response()->json([
                'exists' => true,
                'data' => $formatted,
            ], 200);
        }

        return response()->json([
            'exists' => false,
            'data' => null,
        ], 200);
    }

    /**
     * Preview ID SKRB sistem yang akan dibuat (untuk dialog konfirmasi di Flutter sebelum store)
     * Query: GET /skrbs/preview-id?customer_id=X
     */
    public function previewIdSkrb(Request $request)
    {
        $request->validate(['customer_id' => 'required|integer']);

        $customerId = (int) $request->customer_id;
        $today = now();
        $bulanRomawi = $this->toRoman($today->month);
        $tahun = $today->year;
        $bulanTahun = $today->format('m-Y');

        $lastUrut = Skrb::where('customer_id', $customerId)->where('bulan_tahun', $bulanTahun)->max('nomor_urut') ?? 0;
        $nomorUrut = $lastUrut + 1;
        $strUrut = str_pad($nomorUrut, 2, '0', STR_PAD_LEFT);

        $docCustomer = DocumentCustomer::where('customer_id', $customerId)->first();
        $hasCustomPermohonan = ($docCustomer && !empty(trim($docCustomer->permohonan_skrb ?? '')));

        if (!$hasCustomPermohonan) {
            return response()->json([
                'message' => "Data Customer belum ditambahkan\nhubungi admin"
            ], 422);
        }

        $permohonanDoc = trim($docCustomer->permohonan_skrb);
        $previewId = sprintf("%s/%s/%s/%s", $strUrut, $permohonanDoc, $bulanRomawi, $tahun);
        // Cek apakah preview ID sudah terpakai, jika ya naikan urut
        $tempUrut = $nomorUrut;
        while (Skrb::where('id_skrb', $previewId)->exists()) {
            $tempUrut++;
            $tempStr = str_pad($tempUrut, 2, '0', STR_PAD_LEFT);
            $previewId = sprintf("%s/%s/%s/%s", $tempStr, $permohonanDoc, $bulanRomawi, $tahun);
        }

        return response()->json([
            'preview_id_skrb' => $previewId,
            'nomor_urut_sistem' => $nomorUrut,
            'has_custom_permohonan' => $hasCustomPermohonan,
        ]);
    }

    /**
     * Helper private: Hitung nomor urut & ID SKRB berdasarkan customer_id, bulan-tahun, dan opsional nomor manual.
     * Return: ['idSkrb', 'nomorUrut', 'strUrut', 'permohonanDoc']
     */
    private function generateIdSkrb(int $customerId, ?int $nomorUrutManual = null, ?int $excludeSkrbId = null): array
    {
        $today = now();
        $bulanRomawi = $this->toRoman($today->month);
        $tahun = $today->year;
        $bulanTahun = $today->format('m-Y');

        $docCustomer = DocumentCustomer::where('customer_id', $customerId)->first();
        $hasCustomPermohonan = ($docCustomer && !empty(trim($docCustomer->permohonan_skrb ?? '')));

        if (!$hasCustomPermohonan) {
            throw new \Exception("Data Customer belum ditambahkan\nhubungi admin");
        }

        if ($nomorUrutManual !== null) {
            // Mode Kustom / Ubah Nomor Urut: user menentukan nomor urut
            $nomorUrut = $nomorUrutManual;
            $strUrut = str_pad($nomorUrut, 2, '0', STR_PAD_LEFT);

            $permohonanDoc = trim($docCustomer->permohonan_skrb);
            $idSkrb = sprintf("%s/%s/%s/%s", $strUrut, $permohonanDoc, $bulanRomawi, $tahun);

            // Validasi uniqueness
            $query = Skrb::where('id_skrb', $idSkrb);
            if ($excludeSkrbId !== null) {
                $query->where('id', '!=', $excludeSkrbId);
            }
            if ($query->exists()) {
                throw new \Exception("ID SKRB '{$idSkrb}' sudah terdaftar! ID SKRB harus unik dan tidak boleh sama persis dengan ID lainnya. Harap ubah nomor urut Anda.");
            }
        } else {
            // Mode Sistem: auto-increment
            $lastUrut = Skrb::where('customer_id', $customerId)->where('bulan_tahun', $bulanTahun)->max('nomor_urut') ?? 0;
            $nomorUrut = $lastUrut + 1;
            $strUrut = str_pad($nomorUrut, 2, '0', STR_PAD_LEFT);

            $permohonanDoc = trim($docCustomer->permohonan_skrb);
            $idSkrb = sprintf("%s/%s/%s/%s", $strUrut, $permohonanDoc, $bulanRomawi, $tahun);
            while (Skrb::where('id_skrb', $idSkrb)->exists()) {
                $nomorUrut++;
                $strUrut = str_pad($nomorUrut, 2, '0', STR_PAD_LEFT);
                $idSkrb = sprintf("%s/%s/%s/%s", $strUrut, $permohonanDoc, $bulanRomawi, $tahun);
            }
        }

        return [
            'idSkrb' => $idSkrb,
            'nomorUrut' => $nomorUrut,
            'strUrut' => $strUrut,
            'bulanTahun' => $bulanTahun,
            'permohonanDoc' => $permohonanDoc ?? null,
            'hasCustomPermohonan' => $hasCustomPermohonan,
        ];
    }

    /**
     * Membuat rekor Permohonan SKRB baru.
     * Cara 1: dari ID transaksi (transaksi_id wajib ada) → data kendaraan & customer dari transaksi.
     * Cara 2: dari customer_id + master_data_id + jenis_pengajuan_id → tanpa transaksi.
     * Keduanya support nomor_urut_manual (untuk ID SKRB kustom).
     */
    public function store(Request $request)
    {
        // Tentukan cara: Cara 1 (ada transaksi_id) atau Cara 2 (tidak ada transaksi_id)
        $isCara1 = $request->has('transaksi_id') && !empty($request->transaksi_id);

        if ($isCara1) {
            // === CARA 1: Buat SKRB dari ID Transaksi ===
            $request->validate([
                'transaksi_id' => 'required|string|exists:z_transaksi,id',
                'nomor_urut_manual' => 'nullable|integer|min:1',
            ]);

            $transaksiId = $request->transaksi_id;

            // Cara 1: 1 transaksi_id hanya boleh punya 1 SKRB → arahkan ke existing jika ada
            $existing = Skrb::where('transaksi_id', $transaksiId)->first();
            if ($existing) {
                $formatted = $this->formatSkrb($existing);
                $formatted['already_exists'] = true;
                return response()->json([
                    'message' => 'Permohonan SKRB untuk transaksi ini sudah ada. Mengalihkan ke Detail SKRB.',
                    'data' => $formatted,
                    'already_exists' => true,
                ], 200);
            }

            $trx = Transaksi::with(['customer', 'fPengajuan', 'masterData.typeEngine', 'masterData.merk', 'masterData.typeChassis', 'masterData.jenisKendaraan', 'detail'])
                ->findOrFail($transaksiId);

            $customerId = $trx->customer_id;
            $nomorUrutManual = $request->has('nomor_urut_manual') ? (int) $request->nomor_urut_manual : null;

            try {
                $idData = $this->generateIdSkrb($customerId, $nomorUrutManual);
            } catch (\Exception $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            $idSkrb = $idData['idSkrb'];
            $nomorUrut = $idData['nomorUrut'];
            $bulanTahun = $idData['bulanTahun'];

            // Ekstrak metadata gambar utama
            $gambarUtamaList = ProsesTransaksiController::extractGambarUtamaMetadataForSkrb($trx);

            // Ambil dokumen customer
            $docCustomer = DocumentCustomer::where('customer_id', $customerId)->first();

            // Siapkan snapshot
            $snapshot = [
                'customer_name' => $trx->customer ? $trx->customer->nama_pt : '-',
                'document_customer_id' => $docCustomer ? $docCustomer->id : null,
                'has_document_customer' => $docCustomer !== null,
                'customer_pj' => $trx->customer ? $trx->customer->pj : null,
                'customer_jabatan' => $trx->customer ? $trx->customer->jabatan : null,
                'alamat_permohonan' => $docCustomer ? $docCustomer->alamat_permohonan : null,
                'alamat_lengkap' => $docCustomer ? $docCustomer->alamat_lengkap : ($trx->customer ? $trx->customer->alamat_kantor : null),
                'customer_alamat' => $docCustomer ? ($docCustomer->alamat_permohonan ?: $docCustomer->alamat_lengkap) : ($trx->customer ? $trx->customer->alamat_kantor : '-'),
                'bidang_usaha' => $docCustomer ? $docCustomer->bidang_usaha : null,
                'data_umum_file' => $docCustomer ? $docCustomer->data_umum_file : null,
                'tdp_files' => $docCustomer && is_array($docCustomer->tdp_files) ? $docCustomer->tdp_files : [],
                'kop_surat_file' => $docCustomer ? $docCustomer->kop_surat_file : null,
                'tdp_masa_berlaku_saved' => $docCustomer && $docCustomer->tdp_masa_berlaku ? \Carbon\Carbon::parse($docCustomer->tdp_masa_berlaku)->format('Y-m-d') : null,
                'sut_file' => $trx->masterData && $trx->masterData->typeChassis ? $trx->masterData->typeChassis->sut_file : null,
                'type_engine' => $trx->masterData && $trx->masterData->typeEngine ? $trx->masterData->typeEngine->type_engine : '-',
                'merk' => $trx->masterData && $trx->masterData->merk ? $trx->masterData->merk->merk : null,
                'type_chassis' => $trx->masterData && $trx->masterData->typeChassis ? $trx->masterData->typeChassis->type_chassis : null,
                'merek_dagang' => $trx->masterData && $trx->masterData->typeChassis ? $trx->masterData->typeChassis->merek_dagang : null,
                'jenis_tipe' => $trx->masterData && $trx->masterData->typeChassis ? $trx->masterData->typeChassis->jenis_tipe : null,
                'jenis_kendaraan' => $trx->masterData && $trx->masterData->jenisKendaraan ? $trx->masterData->jenisKendaraan->jenis_kendaraan : null,
                'alias_kendaraan' => $trx->masterData && $trx->masterData->jenisKendaraan ? $trx->masterData->jenisKendaraan->alias_kendaraan : null,
                'jenis_pengajuan' => $trx->fPengajuan ? $trx->fPengajuan->jenis_pengajuan : 'Varian',
                'gambar_utama_list' => $gambarUtamaList,
                'gambar_status' => 'ready',
            ];

            // Generate Surat Permohonan PDF
            $suratPath = $this->generateSuratPermohonanPdf($transaksiId, $idSkrb, $snapshot);
            if ($suratPath) {
                $snapshot['surat_permohonan_path'] = $suratPath;
            }

            try {
                $skrb = Skrb::create([
                    'id_skrb' => $idSkrb,
                    'transaksi_id' => $transaksiId,
                    'master_data_id' => $trx->master_data_id,
                    'jenis_pengajuan_id' => $trx->f_pengajuan_id,
                    'customer_id' => $customerId,
                    'bulan_tahun' => $bulanTahun,
                    'nomor_urut' => $nomorUrut,
                    'snapshot_documents' => $snapshot,
                    'custom_files' => [],
                    'hidden_flags' => [],
                    'fase' => 1,
                ]);
            } catch (\Exception $e) {
                // Race condition: cek sekali lagi
                $existing = Skrb::where('transaksi_id', $transaksiId)->first();
                if ($existing) {
                    $formatted = $this->formatSkrb($existing);
                    $formatted['already_exists'] = true;
                    return response()->json([
                        'message' => 'Permohonan SKRB untuk transaksi ini sudah ada. Mengalihkan ke Detail SKRB.',
                        'data' => $formatted,
                        'already_exists' => true,
                    ], 200);
                }
                // Jika bukan duplicate transaksi_id (misal duplicate id_skrb manual)
                if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                    return response()->json(['message' => 'ID SKRB sudah digunakan. Pilih nomor urut yang berbeda.'], 422);
                }
                throw $e;
            }

            $formatted = $this->formatSkrb($skrb);
            $formatted['already_exists'] = false;

            return response()->json([
                'message' => 'Permohonan SKRB berhasil dibuat!',
                'data' => $formatted,
                'already_exists' => false,
            ], 201);

        } else {
            // === CARA 2: Buat SKRB tanpa Transaksi (Customer + Kendaraan + Jenis Pengajuan) ===
            $request->validate([
                'customer_id' => 'required|integer|exists:customers,id',
                'master_data_id' => 'required|integer',
                'jenis_pengajuan_id' => 'required|integer|exists:f_pengajuan,id',
                'nomor_urut_manual' => 'nullable|integer|min:1',
            ]);

            // Pastikan jenis pengajuan bukan GAMBAR TU (id=4)
            if ((int)$request->jenis_pengajuan_id === 4) {
                return response()->json(['message' => 'Jenis Pengajuan GAMBAR TU tidak diperbolehkan untuk SKRB.'], 422);
            }

            $customerId = (int) $request->customer_id;
            $masterDataId = (int) $request->master_data_id;
            $jenisPengajuanId = (int) $request->jenis_pengajuan_id;
            $nomorUrutManual = $request->has('nomor_urut_manual') ? (int) $request->nomor_urut_manual : null;

            // Load relasi yang dibutuhkan
            $customer = \App\Models\Customer::findOrFail($customerId);
            $masterData = \App\Models\MasterData::with(['typeEngine', 'merk', 'typeChassis', 'jenisKendaraan'])->findOrFail($masterDataId);
            $fPengajuan = \App\Models\FPengajuan::findOrFail($jenisPengajuanId);

            try {
                $idData = $this->generateIdSkrb($customerId, $nomorUrutManual);
            } catch (\Exception $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            $idSkrb = $idData['idSkrb'];
            $nomorUrut = $idData['nomorUrut'];
            $bulanTahun = $idData['bulanTahun'];

            // Snapshot dari customer & master data (tanpa transaksi)
            $docCustomer = DocumentCustomer::where('customer_id', $customerId)->first();

            $snapshot = [
                'customer_name' => $customer->nama_pt ?? '-',
                'document_customer_id' => $docCustomer ? $docCustomer->id : null,
                'has_document_customer' => $docCustomer !== null,
                'customer_pj' => $customer->pj ?? null,
                'customer_jabatan' => $customer->jabatan ?? null,
                'customer_alamat_kantor' => $customer->alamat_kantor ?? null,
                'alamat_permohonan' => $docCustomer ? $docCustomer->alamat_permohonan : null,
                'alamat_lengkap' => $docCustomer ? $docCustomer->alamat_lengkap : ($customer->alamat_kantor ?? null),
                'customer_alamat' => $docCustomer ? ($docCustomer->alamat_permohonan ?: $docCustomer->alamat_lengkap) : ($customer->alamat_kantor ?? '-'),
                'bidang_usaha' => $docCustomer ? $docCustomer->bidang_usaha : null,
                'data_umum_file' => $docCustomer ? $docCustomer->data_umum_file : null,
                'tdp_files' => $docCustomer && is_array($docCustomer->tdp_files) ? $docCustomer->tdp_files : [],
                'kop_surat_file' => $docCustomer ? $docCustomer->kop_surat_file : null,
                'tdp_masa_berlaku_saved' => $docCustomer && $docCustomer->tdp_masa_berlaku ? \Carbon\Carbon::parse($docCustomer->tdp_masa_berlaku)->format('Y-m-d') : null,
                // Data kendaraan dari master data
                'sut_file' => $masterData->typeChassis ? $masterData->typeChassis->sut_file : null,
                'type_engine' => $masterData->typeEngine ? ($masterData->typeEngine->type_engine ?? '-') : '-',
                'merk' => $masterData->merk ? ($masterData->merk->merk ?? null) : null,
                'type_chassis' => $masterData->typeChassis ? ($masterData->typeChassis->type_chassis ?? null) : null,
                'merek_dagang' => $masterData->typeChassis ? ($masterData->typeChassis->merek_dagang ?? null) : null,
                'jenis_tipe' => $masterData->typeChassis ? ($masterData->typeChassis->jenis_tipe ?? null) : null,
                'jenis_kendaraan' => $masterData->jenisKendaraan ? ($masterData->jenisKendaraan->jenis_kendaraan ?? null) : null,
                'alias_kendaraan' => $masterData->jenisKendaraan ? ($masterData->jenisKendaraan->alias_kendaraan ?? null) : null,
                'jenis_pengajuan' => $fPengajuan->jenis_pengajuan ?? 'Varian',
                // Cara 2: tidak ada gambar utama dari transaksi — user isi manual di Detail SKRB
                'gambar_utama_list' => [],
                'gambar_status' => 'ready',
            ];

            // Generate Surat Permohonan PDF (tanpa transaksi_id, gunakan 'standalone-{skrb.id}' setelah create)
            // Surat permohonan di-generate setelah create agar ada ID untuk folder

            try {
                $skrb = Skrb::create([
                    'id_skrb' => $idSkrb,
                    'transaksi_id' => null, // Cara 2: tidak ada transaksi
                    'master_data_id' => $masterDataId,
                    'jenis_pengajuan_id' => $jenisPengajuanId,
                    'customer_id' => $customerId,
                    'bulan_tahun' => $bulanTahun,
                    'nomor_urut' => $nomorUrut,
                    'snapshot_documents' => $snapshot,
                    'custom_files' => [],
                    'hidden_flags' => [],
                    'fase' => 1,
                ]);
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                    return response()->json(['message' => 'ID SKRB sudah digunakan. Pilih nomor urut yang berbeda.'], 422);
                }
                throw $e;
            }

            // Generate Surat Permohonan PDF menggunakan ID SKRB baru
            $storageKey = $skrb->getStorageKey();
            $suratPath = $this->generateSuratPermohonanPdf($storageKey, $idSkrb, $snapshot);
            if ($suratPath) {
                $snapshot['surat_permohonan_path'] = $suratPath;
                $skrb->snapshot_documents = $snapshot;
                $skrb->saveQuietly();
            }

            $formatted = $this->formatSkrb($skrb);
            $formatted['already_exists'] = false;

            return response()->json([
                'message' => 'Permohonan SKRB berhasil dibuat (tanpa transaksi)!',
                'data' => $formatted,
                'already_exists' => false,
            ], 201);
        }
    }

    /**
     * Tampilkan detail tunggal SKRB beserta riwayat filenya
     */
    public function show(Skrb $skrb)
    {
        return response()->json(['data' => $this->formatSkrb($skrb)]);
    }

    /**
     * Generate file PDF untuk gambar utama (a, b, c, d) secara background dari antarmuka Detail SKRB
     */
    public function generateGambar(Skrb $skrb)
    {
        // [KOMENTAR FITUR LAMA]: Sebelumnya endpoint ini men-generate PDF fisik gambar utama a, b, c, d di background:
        /*
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $snapshot = $skrb->snapshot_documents ?? [];
        if (($snapshot['gambar_status'] ?? null) === 'ready' && !empty($snapshot['gambar_utama_list'])) {
            // Periksa apakah semua path sudah ada
            $allReady = true;
            foreach ($snapshot['gambar_utama_list'] as $g) {
                if (empty($g['path']) && !($skrb->custom_files[$g['key'] ?? ''] ?? null)) {
                    $allReady = false;
                    break;
                }
            }
            if ($allReady) {
                return response()->json([
                    'message' => 'Gambar utama sudah siap.',
                    'data' => $this->formatSkrb($skrb)
                ], 200);
            }
        }

        $lockKey = 'generate_gambar_skrb_' . $skrb->id;
        if (Cache::has($lockKey)) {
            return response()->json([
                'message' => 'Proses pembuatan gambar masih berlangsung di background. Harap tunggu...',
                'data' => $this->formatSkrb($skrb)
            ], 202);
        }
        Cache::put($lockKey, true, 300);

        try {
            $skrb->load('transaksi');
            $trx = $skrb->transaksi;

            if ($trx) {
                $snapshot['gambar_utama_list'] = ProsesTransaksiController::extractGambarUtamaForSkrb($trx);
            } else {
                $snapshot['gambar_utama_list'] = [];
            }

            $snapshot['gambar_status'] = 'ready';
            $skrb->snapshot_documents = $snapshot;
            $skrb->save();
        } finally {
            Cache::forget($lockKey);
        }
        */

        // [LOGIKA BARU]: Karena gambar utama hanya diambil teksnya untuk Surat Permohonan, langsung pastikan status 'ready'
        $snapshot = $skrb->snapshot_documents ?? [];
        $skrb->load('transaksi');
        $trx = $skrb->transaksi;

        if ($trx && empty($snapshot['gambar_utama_list'])) {
            $snapshot['gambar_utama_list'] = ProsesTransaksiController::extractGambarUtamaMetadataForSkrb($trx);
        }
        $snapshot['gambar_status'] = 'ready';
        $skrb->snapshot_documents = $snapshot;
        $skrb->save();

        return response()->json([
            'message' => 'Metadata gambar utama siap.',
            'data' => $this->formatSkrb($skrb)
        ], 200);
    }

    /**
     * Inspeksi informasi ukuran dan daftar file di dalam storage ID SKRB (Khusus Admin)
     */
    public function storageInfo(Skrb $skrb)
    {
        $disk = Storage::disk('skrb');
        $storageKey = $skrb->getStorageKey();
        $folder = 'skrb-' . $storageKey;
        if (!$disk->exists($folder) && $disk->exists($storageKey)) {
            $folder = $storageKey;
        }

        $totalBytes = 0;
        $rootFiles = [];
        $gambarUtamaFiles = [];
        $savedFiles = [];

        if ($disk->exists($folder)) {
            // Root files (Surat Permohonan & Optional 5-9)
            $files = $disk->files($folder);
            foreach ($files as $filePath) {
                $size = $disk->size($filePath);
                $totalBytes += $size;
                $rootFiles[] = [
                    'name' => basename($filePath),
                    'path' => $filePath,
                    'size_bytes' => $size,
                    'size_kb' => round($size / 1024, 1),
                ];
            }

            // Folder gambar_utama
            $guFolder = $folder . '/gambar_utama';
            if ($disk->exists($guFolder)) {
                $files = $disk->files($guFolder);
                foreach ($files as $filePath) {
                    $size = $disk->size($filePath);
                    $totalBytes += $size;
                    $gambarUtamaFiles[] = [
                        'name' => basename($filePath),
                        'path' => $filePath,
                        'size_bytes' => $size,
                        'size_kb' => round($size / 1024, 1),
                    ];
                }
            }

            // Folder saved (History SKRB)
            $savedFolder = $folder . '/saved';
            if ($disk->exists($savedFolder)) {
                $files = $disk->files($savedFolder);
                foreach ($files as $filePath) {
                    $size = $disk->size($filePath);
                    $totalBytes += $size;
                    $savedFiles[] = [
                        'name' => basename($filePath),
                        'path' => $filePath,
                        'size_bytes' => $size,
                        'size_kb' => round($size / 1024, 1),
                    ];
                }
            }
        }

        $totalMb = round($totalBytes / (1024 * 1024), 2);
        $totalKb = round($totalBytes / 1024, 1);

        return response()->json([
            'folder' => $folder,
            'total_bytes' => $totalBytes,
            'total_kb' => $totalKb,
            'total_mb' => $totalMb,
            'root_files' => $rootFiles,
            'gambar_utama_files' => $gambarUtamaFiles,
            'saved_files' => $savedFiles,
        ]);
    }

    /**
     * Perbarui status fase (misal masuk Mode Edit / Fase 3 atau Batal Edit kembali ke Fase 2)
     * Atau perbarui toggle hidden_flags (ikon mata 👁️)
     */
    public function update(Request $request, Skrb $skrb)
    {
        $hasIdDwg = !empty($skrb->transaksi_id);
        $isRestricted = $hasIdDwg || ((int) $skrb->fase === 2);

        if ($isRestricted) {
            if ($request->has('customer_id') && (int) $request->input('customer_id') !== (int) $skrb->customer_id) {
                $msg = $hasIdDwg
                    ? 'Customer dan Master Data tidak dapat diubah karena SKRB terikat dengan ID DWG/Transaksi.'
                    : 'Customer dan Master Data tidak dapat diubah pada SKRB Fase 2.';
                return response()->json(['message' => $msg], 422);
            }
            if ($request->has('master_data_id') && (int) $request->input('master_data_id') !== (int) $skrb->master_data_id) {
                $msg = $hasIdDwg
                    ? 'Customer dan Master Data tidak dapat diubah karena SKRB terikat dengan ID DWG/Transaksi.'
                    : 'Customer dan Master Data tidak dapat diubah pada SKRB Fase 2.';
                return response()->json(['message' => $msg], 422);
            }
        }

        $coreDataChanged = false;

        $newCustomerId = $request->has('customer_id') ? (int) $request->input('customer_id') : (int) $skrb->customer_id;
        $customerIdChanged = ($newCustomerId != (int) $skrb->customer_id);
        
        $nomorUrutParam = $request->has('nomor_urut_manual') ? $request->input('nomor_urut_manual') : ($request->has('nomor_urut') ? $request->input('nomor_urut') : null);
        $hasNomorUrut = ($nomorUrutParam !== null && trim((string) $nomorUrutParam) !== '');
        $newNomorUrut = $hasNomorUrut ? (int) $nomorUrutParam : null;

        if ($customerIdChanged || ($newNomorUrut !== null && $newNomorUrut != (int) $skrb->nomor_urut)) {
            try {
                $targetNomor = $newNomorUrut ?? ($customerIdChanged ? null : $skrb->nomor_urut);
                $generated = $this->generateIdSkrb($newCustomerId, $targetNomor, $skrb->id);
                $skrb->customer_id = $newCustomerId;
                $skrb->id_skrb = $generated['idSkrb'];
                $skrb->nomor_urut = $generated['nomorUrut'];
                $skrb->bulan_tahun = $generated['bulanTahun'];
                $coreDataChanged = true;
            } catch (\Exception $e) {
                return response()->json([
                    'message' => $e->getMessage()
                ], 422);
            }
        }
        if ($request->has('master_data_id') && (int) $request->input('master_data_id') != (int) $skrb->master_data_id) {
            $skrb->master_data_id = (int) $request->input('master_data_id');
            $coreDataChanged = true;
        }
        if ($request->has('jenis_pengajuan_id') && (int) $request->input('jenis_pengajuan_id') != (int) $skrb->jenis_pengajuan_id) {
            if ((int) $request->input('jenis_pengajuan_id') === 4) {
                return response()->json(['message' => 'Jenis Pengajuan GAMBAR TU tidak diperbolehkan untuk SKRB.'], 422);
            }
            $skrb->jenis_pengajuan_id = (int) $request->input('jenis_pengajuan_id');
            $coreDataChanged = true;
        }

        if ($coreDataChanged) {
            $skrb->saveQuietly();
            $skrb->unsetRelations();
            $this->syncSnapshotIfOpen($skrb, true);
            $snapshot = $skrb->snapshot_documents ?? [];
            $suratPath = $this->generateSuratPermohonanPdf($skrb->getStorageKey(), $skrb->id_skrb, $snapshot, $snapshot['surat_permohonan_path'] ?? null);
            if ($suratPath) {
                $snapshot['surat_permohonan_path'] = $suratPath;
                $skrb->snapshot_documents = $snapshot;
            }
        }

        if ($request->has('fase')) {
            $newFase = (int) $request->input('fase');
            $skrb->fase = $newFase;
            if ($newFase == 3) {
                $snapshot = $skrb->snapshot_documents ?? [];
                unset($snapshot['modified_keys']);
                $skrb->snapshot_documents = $snapshot;
                $skrb->saveQuietly();
                $this->syncSnapshotIfOpen($skrb, true, true, true);
            }
        }

        if ($request->has('hidden_flags')) {
            $skrb->hidden_flags = $request->input('hidden_flags');
        }

        $needRegenPdf = false;
        $snapshot = $skrb->snapshot_documents ?? [];

        if ($request->has('gambar_utama_list')) {
            $snapshot['gambar_utama_list'] = $request->input('gambar_utama_list');
            $needRegenPdf = true;
        }

        if ($request->has('foto_copy_skrb')) {
            $val = $request->input('foto_copy_skrb');
            $skrb->foto_copy_skrb = $val;
            $snapshot['foto_copy_skrb'] = $val;
            $needRegenPdf = true;
        }

        if ($request->has('tanggal_permohonan')) {
            $val = $request->input('tanggal_permohonan');
            $skrb->tanggal_permohonan = $val ?: null;
            $snapshot['tanggal_permohonan'] = $val ?: null;
            $needRegenPdf = true;
        }

        if ($request->has('is_permohonan_manual')) {
            $snapshot['is_permohonan_manual'] = filter_var($request->input('is_permohonan_manual'), FILTER_VALIDATE_BOOLEAN);
            $needRegenPdf = true;
        }
        if ($request->has('manual_merk_tipe')) {
            $snapshot['manual_merk_tipe'] = $request->input('manual_merk_tipe');
            $needRegenPdf = true;
        }
        if ($request->has('manual_jenis')) {
            $snapshot['manual_jenis'] = $request->input('manual_jenis');
            $needRegenPdf = true;
        }
        if ($request->has('manual_peruntukan')) {
            $snapshot['manual_peruntukan'] = $request->input('manual_peruntukan');
            $needRegenPdf = true;
        }
        if ($request->has('manual_gambar_list')) {
            $snapshot['manual_gambar_list'] = $request->input('manual_gambar_list');
            $needRegenPdf = true;
        }

        if ($needRegenPdf) {
            $suratPath = $this->generateSuratPermohonanPdf($skrb->getStorageKey(), $skrb->id_skrb, $snapshot, $snapshot['surat_permohonan_path'] ?? null);
            if ($suratPath) {
                $snapshot['surat_permohonan_path'] = $suratPath;
            }
            $skrb->snapshot_documents = $snapshot;
        }

        $skrb->save();

        return response()->json([
            'message' => 'Permohonan SKRB diperbarui',
            'data' => $this->formatSkrb($skrb),
        ]);
    }

    /**
     * Upload / Ganti file opsional nomor 5, 6, 7, 8, 9 (atau gambar a, b, c, d)
     */
    public function uploadFile(Request $request, Skrb $skrb, string $key)
    {
        $maxSize = in_array($key, ['5', '6', '7', '8', '9']) ? 500 : 5120; // max 500KB untuk opsi 5-9, 5MB untuk gambar utama
        $request->validate([
            'file' => "required|file|mimes:pdf|max:{$maxSize}",
        ]);

        $file = $request->file('file');

        // Mapping nama file sesuai request user: idtransaksi-detailskrb-YmdHis.pdf
        // cth: 0726-0048-Surat_Pernyataan-20260729-205737.pdf
        $namesMap = [
            '5' => 'Surat_Pernyataan',
            '6' => 'Surat_Perhitungan',
            '7' => 'Brosur_Alat',
            '8' => 'Surat_Rekomendasi',
            '9' => 'Lampiran_SKRB',
            'a' => 'Gambar_1_Custom',
            'b' => 'Gambar_2_Custom',
            'c' => 'Gambar_3_Custom',
            'd' => 'Gambar_4_Custom',
        ];

        $detailName = $namesMap[$key] ?? ("Detail_" . $key);
        $filename = sprintf("%s-%s-%s.pdf", $skrb->getStorageKey(), $detailName, now()->format('Ymd-His'));
        $saveFolder = 'skrb-' . $skrb->getStorageKey();
        
        $path = $file->storeAs($saveFolder, $filename, 'skrb');

        // Hapus file lama di key yang sama jika ada
        $customFiles = $skrb->custom_files ?? [];
        if (!empty($customFiles[$key]) && Storage::disk('skrb')->exists($customFiles[$key])) {
            Storage::disk('skrb')->delete($customFiles[$key]);
        }

        $customFiles[$key] = $path;
        $skrb->custom_files = $customFiles;

        $snapshot = $skrb->snapshot_documents ?? [];
        $modifiedKeys = $snapshot['modified_keys'] ?? [];
        $modifiedKeys[$key] = true;
        $snapshot['modified_keys'] = $modifiedKeys;
        $skrb->snapshot_documents = $snapshot;

        $skrb->save();

        return response()->json([
            'message' => 'File berhasil diunggah',
            'path' => $path,
            'custom_files' => $skrb->custom_files,
        ]);
    }

    /**
     * Helper endpoint untuk Preview PDF setiap item (1, a, b, c, d, 2, 3, 4, 5, 6, 7, 8, 9)
     */
    public function viewFile(Skrb $skrb, string $key, Request $request)
    {
        $this->syncSnapshotIfOpen($skrb);
        $snapshot = $skrb->snapshot_documents ?? [];
        $customFiles = $skrb->custom_files ?? [];

        // 1. Surat Permohonan
        if ($key === '1') {
            $path = $snapshot['surat_permohonan_path'] ?? ('skrb-' . $skrb->getStorageKey() . '/surat_permohonan.pdf');
            if ($skrb->fase != 2 || !Storage::disk('skrb')->exists($path)) {
                $newPath = $this->generateSuratPermohonanPdf($skrb->getStorageKey(), $skrb->id_skrb, $snapshot, $path);
                if ($newPath) {
                    $path = $newPath;
                    $snapshot['surat_permohonan_path'] = $path;
                    $skrb->snapshot_documents = $snapshot;
                    $skrb->saveQuietly();
                }
            }
            if ($path && Storage::disk('skrb')->exists($path)) {
                $content = Storage::disk('skrb')->get($path);
                if (ob_get_length()) ob_clean();
                return response($content, 200)->header('Content-Type', 'application/pdf');
            }
        }

        // a, b, c, d (Gambar Utama) - jika ada custom file pakai custom, jika tidak pakai snapshot
        if (in_array($key, ['a', 'b', 'c', 'd'])) {
            if (!empty($customFiles[$key]) && Storage::disk('skrb')->exists($customFiles[$key])) {
                $content = Storage::disk('skrb')->get($customFiles[$key]);
                if (ob_get_length()) ob_clean();
                return response($content, 200)->header('Content-Type', 'application/pdf');
            }
            
            $list = $snapshot['gambar_utama_list'] ?? [];
            foreach ($list as $item) {
                if (($item['key'] ?? '') === $key && !empty($item['path'])) {
                    if (Storage::disk('skrb')->exists($item['path'])) {
                        $content = Storage::disk('skrb')->get($item['path']);
                        if (ob_get_length()) ob_clean();
                        return response($content, 200)->header('Content-Type', 'application/pdf');
                    }
                }
            }
        }

        // 2. Data Umum Perusahaan (dari customer-documents)
        if ($key === '2') {
            $path = $snapshot['data_umum_file'] ?? ($snapshot['data_umum'] ?? null);
            if ($path && Storage::disk('customer-documents')->exists($path)) {
                $content = Storage::disk('customer-documents')->get($path);
                if (ob_get_length()) ob_clean();
                return response($content, 200)->header('Content-Type', 'application/pdf');
            }
        }

        // 3. TDP (dari customer-documents) - bisa lebih dari 1, param ?index=0
        if ($key === '3') {
            $idx = (int) $request->input('index', 0);
            $tdpFiles = $snapshot['tdp_files'] ?? ($snapshot['tdp_list'] ?? []);
            $item = $tdpFiles[$idx] ?? null;
            $path = is_array($item) ? ($item['path'] ?? null) : $item;
            if ($path && is_string($path) && Storage::disk('customer-documents')->exists($path)) {
                $content = Storage::disk('customer-documents')->get($path);
                if (ob_get_length()) ob_clean();
                return response($content, 200)->header('Content-Type', 'application/pdf');
            }
        }

        // 4. SUT (dari sut-pdf)
        if ($key === '4') {
            $path = $snapshot['sut_file'] ?? ($snapshot['sut_pdf_path'] ?? null);
            if ($path && Storage::disk('sut-pdf')->exists($path)) {
                $content = Storage::disk('sut-pdf')->get($path);
                if (ob_get_length()) ob_clean();
                return response($content, 200)->header('Content-Type', 'application/pdf');
            }
        }

        // 5, 6, 7, 8, 9 (Custom Files upload di disk skrb)
        if (in_array($key, ['5', '6', '7', '8', '9'])) {
            $path = $customFiles[$key] ?? null;
            if ($path && Storage::disk('skrb')->exists($path)) {
                $content = Storage::disk('skrb')->get($path);
                if (ob_get_length()) ob_clean();
                return response($content, 200)->header('Content-Type', 'application/pdf');
            }
        }

        return response()->json(['message' => 'File dokumen untuk item ini belum diunggah atau tidak ditemukan.'], 404);
    }

    /**
     * Merge seluruh file PDF berurutan (1, a, b, c, d, 2, 3, 4, 5, 6, 7, 8, 9)
     * Untuk tombol 'Simpan' dan 'Simpan dan Unduh'
     */
    public function merge(Request $request, Skrb $skrb)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        // Sebelum disatukan dan disimpan, wajib ambil snapshot live dari DocumentCustomer & SUT terbaru
        $this->syncSnapshotIfOpen($skrb, true, true);

        // TODO: Hapus cek dokumen TDP expired (perlu diskusi ulang)
        // $doc = DocumentCustomer::where('customer_id', $skrb->customer_id)->first();
        // if ($doc && strcasecmp($doc->status_tdp ?? '', 'Expired') === 0) {
        //     return response()->json([
        //         'message' => 'Dokumen TDP sudah Expired. Hubungi Admin'
        //     ], 422);
        // }

        $isDownload = filter_var($request->input('download', false), FILTER_VALIDATE_BOOLEAN);
        $historyCount = $skrb->histories()->count();

        // Cek batas maksimal 3 riwayat per 1 ID SKRB (Berlaku untuk semua user & admin)
        if ($historyCount >= 3 && !$isDownload) {
            return response()->json([
                'message' => 'Riwayat dokumen SKRB sudah mencapai batas maksimal (3/3). Harap hapus minimal 1 riwayat terlebih dahulu melalui menu History SKRB.'
            ], 422);
        }

        $snapshot = $skrb->snapshot_documents ?? [];
        $customFiles = $skrb->custom_files ?? [];
        $hiddenFlags = $skrb->hidden_flags ?? [];

        $pdfFilesToMerge = [];

        // Helper cek hidden
        $isHidden = fn($k) => !empty($hiddenFlags[$k]);

        // 1. Surat Permohonan - selalu re-generate dengan snapshot terbaru
        if (!$isHidden('1')) {
            $oldPath = $snapshot['surat_permohonan_path'] ?? ('skrb-' . $skrb->getStorageKey() . '/surat_permohonan.pdf');
            $newPath = $this->generateSuratPermohonanPdf($skrb->getStorageKey(), $skrb->id_skrb, $snapshot, $oldPath);
            if ($newPath) {
                $snapshot['surat_permohonan_path'] = $newPath;
                $skrb->snapshot_documents = $snapshot;
                $skrb->saveQuietly();
                if (Storage::disk('skrb')->exists($newPath)) {
                    $pdfFilesToMerge[] = Storage::disk('skrb')->path($newPath);
                }
            } elseif (Storage::disk('skrb')->exists($oldPath)) {
                $pdfFilesToMerge[] = Storage::disk('skrb')->path($oldPath);
            }
        }

        // a, b, c, d (Gambar Utama)
        // [KOMENTAR FITUR LAMA]: Dulu dilekatkan di proses merger SKRB. Kini tidak dilampirkan lagi karena hanya teks varian/judul yang dicantumkan pada Surat Permohonan (No. 1).
        /*
        $gambarList = $snapshot['gambar_utama_list'] ?? [];
        foreach (['a', 'b', 'c', 'd'] as $gKey) {
            if ($isHidden($gKey)) continue;

            if (!empty($customFiles[$gKey]) && Storage::disk('skrb')->exists($customFiles[$gKey])) {
                $pdfFilesToMerge[] = Storage::disk('skrb')->path($customFiles[$gKey]);
            } else {
                foreach ($gambarList as $item) {
                    if (($item['key'] ?? '') === $gKey && !empty($item['path']) && Storage::disk('skrb')->exists($item['path'])) {
                        $pdfFilesToMerge[] = Storage::disk('skrb')->path($item['path']);
                    }
                }
            }
        }
        */

        // 2. Data Umum Perusahaan
        $dataUmumPath = $snapshot['data_umum_file'] ?? ($snapshot['data_umum'] ?? null);
        if (!$isHidden('2') && !empty($dataUmumPath) && Storage::disk('customer-documents')->exists($dataUmumPath)) {
            $pdfFilesToMerge[] = Storage::disk('customer-documents')->path($dataUmumPath);
        }

        // 3. TDP (Bisa multi-files)
        $tdpFilesToMerge = $snapshot['tdp_files'] ?? ($snapshot['tdp_list'] ?? []);
        if (!$isHidden('3') && !empty($tdpFilesToMerge) && is_array($tdpFilesToMerge)) {
            foreach ($tdpFilesToMerge as $item) {
                $tdpPath = is_array($item) ? ($item['path'] ?? null) : $item;
                if ($tdpPath && is_string($tdpPath) && Storage::disk('customer-documents')->exists($tdpPath)) {
                    $pdfFilesToMerge[] = Storage::disk('customer-documents')->path($tdpPath);
                }
            }
        }

        // 4. SUT
        $sutPath = $snapshot['sut_file'] ?? ($snapshot['sut_pdf_path'] ?? null);
        if (!$isHidden('4') && !empty($sutPath) && Storage::disk('sut-pdf')->exists($sutPath)) {
            $pdfFilesToMerge[] = Storage::disk('sut-pdf')->path($sutPath);
        }

        // 5, 6, 7, 8, 9 (Optional uploaded files)
        foreach (['5', '6', '7', '8', '9'] as $optKey) {
            if (!$isHidden($optKey) && !empty($customFiles[$optKey]) && Storage::disk('skrb')->exists($customFiles[$optKey])) {
                $pdfFilesToMerge[] = Storage::disk('skrb')->path($customFiles[$optKey]);
            }
        }

        if (empty($pdfFilesToMerge)) {
            return response()->json(['message' => 'Tidak ada dokumen PDF yang tersedia atau aktif untuk diringkas/merge.'], 400);
        }

        try {
            $merger = new MasterPdf();
            foreach ($pdfFilesToMerge as $filePath) {
                if (!file_exists($filePath)) continue;
                try {
                    $pageCount = $merger->setSourceFile($filePath);
                    for ($i = 1; $i <= $pageCount; $i++) {
                        $tplId = $merger->importPage($i);
                        $size = $merger->getTemplateSize($tplId);
                        $merger->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $merger->useTemplate($tplId);
                    }
                } catch (\Exception $ex) {
                    Log::warning("Melewatkan file saat merge dikerenakan format atau perlindungan: $filePath | " . $ex->getMessage());
                    continue;
                }
            }

            // Gunakan penamaan murni hasil optimasi Backend
            $fileNames = $this->getCleanMergedFileName($skrb, $snapshot);
            $cleanDownloadName = $fileNames['download_name'];
            $cleanStorageFileName = $fileNames['storage_name'];

            // Wajib 2 parameter ('name.pdf', 'S') pada TCPDF/FPDI agar output biner dikembalikan sebagai string murni dan tidak tercecer ke stdout echo (yang menyudahi $pdfOutput kosong / 0 KB di storage)
            $pdfOutput = $merger->Output($cleanStorageFileName, 'S');

            // Jika riwayat sudah maksimal (3/3), dan pengguna request download ("Download Saja"), langsung kembalikan stream unduhan TANPA menyimpan ke server dan TANPA ganti fase
            if ($historyCount >= 3 && $isDownload) {
                if (ob_get_length()) ob_clean();
                return response($pdfOutput, 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'attachment; filename="' . $cleanDownloadName . '"')
                    ->header('X-Suggested-Filename', $cleanDownloadName);
            }

            $relativeSavePath = 'skrb-' . $skrb->getStorageKey() . '/saved/' . $cleanStorageFileName;
            Storage::disk('skrb')->put($relativeSavePath, $pdfOutput);

            // Catat di tabel history
            $history = SkrbHistory::create([
                'skrb_id' => $skrb->id,
                'file_name' => $cleanDownloadName,
                'storage_path' => $relativeSavePath,
                'file_size' => strlen($pdfOutput),
            ]);

            // Setelah disimpan, beralih ke Fase 2 (terkunci/tersimpan) dan bersihkan flag update dari admin & modifikasi
            $skrb->fase = 2;
            $skrb->is_tdp_updated_by_admin = false;
            $snapshot = $skrb->snapshot_documents ?? [];
            unset($snapshot['modified_keys']);
            $skrb->snapshot_documents = $snapshot;
            $skrb->save();

            if ($isDownload) {
                if (ob_get_length()) ob_clean();
                return response($pdfOutput, 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'attachment; filename="' . $cleanDownloadName . '"')
                    ->header('X-Suggested-Filename', $cleanDownloadName);
            }

            return response()->json([
                'message' => 'File SKRB berhasil disatukan dan disimpan!',
                'history' => $history,
                'fase' => 2,
                'download_url' => url('/api/skrb-histories/' . $history->id . '/download'),
            ]);

        } catch (\Exception $e) {
            Log::error("Error Merge PDF SKRB: " . $e->getMessage());
            return response()->json(['message' => 'Gagal menyatukan PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Tombol 'Hapus' pada Fase 2 / 3: Hapus semua gambar & file yang tersimpan di storage beserta catatan di DB,
     * TANPA menghapus ID SKRB dan ID TRANSAKSI.
     */
    public function resetFiles(Skrb $skrb)
    {
        // Hapus file-file custom (nomor 5-9 dan a-d custom) dari disk skrb
        $customFiles = $skrb->custom_files ?? [];
        foreach ($customFiles as $path) {
            if ($path && Storage::disk('skrb')->exists($path)) {
                Storage::disk('skrb')->delete($path);
            }
        }

        // Hapus folder saved/ untuk SKRB ini
        $storageKey = $skrb->getStorageKey();
        $savedFolder = 'skrb-' . $storageKey . '/saved';
        if (Storage::disk('skrb')->exists($savedFolder)) {
            Storage::disk('skrb')->deleteDirectory($savedFolder);
        }
        if (Storage::disk('skrb')->exists($storageKey . '/saved')) {
            Storage::disk('skrb')->deleteDirectory($storageKey . '/saved');
        }

        // Hapus rekor di tabel skrb_histories
        $skrb->histories()->delete();

        // Reset state SKRB kembali bersih ke Fase 1
        $skrb->custom_files = [];
        $skrb->hidden_flags = [];
        $skrb->fase = 1;
        $snapshot = $skrb->snapshot_documents ?? [];
        unset($snapshot['modified_keys']);
        $skrb->snapshot_documents = $snapshot;
        $skrb->save();

        return response()->json([
            'message' => 'Seluruh file dan riwayat terserap SKRB telah di-reset, rekor kembali ke Fase 1.',
            'data' => $this->formatSkrb($skrb),
        ]);
    }

    /**
     * Menghapus total Permohonan SKRB dari tabel (dari dialog opsi HAPUS)
     */
    public function destroy(Skrb $skrb)
    {
        // Hapus seluruh direktori id_transaksi di disk skrb
        $storageKey = $skrb->getStorageKey();
        $folder = 'skrb-' . $storageKey;
        if (Storage::disk('skrb')->exists($folder)) {
            Storage::disk('skrb')->deleteDirectory($folder);
        }
        if (Storage::disk('skrb')->exists($storageKey)) {
            Storage::disk('skrb')->deleteDirectory($storageKey);
        }

        $skrb->delete();

        return response()->json(['message' => 'Permohonan SKRB beserta seluruh dokumen terkait berhasil dihapus total.']);
    }

    /**
     * Download file riwayat (dari dialog History SKRB)
     */
    public function downloadHistory(SkrbHistory $history)
    {
        if (!Storage::disk('skrb')->exists($history->storage_path)) {
            return response()->json(['message' => 'File fisik riwayat tidak ditemukan di server.'], 404);
        }

        $content = Storage::disk('skrb')->get($history->storage_path);
        if (ob_get_length()) ob_clean();
        return response($content, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $history->file_name . '"')
            ->header('X-Suggested-Filename', $history->file_name);
    }

    /**
     * Preview file riwayat (dari dialog History SKRB)
     */
    public function viewHistory(SkrbHistory $history)
    {
        if (!Storage::disk('skrb')->exists($history->storage_path)) {
            return response()->json(['message' => 'File fisik riwayat tidak ditemukan di server.'], 404);
        }

        $content = Storage::disk('skrb')->get($history->storage_path);
        if (ob_get_length()) ob_clean();
        return response($content, 200)->header('Content-Type', 'application/pdf');
    }

    /**
     * Hapus 1 baris riwayat file (dari dialog History SKRB)
     */
    public function deleteHistory(SkrbHistory $history)
    {
        if (Storage::disk('skrb')->exists($history->storage_path)) {
            Storage::disk('skrb')->delete($history->storage_path);
        }
        $history->delete();

        return response()->json(['message' => 'File riwayat berhasil dihapus.']);
    }

    /**
     * Hapus SEMUA file riwayat pada dialog History SKRB
     */
    public function deleteAllHistories(Skrb $skrb)
    {
        $storageKey = $skrb->getStorageKey();
        $savedFolder = 'skrb-' . $storageKey . '/saved';
        if (Storage::disk('skrb')->exists($savedFolder)) {
            Storage::disk('skrb')->deleteDirectory($savedFolder);
        }
        if (Storage::disk('skrb')->exists($storageKey . '/saved')) {
            Storage::disk('skrb')->deleteDirectory($storageKey . '/saved');
        }
        $skrb->histories()->delete();

        return response()->json(['message' => 'Seluruh riwayat file SKRB telah dibersihkan.']);
    }

    /**
     * Helper generate Surat Permohonan ke file fisik #1
     */
    private function generateSuratPermohonanPdf(string $transaksiId, string $idSkrb, array $snapshot, ?string $oldPath = null): ?string
    {
        try {
            $isManual = !empty($snapshot['is_permohonan_manual']) && ($snapshot['is_permohonan_manual'] === true || $snapshot['is_permohonan_manual'] === 'true' || $snapshot['is_permohonan_manual'] === 1 || $snapshot['is_permohonan_manual'] === '1');

            $gambarList = ($isManual && !empty($snapshot['manual_gambar_list'])) 
                ? $snapshot['manual_gambar_list'] 
                : ($snapshot['gambar_utama_list'] ?? []);
            $varianList = [];
            foreach ($gambarList as $idx => $item) {
                $varianList[] = [
                    'prefix' => ($idx === 0) ? 'd. ' : '   ',
                    'label'  => ucwords(strtolower($item['judul'] ?? ('Varian ' . ($idx + 1)))),
                    'value'  => $item['varian'] ?? null,
                ];
            }
            if (empty($varianList)) {
                $varianList[] = [
                    'prefix' => 'd. ',
                    'label'  => 'Varian Body',
                    'value'  => null,
                ];
            }

            // Mapping Jenis Pengajuan (BARU = Penelitian, VARIAN = Varian, REVISI = Revisi)
            $rawPengajuan = strtoupper(trim($snapshot['jenis_pengajuan'] ?? ''));
            if (str_contains($rawPengajuan, 'BARU')) {
                $pengajuanMapped = 'Penelitian';
            } elseif (str_contains($rawPengajuan, 'REVISI')) {
                $pengajuanMapped = 'Revisi';
            } else {
                $pengajuanMapped = 'Varian';
            }

            // Gabungan Merk TIPE Type Chassis
            $merk = trim((string)($snapshot['merk'] ?? ''));
            $chassis = trim((string)($snapshot['type_chassis'] ?? ''));
            $merekTipe = (!empty($merk) && $merk !== '-' && !empty($chassis) && $chassis !== '-') 
                ? "{$merk} TIPE {$chassis}" : null;

            $data = [
                'nomor_surat' => $idSkrb,
                'lampiran' => '-',
                'nama_pemohon' => !empty($snapshot['customer_pj']) && $snapshot['customer_pj'] !== '-' ? $snapshot['customer_pj'] : ($snapshot['customer_name'] ?? null),
                'jabatan' => $snapshot['customer_jabatan'] ?? null,
                'alamat' => $snapshot['alamat_lengkap'] ?? ($snapshot['customer_alamat'] ?? null),
                'alamat_permohonan' => $snapshot['alamat_permohonan'] ?? null,
                'bidang_usaha' => $snapshot['bidang_usaha'] ?? null,
                'merek_tipe' => ($isManual && isset($snapshot['manual_merk_tipe']) && $snapshot['manual_merk_tipe'] !== '') ? $snapshot['manual_merk_tipe'] : $merekTipe,
                'jenis' => ($isManual && isset($snapshot['manual_jenis']) && $snapshot['manual_jenis'] !== '') ? $snapshot['manual_jenis'] : ($snapshot['jenis_tipe'] ?? null),
                'peruntukan' => ($isManual && isset($snapshot['manual_peruntukan']) && $snapshot['manual_peruntukan'] !== '') ? $snapshot['manual_peruntukan'] : ($snapshot['alias_kendaraan'] ?? null),
                'jenis_pengajuan' => $pengajuanMapped,
                'varian_list' => $varianList,
                'kop_path' => !empty($snapshot['kop_surat_file']) && Storage::disk('customer-documents')->exists($snapshot['kop_surat_file'])
                    ? Storage::disk('customer-documents')->path($snapshot['kop_surat_file'])
                    : (!empty($snapshot['kop_surat']) && Storage::disk('customer-documents')->exists($snapshot['kop_surat']) 
                        ? Storage::disk('customer-documents')->path($snapshot['kop_surat']) : null),
                'foto_copy_skrb'=> $snapshot['foto_copy_skrb'] ?? null,
                'tanggal_permohonan' => $snapshot['tanggal_permohonan'] ?? null,
            ];

            $template = new SKRB_template();
            $pdf = $template->generate($data);
            $content = $pdf->Output('surat.pdf', 'S');

            // Hapus file lama jika ada agar storage bersih dan selalu ter-replace dengan versi terbaru
            if (!empty($oldPath) && Storage::disk('skrb')->exists($oldPath)) {
                Storage::disk('skrb')->delete($oldPath);
            }
            $staticOld = 'skrb-' . $transaksiId . '/surat_permohonan.pdf';
            if (Storage::disk('skrb')->exists($staticOld)) {
                Storage::disk('skrb')->delete($staticOld);
            }

            $filename = sprintf("%s-Surat_Permohonan-%s.pdf", $transaksiId, now()->format('Ymd-His'));
            $savePath = 'skrb-' . $transaksiId . '/' . $filename;
            Storage::disk('skrb')->put($savePath, $content);

            return $savePath;
        } catch (\Exception $e) {
            Log::error("Gagal generate surat permohonan SKRB: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Konversi bulan angka ke Romawi
     */
    private function toRoman(int $number): string
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V',
            6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X',
            11 => 'XI', 12 => 'XII'
        ];
        return $map[$number] ?? 'I';
    }
}
