<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EVarianBody;
use App\Models\GGambarUtama;
use App\Models\HGambarOptional;
use App\Models\IGambarKelistrikan;
use App\Models\JJudulGambar;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Str;

class ProsesTransaksiController extends Controller
{
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
            'deskripsi_optional' => 'nullable|string|max:255',
        ]);

        // Muat data relasi
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

        $drawingJobs = [];
        $pageCounter = 1;

        // --- TAHAP 1 & 2: Gambar Utama & Paket ---
        foreach ($validated['varian_body_ids'] as $index => $varian_id) {
            $varianBody = EVarianBody::find($varian_id);

            // Ambil Gambar Utama
            $gambarUtamaData = GGambarUtama::with('gambarOptionals')
                ->where('e_varian_body_id', $varian_id)
                ->first();

            $jenisJudul = JJudulGambar::find($validated['judul_gambar_ids'][$index]);

            if ($gambarUtamaData && $jenisJudul) {
                // 1. Utama
                $drawingJobs[] = [
                    'type' => 'standard',
                    'title' => 'GAMBAR TAMPAK UTAMA ' . $jenisJudul->nama_judul,
                    'varian' => $varianBody->varian_body,
                    'page' => $pageCounter++,
                    'source_pdf' => $gambarUtamaData->path_gambar_utama,
                    'deskripsi_optional' => $validated['deskripsi_optional'] ?? null
                ];
                // 2. Terurai
                $drawingJobs[] = [
                    'type' => 'standard',
                    'title' => 'GAMBAR TAMPAK TERURAI ' . $jenisJudul->nama_judul,
                    'varian' => $varianBody->varian_body,
                    'page' => $pageCounter++,
                    'source_pdf' => $gambarUtamaData->path_gambar_terurai,
                    'deskripsi_optional' => null
                ];
                // 3. Kontruksi
                $drawingJobs[] = [
                    'type' => 'standard',
                    'title' => 'GAMBAR DETAIL KONTRUKSI ' . $jenisJudul->nama_judul,
                    'varian' => $varianBody->varian_body,
                    'page' => $pageCounter++,
                    'source_pdf' => $gambarUtamaData->path_gambar_kontruksi,
                    'deskripsi_optional' => null
                ];

                // 4. Gambar Paket (Looping yang terhubung ke Gambar Utama ini)
                foreach ($gambarUtamaData->gambarOptionals as $gambarPaket) {
                    // Hanya masukkan jika tipenya paket DAN ID-nya dikirim oleh frontend
                    if ($gambarPaket->tipe === 'paket' && in_array($gambarPaket->id, $validated['h_gambar_optional_ids'] ?? [])) {
                        $drawingJobs[] = [
                            'type' => 'standard',
                            'title' => $gambarPaket->deskripsi ?: 'GAMBAR OPTIONAL PAKET',
                            'varian' => '',
                            'page' => $pageCounter++,
                            'source_pdf' => $gambarPaket->path_gambar_optional,
                            'deskripsi_optional' => null
                        ];
                    }
                }
            }
        }

        // --- TAHAP 3: Gambar Optional Independen ---
        if (!empty($validated['h_gambar_optional_ids'])) {
            $gambarIndependen = HGambarOptional::whereIn('id', $validated['h_gambar_optional_ids'])
                ->where('tipe', 'independen')
                ->get();

            foreach ($gambarIndependen as $gambarOptional) {
                $drawingJobs[] = [
                    'type' => 'standard',
                    'title' => $gambarOptional->deskripsi ?: 'GAMBAR OPTIONAL',
                    'varian' => '',
                    'page' => $pageCounter++,
                    'source_pdf' => $gambarOptional->path_gambar_optional,
                    'deskripsi_optional' => null
                ];
            }
        }

        // --- TAHAP 4: Gambar Kelistrikan ---
        if (isset($validated['i_gambar_kelistrikan_id'])) {
            $gambarKelistrikan = IGambarKelistrikan::find($validated['i_gambar_kelistrikan_id']);
            if ($gambarKelistrikan) {
                $drawingJobs[] = [
                    'type' => 'kelistrikan',
                    'title' => $gambarKelistrikan->deskripsi ?: 'GAMBAR KELISTRIKAN',
                    'jenis_kendaraan' => $masterData->jenisKendaraan->jenis_kendaraan ?? '',
                    'varian' => '',
                    'page' => $pageCounter++,
                    'source_pdf' => $gambarKelistrikan->path_gambar_kelistrikan,
                    'deskripsi_optional' => null
                ];
            }
        }

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
            // -------------------------------------------------

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
            $pdf->SetFont('helvetica', 'B', 12);
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
        $pdf->SetFont('arial', '', 8);
        $pdf->SetXY(217.004, 194.679);
        $pdf->Cell(44.149, 0, $data['karoseri'], 0, 0, 'C');

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

            // 1. Format String: "Deskripsi (Jenis Kendaraan)"
            $finalText = sprintf('%s (%s)', $data['judul_gambar'], $data['jenis_kendaraan']);

            // 2. Atur Font & Posisi
            $pdf->SetFont('arial', '', 6);

            // TODO: Sesuaikan Y di sini nanti
            $customX = 215.686; // Sama dengan X judul gambar standar
            $customY = 188.586; // <-- GANTI INI NANTI dengan posisi Y yang Anda mau

            $pdf->SetXY($customX, $customY);
            $pdf->Cell(68.654, 0, $finalText, 0, 0, 'C'); // Align Center agar rapi di kolom

            // PENTING: Tidak mencetak 'judul_gambar' standar di sini, sudah diganti dengan $finalText di atas.

        } else {
            // --- STANDARD (Utama, Terurai, Kontruksi, Optional) ---

            // 1. Cetak Judul Gambar Standar di Kanan Bawah
            $pdf->SetFont('arial', '', 6);
            $pdf->setFontSpacing(-0.09); // Rapatkan sedikit jika panjang
            $pdf->SetXY(215.686, 183.252);
            $pdf->Cell(68.654, 0, $data['judul_gambar'], 0, 0, 'C');

            // 2. Cetak Deskripsi Optional (hanya ada di standard)
            if (!empty($data['deskripsi_optional'])) {
                $pdf->SetFont('arial', '', 6);
                $pdf->SetXY(208.573, 163.897);
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
