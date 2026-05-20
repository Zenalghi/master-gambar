<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EVarianBody;
use App\Models\GGambarUtama;
use App\Models\HGambarOptional;
use App\Models\IGambarKelistrikan;
use App\Models\JJudulGambar;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ProsesTransaksiController extends Controller
{
    public function saveDraft(Request $request, Transaksi $transaksi)
    {
        $validated = $request->validate([
            'pemeriksa_id' => 'nullable|exists:users,id',
            'pihak_penyetujuan' => 'nullable|string|in:vendor,customer', // <-- Validasi Baru 'jumlah_gambar'=> 'required|integer|min:1|max:4',
            'data_gambar_utama' => 'required|array',
            'deskripsi_optional' => 'nullable|string',
            'desc_space' => 'nullable|integer|min:0',
            'ordered_independent_ids' => 'nullable|array',
            'ordered_independent_ids.*' => 'integer',
            'i_gambar_kelistrikan_id' => 'nullable|integer|exists:i_gambar_kelistrikan,id',
        ]);

        // --- LOGIKA SNAPSHOT (FOTO ALAMAT FILE SAAT INI) ---
        $snapshot = [
            'varian' => [],
            'independen' => [],
            'kelistrikan' => null
        ];

        // 1. Snapshot Varian Body (Utama, Terurai, Kontruksi, Paket)
        if (!empty($request->data_gambar_utama)) {
            foreach ($request->data_gambar_utama as $item) {
                $vid = $item['varian_id'] ?? null;
                if ($vid) {
                    $g = GGambarUtama::with('gambarOptionals')->where('e_varian_body_id', $vid)->first();
                    if ($g) {
                        $paket = [];
                        foreach ($g->gambarOptionals as $opt) {
                            if ($opt->tipe === 'paket') {
                                $paket[$opt->id] = $opt->path_gambar_optional;
                            }
                        }
                        $snapshot['varian'][$vid] = [
                            'utama' => $g->path_gambar_utama,
                            'terurai' => $g->path_gambar_terurai,
                            'kontruksi' => $g->path_gambar_kontruksi,
                            'paket' => $paket
                        ];
                    }
                }
            }
        }

        // 2. Snapshot Independen
        if (!empty($request->ordered_independent_ids)) {
            $inds = HGambarOptional::whereIn('id', $request->ordered_independent_ids)->where('tipe', 'independen')->get();
            foreach ($inds as $ind) {
                $snapshot['independen'][$ind->id] = $ind->path_gambar_optional;
            }
        }

        // 3. Snapshot Kelistrikan
        if ($request->i_gambar_kelistrikan_id) {
            $kel = IGambarKelistrikan::with('fileKelistrikan')->find($request->i_gambar_kelistrikan_id);
            if ($kel && $kel->fileKelistrikan) {
                $snapshot['kelistrikan'] = $kel->fileKelistrikan->path_file;
            }
        }
        // ----------------------------------------------------

        $detail = TransaksiDetail::updateOrCreate(
            ['transaksi_id' => $transaksi->id],
            [
                'pemeriksa_id' => $request->pemeriksa_id,
                'pihak_penyetujuan' => $request->input('pihak_penyetujuan', 'vendor'), // <-- Simpan ke DB! 'jumlah_gambar'=> $request->jumlah_gambar,
                'data_gambar_utama' => $request->data_gambar_utama,
                'ordered_independent_ids' => $request->ordered_independent_ids ?? [],
                'deskripsi_optional' => $request->deskripsi_optional,
                'desc_space' => $request->input('desc_space', 0),
                'i_gambar_kelistrikan_id' => $request->input('i_gambar_kelistrikan_id'),
                'snapshot_data' => $snapshot, // <-- Simpan hasil foto ke DB!
            ]
        );

        return response()->json(['message' => 'Draft berhasil disimpan', 'detail' => $detail]);
    }


    public function proses(Request $request, Transaksi $transaksi)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $request->validate([
            'aksi' => 'required|in:preview,proses',
            'preview_page' => 'nullable|integer|min:1',
            'is_edit_mode' => 'nullable|boolean',
        ]);

        $transaksi->load([
            'detail',
            'user',
            'customer',
            'fPengajuan',
            'masterData.typeEngine',
            'masterData.merk',
            'masterData.typeChassis',
            'masterData.jenisKendaraan'
        ]);

        if (!$transaksi->detail) {
            return response()->json(['message' => 'Data detail belum tersimpan. Silakan klik Simpan Draft terlebih dahulu.'], 422);
        }

        $detail = $transaksi->detail;

        // --- LOGIKA BARU: BYPASS SNAPSHOT JIKA MODE EDIT ---
        $isEditMode = filter_var($request->input('is_edit_mode', false), FILTER_VALIDATE_BOOLEAN);

        // Jika sedang Edit Mode, KOSONGKAN snapshot agar sistem mengambil langsung dari tabel Master terbaru!
        $snapshot = $isEditMode ? [] : ($detail->snapshot_data ?? []);

        $pihakPenyetujuan = $request->input('pihak_penyetujuan') ?? ($detail->pihak_penyetujuan ?? 'vendor');

        $pemeriksaIdRaw = $request->input('pemeriksa_id') ?? $detail->pemeriksa_id;
        $pemeriksa = $pemeriksaIdRaw ? User::find($pemeriksaIdRaw) : null;

        $dataGambarUtama = $detail->data_gambar_utama ?? [];
        $orderedIndependentIds = $detail->ordered_independent_ids ?? [];
        $deskripsiOptional = $detail->deskripsi_optional;
        $descSpace = $detail->desc_space ?? 0;
        $iGambarKelistrikanId = $detail->i_gambar_kelistrikan_id;
        $hGambarOptionalIds = $request->input('h_gambar_optional_ids', []);

        $masterData = $transaksi->masterData;
        $jenisPengajuan = strtoupper($transaksi->fPengajuan->jenis_pengajuan);
        $isGambarTU = ($jenisPengajuan === 'GAMBAR TU');

        $jobsUtama = [];
        $jobsTerurai = [];
        $jobsKontruksi = [];
        $jobsPaket = [];
        $jobsIndependen = [];
        $jobsKelistrikan = [];

        // --- TAHAP 1: LOOPING DATA DB ---
        if (!empty($dataGambarUtama)) {
            foreach ($dataGambarUtama as $item) {
                $varian_id = $item['varian_id'] ?? null;
                $judul_id = $item['judul_id'] ?? null;

                if (!$varian_id || !$judul_id) continue;

                $varianBody = EVarianBody::find($varian_id);
                $gambarUtamaData = GGambarUtama::with('gambarOptionals')->where('e_varian_body_id', $varian_id)->first();
                $jenisJudul = JJudulGambar::find($judul_id);

                if ($gambarUtamaData && $jenisJudul) {

                    // !!! KUNCI UTAMA: Coba ambil path dari Snapshot, jika tidak ada (transaksi lama), ambil dari Master !!!
                    $pathUtama = $snapshot['varian'][$varian_id]['utama'] ?? $gambarUtamaData->path_gambar_utama;
                    $pathTerurai = $snapshot['varian'][$varian_id]['terurai'] ?? $gambarUtamaData->path_gambar_terurai;
                    $pathKontruksi = $snapshot['varian'][$varian_id]['kontruksi'] ?? $gambarUtamaData->path_gambar_kontruksi;

                    if ($pathUtama) {
                        $jobsUtama[] = [
                            'type' => 'standard',
                            'title' => 'GAMBAR TAMPAK UTAMA ' . $jenisJudul->nama_judul,
                            'varian' => $varianBody->varian_body,
                            'source_pdf' => $pathUtama, // Pakai file snapshot
                            'deskripsi_optional' => $deskripsiOptional,
                            'desc_space' => $descSpace
                        ];
                    }

                    if ($isGambarTU) continue;

                    if (!empty($pathTerurai)) {
                        $jobsTerurai[] = [
                            'type' => 'standard',
                            'title' => 'GAMBAR TAMPAK TERURAI ' . $jenisJudul->nama_judul,
                            'varian' => $varianBody->varian_body,
                            'source_pdf' => $pathTerurai,
                            'deskripsi_optional' => null
                        ];
                    }

                    if (!empty($pathKontruksi)) {
                        $jobsKontruksi[] = [
                            'type' => 'standard',
                            'title' => 'GAMBAR DETAIL KONTRUKSI ' . $jenisJudul->nama_judul,
                            'varian' => $varianBody->varian_body,
                            'source_pdf' => $pathKontruksi,
                            'deskripsi_optional' => null
                        ];
                    }

                    foreach ($gambarUtamaData->gambarOptionals as $gambarPaket) {
                        if ($gambarPaket->tipe === 'paket' && in_array($gambarPaket->id, $hGambarOptionalIds)) {
                            // Cek snapshot paket
                            $pathPaket = $snapshot['varian'][$varian_id]['paket'][$gambarPaket->id] ?? $gambarPaket->path_gambar_optional;

                            if ($pathPaket) {
                                $judulLengkap = ($gambarPaket->deskripsi ?: 'GAMBAR OPTIONAL PAKET') . ' ' . $jenisJudul->nama_judul;
                                $jobsPaket[] = [
                                    'type' => 'standard',
                                    'title' => $judulLengkap,
                                    'varian' => '',
                                    'source_pdf' => $pathPaket,
                                    'deskripsi_optional' => null
                                ];
                            }
                        }
                    }
                }
            }
        }

        if (!$isGambarTU) {
            if (!empty($orderedIndependentIds)) {
                $gambarIndependen = HGambarOptional::whereIn('id', $orderedIndependentIds)->where('tipe', 'independen')->get();
                $idMap = array_flip($orderedIndependentIds);
                $gambarIndependen = $gambarIndependen->sortBy(function ($model) use ($idMap) {
                    return $idMap[$model->id] ?? 999;
                });

                foreach ($gambarIndependen as $gambarOptional) {
                    // Ambil snapshot independen
                    $pathIndependen = $snapshot['independen'][$gambarOptional->id] ?? $gambarOptional->path_gambar_optional;

                    if ($pathIndependen) {
                        $jobsIndependen[] = [
                            'type' => 'standard',
                            'title' => $gambarOptional->deskripsi ?: 'GAMBAR OPTIONAL',
                            'varian' => '',
                            'source_pdf' => $pathIndependen,
                            'deskripsi_optional' => null
                        ];
                    }
                }
            }

            if ($iGambarKelistrikanId) {
                $gambarKelistrikan = IGambarKelistrikan::with('fileKelistrikan')->find($iGambarKelistrikanId);
                if ($gambarKelistrikan && $gambarKelistrikan->fileKelistrikan) {
                    // Ambil snapshot kelistrikan
                    $pathKelistrikan = $snapshot['kelistrikan'] ?? $gambarKelistrikan->fileKelistrikan->path_file;

                    if ($pathKelistrikan) {
                        $jobsKelistrikan[] = [
                            'type' => 'kelistrikan',
                            'title' => $gambarKelistrikan->deskripsi ?: 'GAMBAR KELISTRIKAN',
                            'jenis_kendaraan' => $masterData->jenisKendaraan->jenis_kendaraan ?? '',
                            'varian' => '',
                            'source_pdf' => $pathKelistrikan,
                            'deskripsi_optional' => null
                        ];
                    }
                }
            }
        }

        // Merge & Page Number
        $drawingJobs = array_merge($jobsUtama, $jobsTerurai, $jobsKontruksi, $jobsPaket, $jobsIndependen, $jobsKelistrikan);
        $pageCounter = 1;
        foreach ($drawingJobs as &$job) {
            $job['page'] = $pageCounter++;
        }
        unset($job);
        $totalHalaman = count($drawingJobs);

        // --- EKSEKUSI (SAMA SEPERTI SEBELUMNYA) ---
        if ($request->aksi === 'preview') {
            $previewPage = $request->preview_page ?? 1;
            $previewIndex = $previewPage - 1;

            if (isset($drawingJobs[$previewIndex])) {
                $job = $drawingJobs[$previewIndex];
                if (!Storage::disk('master_gambar')->exists($job['source_pdf'])) {
                    return response()->json(['message' => 'File PDF sumber tidak ditemukan.'], 404);
                }

                // --- KIRIM PIHAK PENYETUJUAN KE BUILDER ---
                $pdfData = $this->buildPdfData($job, $transaksi, $pemeriksa, $totalHalaman, $pihakPenyetujuan);

                $pdfContent = $this->generateUncopyablePdfPage($pdfData);
                if (ob_get_length()) ob_clean();
                return response($pdfContent, 200)->header('Content-Type', 'application/pdf');
            } else {
                return response()->json(['message' => 'Halaman preview tidak ditemukan.'], 404);
            }
        } else {
            try {
                if ($isGambarTU) {
                    // Logic Merge PDF (GAMBAR TU)
                    $pdfMerger = new Fpdi();
                    $pdfMerger->setPrintHeader(false);
                    $pdfMerger->setPrintFooter(false);
                    $tempFiles = [];

                    foreach ($drawingJobs as $job) {
                        if (!Storage::disk('master_gambar')->exists($job['source_pdf'])) continue;

                        // --- KIRIM PIHAK PENYETUJUAN KE BUILDER ---
                        $pdfData = $this->buildPdfData($job, $transaksi, $pemeriksa, $totalHalaman, $pihakPenyetujuan);

                        $pdfContent = $this->generateUncopyablePdfPage($pdfData);
                        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('merge_', true) . '.pdf';
                        file_put_contents($tempPath, $pdfContent);
                        $tempFiles[] = $tempPath;
                        $pageCount = $pdfMerger->setSourceFile($tempPath);
                        for ($i = 1; $i <= $pageCount; $i++) {
                            $tplId = $pdfMerger->importPage($i);
                            $pdfMerger->AddPage('L', 'A4');
                            $pdfMerger->useTemplate($tplId, ['adjustPageSize' => true]);
                        }
                    }

                    if (empty($tempFiles)) return response()->json(['message' => 'Gagal memproses gambar.'], 404);

                    $baseFileName = sprintf('%s (%s) %s_%s %s (%s).pdf', $transaksi->user->username, $transaksi->fPengajuan->jenis_pengajuan, $transaksi->customer->nama_pt, $masterData->merk->merk, $masterData->typeChassis->type_chassis, $masterData->jenisKendaraan->jenis_kendaraan);
                    $cleanFileName = Str::slug(pathinfo($baseFileName, PATHINFO_FILENAME)) . '.pdf';

                    foreach ($tempFiles as $file) @unlink($file);
                    if (ob_get_length()) ob_clean();
                    return response($pdfMerger->Output('S'), 200)->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="' . $cleanFileName . '"');
                } else {
                    // Logic ZIP (LAINNYA)
                    $generatedPdfs = [];
                    foreach ($drawingJobs as $job) {
                        if (!Storage::disk('master_gambar')->exists($job['source_pdf'])) continue;

                        // --- KIRIM PIHAK PENYETUJUAN KE BUILDER ---
                        $pdfData = $this->buildPdfData($job, $transaksi, $pemeriksa, $totalHalaman, $pihakPenyetujuan);

                        $pdfContent = $this->generateUncopyablePdfPage($pdfData);
                        $generatedPdfs[] = ['name' => $job['page'] . '.pdf', 'content' => $pdfContent];
                    }

                    if (empty($generatedPdfs)) return response()->json(['message' => 'Gagal memproses gambar.'], 404);

                    $zipFileName = sprintf('%s (%s) %s_%s %s (%s).zip', $transaksi->user->username, $transaksi->fPengajuan->jenis_pengajuan, $transaksi->customer->nama_pt, $masterData->merk->merk, $masterData->typeChassis->type_chassis, $masterData->jenisKendaraan->jenis_kendaraan);
                    $cleanZipFileName = Str::slug(pathinfo($zipFileName, PATHINFO_FILENAME)) . '.zip';

                    $zip = new \ZipArchive();
                    $tempZipPath = tempnam(sys_get_temp_dir(), 'gambar_');
                    $zip->open($tempZipPath, \ZipArchive::CREATE);
                    foreach ($generatedPdfs as $pdfFile) $zip->addFromString($pdfFile['name'], $pdfFile['content']);
                    $zip->close();

                    if (ob_get_length()) ob_clean();
                    return response()->download($tempZipPath, $cleanZipFileName)->deleteFileAfterSend(true);
                }
            } catch (\Exception $e) {
                Log::error("Error Proses PDF: " . $e->getMessage());
                return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
            }
        }
    }

    // --- Helper Data Builder (SEKARANG MENERIMA PIHAK PENYETUJUAN) ---
    private function buildPdfData(array $job, Transaksi $transaksi, ?User $pemeriksa, int $totalHalaman, string $pihakPenyetujuan): array
    {
        // Default (Vendor)
        $namaDigambar = $transaksi->user->name ?? '';
        $namaDiperiksa = $pemeriksa ? $pemeriksa->name : '';
        $parafDigambarPath = $transaksi->user->signature ? Storage::disk('user_paraf')->path($transaksi->user->signature) : null;
        $parafDiperiksaPath = ($pemeriksa && $pemeriksa->signature) ? Storage::disk('user_paraf')->path($pemeriksa->signature) : null;

        // --- OVERRIDE JIKA CUSTOMER ---
        if ($pihakPenyetujuan === 'customer') {
            $customer = $transaksi->customer;

            // Nama (Gunakan null coalescing fallback)
            $namaDigambar = $customer->nama_drafter ?? '';
            $namaDiperiksa = $customer->nama_pemeriksa ?? '';

            // Paraf
            $parafDigambarPath = $customer->signature_drafter ? Storage::disk('customer_paraf')->path($customer->signature_drafter) : null;
            $parafDiperiksaPath = $customer->signature_pemeriksa ? Storage::disk('customer_paraf')->path($customer->signature_pemeriksa) : null;
        }

        return [
            'type' => $job['type'] ?? 'standard',
            // Pastikan casting ke (string) agar TCPDF Write tidak error null
            'digambar' => (string) $namaDigambar,
            'diperiksa' => (string) $namaDiperiksa,
            'disetujui' => (string) ($transaksi->customer->pj ?? ''),
            'tanggal' => now()->format('d.m.y'),
            'judul_gambar' => $job['title'] ?? '',
            'catatan' => $job['varian'] ?? '',
            'jenis_kendaraan' => $job['jenis_kendaraan'] ?? '',
            'karoseri' => $transaksi->customer->nama_pt ?? '',
            'no_halaman' => str_pad($job['page'] ?? 1, 2, '0', STR_PAD_LEFT),
            'total_halaman' => str_pad($totalHalaman, 2, '0', STR_PAD_LEFT),
            'source_pdf_path' => $job['source_pdf'] ?? '',
            'signature_path'   => $parafDigambarPath,
            'signature_path_2' => $parafDiperiksaPath,
            'signature_path_3' => $transaksi->customer->signature_pj ? Storage::disk('customer_paraf')->path($transaksi->customer->signature_pj) : null,
            'deskripsi_optional' => $job['deskripsi_optional'] ?? '',
            'desc_space' => $job['desc_space'] ?? 0,
        ];
    }

    private function generateUncopyablePdfPage(array $data): string
    {
        // 1. Setup Temporary Files
        $tempDir = sys_get_temp_dir();
        $uniqueId = uniqid('pdf_secure_', true);
        $tempPdfVector = $tempDir . DIRECTORY_SEPARATOR . $uniqueId . '_vector.pdf';
        $tempPng = $tempDir . DIRECTORY_SEPARATOR . $uniqueId . '.png';

        // 2. Generate Vector PDF (HANYA TEKS + TEMPLATE, TANPA PARAF)
        $this->createVectorPdfFile($tempPdfVector, $data);

        // 3. Rasterize using Ghostscript (Vector PDF -> PNG)
        // Command Windows (Laragon)
        $gsCmd = sprintf(
            'gswin64c -dSAFER -dBATCH -dNOPAUSE -sDEVICE=png16m -r300 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile="%s" "%s"',
            $tempPng,
            $tempPdfVector
        );
        exec($gsCmd, $output, $returnVar);

        // Fallback jika GS gagal: Return Vector PDF (agar user tetap dapat file)
        if (!file_exists($tempPng) || $returnVar !== 0) {
            Log::warning("Ghostscript conversion failed. Returning vector PDF. Error: " . implode(" ", $output));
            $content = file_get_contents($tempPdfVector); // Kembalikan vector jika gagal
            @unlink($tempPdfVector);
            return $content;
        }

        // 4. Create Final PDF (Background Image + Signatures Overlay)
        $finalPdf = new Fpdi('L', 'mm', 'A4');
        $finalPdf->setPrintHeader(false);
        $finalPdf->setPrintFooter(false);
        $finalPdf->SetAutoPageBreak(false, 0);
        $finalPdf->AddPage();

        // Layer 1: Background Image (Anti Copy)
        $finalPdf->Image($tempPng, 0, 0, 297, 210, 'PNG');

        // Layer 2: Signatures (High Quality Overlay)
        $boxX = 238.59;
        $boxWidth = 4.529;
        $boxHeight = 2.074;
        $this->placeSignature($finalPdf, $data['signature_path'], $boxX, 175.062, $boxWidth, $boxHeight);
        $this->placeSignature($finalPdf, $data['signature_path_2'], $boxX, 177.625, $boxWidth, $boxHeight);
        $this->placeSignature($finalPdf, $data['signature_path_3'], $boxX, 180.188, $boxWidth, $boxHeight);

        // 5. Output Content
        $content = $finalPdf->Output('doc.pdf', 'S');

        // 6. Cleanup
        @unlink($tempPdfVector);
        @unlink($tempPng);

        return $content;
    }

    private function createVectorPdfFile($outputPath, $data)
    {
        $pdf = new Fpdi('L', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);

        $templatePath = Storage::disk('master_gambar')->path($data['source_pdf_path']);

        if (!file_exists($templatePath)) {
            $pdf->AddPage();
            $pdf->SetFont('arial', '', 12);
            $pdf->Text(10, 10, 'File not found');
            $pdf->Output($outputPath, 'F');
            return;
        }

        $pdf->setSourceFile($templatePath);
        $tplId = $pdf->importPage(1);
        $pdf->AddPage();
        $pdf->useTemplate($tplId, ['adjustPageSize' => true]);

        // LOGIKA PENULISAN TEKS (DIPERBAIKI)
        $pdf->SetFont('arial', '', 4.3);
        $pdf->SetXY(225.862, 175.205);
        $pdf->Write(0, $data['digambar'] ?? '');
        $pdf->SetXY(225.862, 177.768);
        $pdf->Write(0, $data['diperiksa'] ?? '');
        $pdf->SetXY(225.862, 180.331);
        $pdf->Write(0, $data['disetujui'] ?? '');

        $pdf->SetXY(243.53, 175.205);
        $pdf->Cell(8.377, 0, $data['tanggal'] ?? '', 0, 0, 'C');
        $pdf->SetXY(243.53, 177.768);
        $pdf->Cell(8.377, 0, $data['tanggal'] ?? '', 0, 0, 'C');
        $pdf->SetXY(243.53, 180.331);
        $pdf->Cell(8.377, 0, $data['tanggal'] ?? '', 0, 0, 'C');

        // CATATAN: KITA TIDAK MEMANGGIL placeSignature DI SINI (AGAR PARAF TIDAK DIRASTER)

        $text = $data['karoseri'] ?? '';
        $cellWidth = 44.149;
        $fontSize = 8;
        $pdf->SetFont('arial', '', $fontSize);
        while ($pdf->GetStringWidth($text) > ($cellWidth - 1)) {
            $fontSize -= 0.1;
            $pdf->SetFont('arial', '', $fontSize);
            if ($fontSize < 5) break;
        }
        $pdf->SetXY(216.984, 194.679);
        $pdf->Cell($cellWidth, 0, $text, 0, 0, 'C');

        $pdf->SetFont('arial', '', 7);
        $pdf->SetXY(274.381, 194.118);
        $pdf->Write(0, $data['no_halaman'] ?? '');
        $pdf->SetFont('arial', '', 5);
        $pdf->SetXY(275.342, 198.311);
        $pdf->Cell(10.139, 0, ($data['no_halaman'] ?? '') . ' / ' . ($data['total_halaman'] ?? ''), 0, 0, 'C');

        if (($data['type'] ?? '') === 'kelistrikan') {
            $finalText = sprintf('%s', $data['judul_gambar'] ?? '');
            $maxWidth = 68.54;
            $fontSize = 6;
            $minFontSize = 3;
            $pdf->SetFont('arial', '', $fontSize);
            while ($pdf->GetStringWidth($finalText) > ($maxWidth - 1) && $fontSize > $minFontSize) {
                $fontSize -= 0.2;
                $pdf->SetFont('arial', '', $fontSize);
            }
            $pdf->SetXY(216.847, 188.632);
            $pdf->Cell($maxWidth, 0, $finalText, 0, 0, 'C');
        } else {
            $text = $data['judul_gambar'] ?? '';
            $maxWidth = 68.54;
            $fontSize = 6;
            $minFontSize = 3;
            $pdf->SetFont('arial', '', $fontSize);
            $pdf->setFontSpacing(-0.09);
            while ($pdf->GetStringWidth($text) > $maxWidth && $fontSize > $minFontSize) {
                $fontSize -= 0.2;
                $pdf->SetFont('arial', '', $fontSize);
                $pdf->setFontSpacing(-0.09);
            }
            $pdf->SetXY(216.847, 183.252);
            $pdf->Cell($maxWidth, 0, $text, 0, 0, 'C');

            if (!empty($data['deskripsi_optional'])) {
                $pdf->SetFont('arial', '', 8);

                // --- LOGIKA DESC SPACE ---
                $baseY = 161.858;
                $lineHeight = 2.898;
                $spaceMultiplier = isset($data['desc_space']) ? (int)$data['desc_space'] : 0;

                $calculatedY = $baseY - ($lineHeight * $spaceMultiplier);

                $pdf->SetXY(211.878, $calculatedY);
                $pdf->Write(0, $data['deskripsi_optional']);
            }
        }

        $pdf->Output($outputPath, 'F');
    }

    private function placeSignature(Fpdi &$pdf, $imagePath, $boxX, $boxY, $boxWidth, $boxHeight)
    {
        if (!$imagePath || !file_exists($imagePath)) return;
        list($w, $h) = getimagesize($imagePath);
        if ($h == 0) return;
        $newW = ($w / $h) * $boxHeight;
        $newH = $boxHeight;
        $posX = $boxX + (($boxWidth - $newW) / 2);
        $pdf->Image($imagePath, $posX, $boxY, $newW, $newH, 'PNG');
    }
}
