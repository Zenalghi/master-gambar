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

class ProsesTransaksiController extends Controller
{
    public function saveDraft(Request $request, Transaksi $transaksi)
    {
        $validated = $request->validate([
            'pemeriksa_id' => 'required|exists:users,id',
            'jumlah_gambar' => 'required|integer|min:1|max:4',
            'data_gambar_utama' => 'required|array',
            'deskripsi_optional' => 'nullable|string',
            'ordered_independent_ids' => 'nullable|array',
            'ordered_independent_ids.*' => 'integer',
        ]);

        $detail = TransaksiDetail::updateOrCreate(
            ['transaksi_id' => $transaksi->id],
            [
                'pemeriksa_id' => $validated['pemeriksa_id'],
                'jumlah_gambar' => $validated['jumlah_gambar'],
                'data_gambar_utama' => $validated['data_gambar_utama'],
                'ordered_independent_ids' => $validated['ordered_independent_ids'] ?? [],
                'deskripsi_optional' => $validated['deskripsi_optional'],
            ]
        );
        $detail->touch();

        return response()->json(['message' => 'Draft berhasil disimpan', 'detail' => $detail]);
    }

    public function proses(Request $request, Transaksi $transaksi)
    {
        $varianCount = count($request->input('varian_body_ids', []));

        $validated = $request->validate([
            'pemeriksa_id' => 'required|exists:users,id',
            'varian_body_ids' => 'required|array|min:1|max:20',
            'varian_body_ids.*' => 'required|integer|exists:e_varian_body,id',
            'judul_gambar_ids' => ['required', 'array', "size:$varianCount"],
            'judul_gambar_ids.*' => 'required|integer|exists:j_judul_gambars,id',
            'h_gambar_optional_ids' => 'nullable|array',
            'h_gambar_optional_ids.*' => 'integer|exists:h_gambar_optional,id',
            'i_gambar_kelistrikan_id' => 'nullable|integer|exists:i_gambar_kelistrikan,id',
            'aksi' => 'required|in:preview,proses',
            'preview_page' => 'nullable|integer|min:1',
            'ordered_independent_ids' => 'nullable|array',
            'ordered_independent_ids.*' => 'integer',
            'deskripsi_optional' => 'nullable|string|max:255',
        ]);

        // Kita susun ulang data_gambar_utama dari input terpisah (varian & judul)
        // Agar formatnya sama dengan format Save Draft JSON
        $dataGambarUtamaJSON = [];
        $inputVarian = $request->input('varian_body_ids', []);
        $inputJudul = $request->input('judul_gambar_ids', []);

        foreach ($inputVarian as $index => $varianId) {
            $dataGambarUtamaJSON[] = [
                'varian_id' => $varianId,
                'judul_id' => $inputJudul[$index] ?? null
            ];
        }
        // Simpan ke DB
        TransaksiDetail::updateOrCreate(
            ['transaksi_id' => $transaksi->id],
            [
                'pemeriksa_id' => $request->pemeriksa_id,
                'jumlah_gambar' => count($dataGambarUtamaJSON),
                'data_gambar_utama' => $dataGambarUtamaJSON,
                'ordered_independent_ids' => $validated['ordered_independent_ids'] ?? [],
                'deskripsi_optional' => $request->deskripsi_optional,
            ]
        );
        $transaksi->detail->touch();

        $transaksi->load([
            'user',
            'customer',
            'fPengajuan',
            'masterData.typeEngine',
            'masterData.merk',
            'masterData.typeChassis',
            'masterData.jenisKendaraan'
        ]);

        $pemeriksa = User::find($validated['pemeriksa_id']);
        $masterData = $transaksi->masterData;

        // --- CEK JENIS PENGAJUAN ---
        $jenisPengajuan = strtoupper($transaksi->fPengajuan->jenis_pengajuan);
        $isGambarTU = ($jenisPengajuan === 'GAMBAR TU');

        // --- SIAPKAN WADAH TERPISAH PER KATEGORI ---
        $jobsUtama = [];
        $jobsTerurai = [];
        $jobsKontruksi = [];
        $jobsPaket = [];
        $jobsIndependen = [];
        $jobsKelistrikan = [];

        // --- TAHAP 1: LOOPING VARIAN BODY ---
        if (!empty($validated['varian_body_ids'])) {
            foreach ($validated['varian_body_ids'] as $index => $varian_id) {
                $varianBody = EVarianBody::find($varian_id);
                $gambarUtamaData = GGambarUtama::with('gambarOptionals')
                    ->where('e_varian_body_id', $varian_id)
                    ->first();
                $jenisJudul = JJudulGambar::find($validated['judul_gambar_ids'][$index]);

                if ($gambarUtamaData && $jenisJudul) {

                    // 1. Array UTAMA (SELALU DIMASUKKAN)
                    $jobsUtama[] = [
                        'type' => 'standard',
                        'title' => 'GAMBAR TAMPAK UTAMA ' . $jenisJudul->nama_judul,
                        'varian' => $varianBody->varian_body,
                        'source_pdf' => $gambarUtamaData->path_gambar_utama,
                        'deskripsi_optional' => $validated['deskripsi_optional'] ?? null
                    ];

                    // --- LOGIKA SKIP: JIKA 'GAMBAR TU', SKIP SISANYA ---
                    if ($isGambarTU) {
                        continue; // Lanjut ke varian berikutnya, abaikan Terurai/Kontruksi/Paket
                    }

                    // 2. Masukkan ke Array TERURAI
                    $jobsTerurai[] = [
                        'type' => 'standard',
                        'title' => 'GAMBAR TAMPAK TERURAI ' . $jenisJudul->nama_judul,
                        'varian' => $varianBody->varian_body,
                        'source_pdf' => $gambarUtamaData->path_gambar_terurai,
                        'deskripsi_optional' => null
                    ];

                    // 3. Masukkan ke Array KONTRUKSI
                    $jobsKontruksi[] = [
                        'type' => 'standard',
                        'title' => 'GAMBAR DETAIL KONTRUKSI ' . $jenisJudul->nama_judul,
                        'varian' => $varianBody->varian_body,
                        'source_pdf' => $gambarUtamaData->path_gambar_kontruksi,
                        'deskripsi_optional' => null
                    ];

                    // 4. Masukkan ke Array PAKET (Cek ID yang dikirim frontend)
                    // Note: Sorting paket di frontend sudah dihandle _OptionController, 
                    // di sini kita collect berdasarkan varian loop agar urut.
                    foreach ($gambarUtamaData->gambarOptionals as $gambarPaket) {
                        if ($gambarPaket->tipe === 'paket' && in_array($gambarPaket->id, $validated['h_gambar_optional_ids'] ?? [])) {

                            // LOGIKA PENGGABUNGAN NAMA
                            $judulDasar = $gambarPaket->deskripsi ?: 'GAMBAR OPTIONAL PAKET';
                            $judulLengkap = $judulDasar . ' ' . $jenisJudul->nama_judul;

                            $jobsPaket[] = [
                                'type' => 'standard',
                                'title' => $judulLengkap, // <--- Ganti ini
                                'varian' => '',
                                'source_pdf' => $gambarPaket->path_gambar_optional,
                                'deskripsi_optional' => null
                            ];
                        }
                    }
                }
            }
        }
        if (!$isGambarTU) {
            // --- TAHAP 2: GAMBAR OPTIONAL INDEPENDEN ---
            if ($request->has('ordered_independent_ids') && !empty($request->ordered_independent_ids)) {

                $orderedIds = $request->ordered_independent_ids;

                // Ambil data gambar berdasarkan ID tersebut
                $gambarIndependen = HGambarOptional::whereIn('id', $orderedIds)
                    ->where('tipe', 'independen')
                    ->get();

                // PENTING: Sorting manual sesuai urutan ID dari Frontend (Drag & Drop)
                $idMap = array_flip($orderedIds);
                $gambarIndependen = $gambarIndependen->sortBy(function ($model) use ($idMap) {
                    return $idMap[$model->id] ?? 999;
                });

                foreach ($gambarIndependen as $gambarOptional) {
                    $jobsIndependen[] = [
                        'type' => 'standard',
                        'title' => $gambarOptional->deskripsi ?: 'GAMBAR OPTIONAL',
                        'varian' => '',
                        'source_pdf' => $gambarOptional->path_gambar_optional,
                        'deskripsi_optional' => null
                    ];
                }
            }
            // Fallback (Jaga-jaga jika request lama): Ambil by Varian Body (Logic lama Anda)
            else if (!empty($validated['varian_body_ids'])) {
                // 1. Cari Master Data ID dari varian yg dipilih
                $masterDataIds = EVarianBody::whereIn('id', $validated['varian_body_ids'])
                    ->pluck('master_data_id')
                    ->unique();

                // 2. Ambil Gambar Independen milik Master Data tsb
                $gambarIndependen = HGambarOptional::whereIn('master_data_id', $masterDataIds)
                    ->where('tipe', 'independen')
                    ->get();

                // Opsional: Urutkan hasil agar sesuai urutan varian di input
                // (Agar gambar independen varian 1 muncul sebelum varian 2)
                $urutanVarian = array_flip($validated['varian_body_ids']);
                $gambarIndependen = $gambarIndependen->sortBy(function ($model) use ($urutanVarian) {
                    return $urutanVarian[$model->e_varian_body_id] ?? 999;
                });

                foreach ($gambarIndependen as $gambarOptional) {
                    $jobsIndependen[] = [
                        'type' => 'standard',
                        'title' => $gambarOptional->deskripsi ?: 'GAMBAR OPTIONAL',
                        'varian' => '', // Atau isi dengan nama varian jika perlu
                        'source_pdf' => $gambarOptional->path_gambar_optional,
                        'deskripsi_optional' => null
                    ];
                }
            }

            // --- TAHAP 3: KELISTRIKAN ---
            if (isset($validated['i_gambar_kelistrikan_id'])) {
                $gambarKelistrikan = IGambarKelistrikan::with('fileKelistrikan')
                    ->find($validated['i_gambar_kelistrikan_id']);

                if ($gambarKelistrikan) {
                    $jobsKelistrikan[] = [
                        'type' => 'kelistrikan',
                        'title' => $gambarKelistrikan->deskripsi ?: 'GAMBAR KELISTRIKAN',
                        'jenis_kendaraan' => $masterData->jenisKendaraan->jenis_kendaraan ?? '',
                        'varian' => '',
                        'source_pdf' => $gambarKelistrikan->path_gambar_kelistrikan,
                        'deskripsi_optional' => null
                    ];
                }
            }
        }

        // --- PENGGABUNGAN (MERGE) SESUAI URUTAN BARU ---
        // Urutan: Utama -> Terurai -> Kontruksi -> Paket -> Independen -> Kelistrikan
        $drawingJobs = array_merge(
            $jobsUtama,
            $jobsTerurai,
            $jobsKontruksi,
            $jobsPaket,
            $jobsIndependen,
            $jobsKelistrikan
        );

        // Beri Nomor Halaman Berurutan
        $pageCounter = 1;
        foreach ($drawingJobs as &$job) {
            $job['page'] = $pageCounter++;
        }
        unset($job);

        $totalHalaman = count($drawingJobs);

        // 5. Eksekusi
        if ($validated['aksi'] === 'preview') {
            $previewPage = $validated['preview_page'] ?? 1;
            $previewIndex = $previewPage - 1;

            if (isset($drawingJobs[$previewIndex])) {
                $job = $drawingJobs[$previewIndex];

                // Validasi file fisik
                if (!Storage::disk('master_gambar')->exists($job['source_pdf'])) {
                    return response()->json(['message' => 'File PDF sumber tidak ditemukan: ' . $job['source_pdf']], 404);
                }

                $pdfData = $this->buildPdfData($job, $transaksi, $pemeriksa, $totalHalaman);
                $pdfContent = $this->generateSinglePdfPage($pdfData);

                // --- FIX PENTING: Bersihkan buffer output sebelum kirim PDF ---
                if (ob_get_length()) ob_clean();
                // -------------------------------------------------------------

                return response($pdfContent, 200)->header('Content-Type', 'application/pdf');
            } else {
                return response()->json(['message' => 'Halaman preview tidak ditemukan.'], 404);
            }
        } else {
            // Proses Download ZIP
            $generatedPdfs = [];
            foreach ($drawingJobs as $job) {
                if (!Storage::disk('master_gambar')->exists($job['source_pdf'])) continue; // Skip jika file hilang

                $pdfData = $this->buildPdfData($job, $transaksi, $pemeriksa, $totalHalaman);
                $pdfContent = $this->generateSinglePdfPage($pdfData);
                $generatedPdfs[] = ['name' => $job['page'] . '.pdf', 'content' => $pdfContent];
            }

            if (empty($generatedPdfs)) {
                return response()->json(['message' => 'Tidak ada gambar yang berhasil diproses.'], 404);
            }

            $zipFileName = sprintf(
                '%s (%s) %s_%s %s (%s).zip',
                $transaksi->user->username,
                $transaksi->fPengajuan->jenis_pengajuan,
                $transaksi->customer->nama_pt,
                $masterData->merk->merk,
                $masterData->typeChassis->type_chassis,
                $masterData->jenisKendaraan->jenis_kendaraan
            );
            $cleanZipFileName = Str::slug(pathinfo($zipFileName, PATHINFO_FILENAME)) . '.zip';

            $zip = new \ZipArchive();
            $tempZipPath = tempnam(sys_get_temp_dir(), 'gambar_');
            $zip->open($tempZipPath, \ZipArchive::CREATE);
            foreach ($generatedPdfs as $pdfFile) {
                $zip->addFromString($pdfFile['name'], $pdfFile['content']);
            }
            $zip->close();

            // --- FIX PENTING: Bersihkan buffer di sini juga ---
            if (ob_get_length()) ob_clean();
            return response()->download($tempZipPath, $cleanZipFileName)->deleteFileAfterSend(true);
        }
    }

    private function buildPdfData(array $job, Transaksi $transaksi, User $pemeriksa, int $totalHalaman): array
    {
        return [
            'type' => $job['type'],
            'digambar' => $transaksi->user->name,
            'diperiksa' => $pemeriksa->name,
            'disetujui' => $transaksi->customer->pj,
            'tanggal' => now()->format('d.m.y'),
            'judul_gambar' => $job['title'],
            'catatan' => $job['varian'],
            'jenis_kendaraan' => $job['jenis_kendaraan'] ?? '',
            'karoseri' => $transaksi->customer->nama_pt,
            'no_halaman' => str_pad($job['page'], 2, '0', STR_PAD_LEFT),
            'total_halaman' => str_pad($totalHalaman, 2, '0', STR_PAD_LEFT),
            'source_pdf_path' => $job['source_pdf'],
            'signature_path' => $transaksi->user->signature ? Storage::disk('user_paraf')->path($transaksi->user->signature) : null,
            'signature_path_2' => $pemeriksa->signature ? Storage::disk('user_paraf')->path($pemeriksa->signature) : null,
            'signature_path_3' => $transaksi->customer->signature_pj ? Storage::disk('customer_paraf')->path($transaksi->customer->signature_pj) : null,
            'deskripsi_optional' => $job['deskripsi_optional'],
        ];
    }

    private function generateSinglePdfPage(array $data): string
    {
        $pdf = new Fpdi('L', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);

        $templatePath = Storage::disk('master_gambar')->path($data['source_pdf_path']);

        if (!file_exists($templatePath)) {
            // Return minimal error PDF if physical file is missing
            $pdf->AddPage();
            $pdf->SetFont('arial', 'B', 12);
            $pdf->Text(10, 10, 'File not found on server');
            return $pdf->Output('err.pdf', 'S');
        }

        $pdf->setSourceFile($templatePath);
        $tplId = $pdf->importPage(1);
        $pdf->AddPage();
        $pdf->useTemplate($tplId, ['adjustPageSize' => true]);

        // Set Font
        $pdf->SetFont('arial', '', 4.3);

        // Tanda Tangan Teks
        $pdf->SetXY(225.862, 175.205);
        $pdf->Write(0, $data['digambar']);
        $pdf->SetXY(225.862, 177.768);
        $pdf->Write(0, $data['diperiksa']);
        $pdf->SetXY(225.862, 180.331);
        $pdf->Write(0, $data['disetujui']);

        // Tanggal
        $pdf->SetXY(243.53, 175.205);
        $pdf->Cell(8.377, 0, $data['tanggal'], 0, 0, 'C');
        $pdf->SetXY(243.53, 177.768);
        $pdf->Cell(8.377, 0, $data['tanggal'], 0, 0, 'C');
        $pdf->SetXY(243.53, 180.331);
        $pdf->Cell(8.377, 0, $data['tanggal'], 0, 0, 'C');

        // Gambar Tanda Tangan
        $boxX = 238.59;
        $boxWidth = 4.529;
        $boxHeight = 2.074;
        $this->placeSignature($pdf, $data['signature_path'], $boxX, 175.062, $boxWidth, $boxHeight);
        $this->placeSignature($pdf, $data['signature_path_2'], $boxX, 177.625, $boxWidth, $boxHeight);
        $this->placeSignature($pdf, $data['signature_path_3'], $boxX, 180.188, $boxWidth, $boxHeight);

        // Karoseri
        $text = $data['karoseri'];
        $cellWidth = 44.149; // Lebar cell sesuai kode Anda
        $fontSize = 8;       // Ukuran font awal

        // Set font awal untuk pengukuran
        $pdf->SetFont('arial', '', $fontSize);

        // LOGIKA AUTO-SHRINK (Pengecilan Otomatis)
        // Loop: Cek apakah lebar teks melebihi lebar cell (dikurangi padding 1mm agar aman)
        while ($pdf->GetStringWidth($text) > ($cellWidth - 1)) {
            $fontSize -= 0.1; // Kurangi ukuran font sebesar 0.1 poin
            $pdf->SetFont('arial', '', $fontSize);

            // Batas minimal font agar tetap terbaca (misal min 5 pt)
            if ($fontSize < 5) break;
        }

        // Posisi dan Cetak
        $pdf->SetXY(216.984, 194.679);
        $pdf->Cell($cellWidth, 0, $text, 0, 0, 'C');
        // Nomor Halaman
        $pdf->SetFont('arial', '', 7);
        $pdf->SetXY(274.381, 194.118);
        $pdf->Write(0, $data['no_halaman']);
        $pdf->SetFont('arial', '', 5);
        $pdf->SetXY(275.342, 198.311);
        $pdf->Cell(10.139, 0, $data['no_halaman'] . ' / ' . $data['total_halaman'], 0, 0, 'C');

        // Logika Khusus kelistrikan
        if ($data['type'] === 'kelistrikan') {
            // --- KHUSUS KELISTRIKAN ---

            // 1. Format String
            $finalText = sprintf('%s', $data['judul_gambar']);

            // 2. Definisi Batas
            $maxWidth = 68.54; // Lebar cell maksimum
            $fontSize = 6;      // Ukuran font awal
            $minFontSize = 3;   // Batas ukuran font terkecil (agar tetap terbaca)

            // Set font awal
            $pdf->SetFont('arial', '', $fontSize);

            // 3. LOGIKA AUTO-SHRINK (Pengecilan Otomatis)
            // Cek lebar teks. Kita kurangi maxWidth dengan 1mm sebagai padding aman.
            while ($pdf->GetStringWidth($finalText) > ($maxWidth - 1) && $fontSize > $minFontSize) {
                $fontSize -= 0.2; // Kurangi 0.2 poin setiap iterasi
                $pdf->SetFont('arial', '', $fontSize);
            }

            // 4. Posisi Koordinat
            $customX = 216.847;
            $customY = 188.632;

            $pdf->SetXY($customX, $customY);

            // 5. Cetak (Font size otomatis sudah terset di loop di atas)
            $pdf->Cell($maxWidth, 0, $finalText, 0, 0, 'C');
        } else {
            // --- STANDARD (Utama, Terurai, Kontruksi, Optional) ---
            // 1. Definisi Variabel
            $text = $data['judul_gambar'];
            $maxWidth = 68.54; // Lebar cell yang tersedia
            $fontSize = 6;     // Ukuran font awal
            $minFontSize = 3;  // Batas font terkecil (agar tetap terbaca)

            // Set settingan awal
            $pdf->SetFont('arial', '', $fontSize);
            $pdf->setFontSpacing(-0.09);

            // 2. LOGIKA AUTO-SHRINK (Pengecilan Otomatis)
            // Selama teks lebih lebar dari cell DAN font masih di atas batas minimum
            while ($pdf->GetStringWidth($text) > $maxWidth && $fontSize > $minFontSize) {
                $fontSize -= 0.2; // Kurangi 0.2 poin
                $pdf->SetFont('arial', '', $fontSize);
                $pdf->setFontSpacing(-0.09); // Set ulang spacing (jaga-jaga jika reset saat SetFont)
            }

            // 3. Cetak Judul Gambar di Kanan Bawah
            $pdf->SetXY(216.847, 183.252);
            $pdf->Cell($maxWidth, 0, $text, 0, 0, 'C');

            // 2. Cetak Deskripsi Optional (hanya ada di standard)
            if (!empty($data['deskripsi_optional'])) {
                $pdf->SetFont('arial', '', 8);
                $pdf->SetXY(211.878, 161.858);
                $pdf->Write(0, $data['deskripsi_optional']);
            }
        }

        return $pdf->Output('doc.pdf', 'S');
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
