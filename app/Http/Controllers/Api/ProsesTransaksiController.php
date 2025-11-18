<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EVarianBody;
use App\Models\GGambarUtama;
use App\Models\HGambarOptional;
use App\Models\IGambarKelistrikan;
use App\Models\JJudulGambar;
use App\Models\Transaksi;
use App\Models\User; // <-- Import User untuk data pemeriksa
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Str;

class ProsesTransaksiController extends Controller
{
    /**
     * Method utama untuk memproses transaksi.
     */
    public function proses(Request $request, Transaksi $transaksi)
    {
        // 1. Validasi (sedikit penyesuaian pada i_gambar_kelistrikan_id)
        $varianCount = count($request->input('varian_body_ids', []));
        $validated = $request->validate([
            'pemeriksa_id' => 'required|exists:users,id',
            'varian_body_ids' => 'required|array|min:1|max:20',
            'varian_body_ids.*' => 'required|integer|exists:e_varian_body,id',
            'judul_gambar_ids' => ['required', 'array', "size:$varianCount"],
            'judul_gambar_ids.*' => 'required|integer|exists:j_judul_gambars,id',
            'h_gambar_optional_ids' => 'nullable|array|min:1|max:20',
            'h_gambar_optional_ids.*' => 'required|integer|exists:h_gambar_optional,id',
            'i_gambar_kelistrikan_id' => 'nullable|integer|exists:i_gambar_kelistrikan,id',
            'aksi' => 'required|in:preview,proses',
            'preview_page' => 'nullable|integer|min:1',
            'deskripsi_optional' => 'nullable|string|max:255',
        ]);

        // 2. Muat semua data yang diperlukan
        $transaksi->load([
            'user:id,name,username,signature',
            'customer:id,nama_pt,pj,signature_pj',
            'fPengajuan',
            'masterData.typeEngine',
            'masterData.merk',
            'masterData.typeChassis',
            'masterData.jenisKendaraan'
        ]);

        // Ambil data pemeriksa secara manual dari ID yang divalidasi
        $pemeriksa = User::find($validated['pemeriksa_id']);

        // 3. Bangun "Daftar Pekerjaan Gambar"
        $drawingJobs = [];
        $pageCounter = 1;
        $masterData = $transaksi->masterData;
        // TAHAP 1 & 2: Loop untuk Gambar Utama, Terurai, Kontruksi, DAN PAKET
        foreach ($validated['varian_body_ids'] as $index => $varian_id) {
            $varianBody = EVarianBody::find($varian_id);

            // Load relasi gambarUtama DAN gambarOptionals-nya
            $gambarUtamaData = GGambarUtama::with('gambarOptionals')
                ->where('e_varian_body_id', $varian_id)
                ->first();

            $jenisJudul = JJudulGambar::find($validated['judul_gambar_ids'][$index]);

            if ($gambarUtamaData && $jenisJudul) {
                // Proses 3 gambar utama
                $drawingJobs[] = [
                    'title' => 'GAMBAR TAMPAK UTAMA ' . $jenisJudul->nama_judul,
                    'varian' => $varianBody->varian_body,
                    'page' => $pageCounter++,
                    'source_pdf' => $gambarUtamaData->path_gambar_utama,
                    'deskripsi_optional' => $validated['deskripsi_optional'] ?? null
                ];
                $drawingJobs[] = [
                    'title' => 'GAMBAR TAMPAK TERURAI ' . $jenisJudul->nama_judul,
                    'varian' => $varianBody->varian_body,
                    'page' => $pageCounter++,
                    'source_pdf' => $gambarUtamaData->path_gambar_terurai,
                    'deskripsi_optional' => null
                ];
                $drawingJobs[] = [
                    'title' => 'GAMBAR DETAIL KONTRUKSI ' . $jenisJudul->nama_judul,
                    'varian' => $varianBody->varian_body,
                    'page' => $pageCounter++,
                    'source_pdf' => $gambarUtamaData->path_gambar_kontruksi,
                    'deskripsi_optional' => null
                ];

                // LANGSUNG proses gambar "paket" yang terikat pada $gambarUtamaData ini
                foreach ($gambarUtamaData->gambarOptionals as $gambarPaket) {
                    // Pastikan ID gambar paket ini ada di dalam request (jika tidak, lewati)
                    // Ini penting jika user bisa memilih/membatalkan pilihan gambar paket
                    if (in_array($gambarPaket->id, $validated['h_gambar_optional_ids'] ?? [])) {
                        $drawingJobs[] = [
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

        // TAHAP 3: Loop HANYA untuk Gambar Optional Independen
        if (!empty($validated['h_gambar_optional_ids'])) {
            // Ambil hanya gambar independen
            $gambarIndependen = HGambarOptional::whereIn('id', $validated['h_gambar_optional_ids'])
                ->where('tipe', 'independen')
                ->get();

            foreach ($gambarIndependen as $gambarOptional) {
                $drawingJobs[] = [
                    'title' => $gambarOptional->deskripsi ?: 'GAMBAR OPTIONAL',
                    'varian' => '',
                    'page' => $pageCounter++,
                    'source_pdf' => $gambarOptional->path_gambar_optional,
                    'deskripsi_optional' => null
                ];
            }
        }

        // TAHAP 4: Proses Gambar Kelistrikan (terakhir)
        if (isset($validated['i_gambar_kelistrikan_id'])) {
            $gambarKelistrikan = IGambarKelistrikan::find($validated['i_gambar_kelistrikan_id']);
            if ($gambarKelistrikan) {
                $drawingJobs[] = [
                    'title' => $gambarKelistrikan->deskripsi ?: 'GAMBAR KELISTRIKAN',
                    'varian' => '',
                    'page' => $pageCounter++,
                    'source_pdf' => $gambarKelistrikan->path_gambar_kelistrikan,
                    'deskripsi_optional' => null
                ];
            }
        }

        $totalHalaman = count($drawingJobs);

        // 5. Tentukan aksi final
        if ($validated['aksi'] === 'preview') {
            $previewPage = $validated['preview_page'] ?? 1;
            $previewIndex = $previewPage - 1;

            if (isset($drawingJobs[$previewIndex])) {
                $job = $drawingJobs[$previewIndex];
                $pdfData = $this->buildPdfData($job, $transaksi, $pemeriksa, $totalHalaman);
                $pdfContent = $this->generateSinglePdfPage($pdfData);
                return response($pdfContent, 200)->header('Content-Type', 'application/pdf');
            } else {
                return response()->json(['message' => 'Halaman preview tidak ditemukan.'], 404);
            }
        } else { // aksi === 'proses'
            $generatedPdfs = [];
            foreach ($drawingJobs as $job) {
                $pdfData = $this->buildPdfData($job, $transaksi, $pemeriksa, $totalHalaman);
                $pdfContent = $this->generateSinglePdfPage($pdfData);
                $generatedPdfs[] = ['name' => $job['page'] . '.pdf', 'content' => $pdfContent];
            }

            // --- LOGIKA PEMBUATAN NAMA FILE ZIP (DIPERBARUI) ---
            $zipFileName = sprintf(
                '%s-(%s)-%s_%s_%s-(%s).zip',
                $transaksi->user->username,
                $transaksi->fPengajuan->jenis_pengajuan,
                $transaksi->customer->nama_pt,
                $masterData->merk->merk,
                $masterData->typeChassis->type_chassis,
                $masterData->jenisKendaraan->jenis_kendaraan
            );
            $cleanZipFileName = Str::slug(pathinfo($zipFileName, PATHINFO_FILENAME)) . '.zip';

            // ... (Logika pembuatan file ZIP tidak berubah) ...
            $zip = new \ZipArchive();
            $tempZipPath = tempnam(sys_get_temp_dir(), 'gambar_');
            $zip->open($tempZipPath, \ZipArchive::CREATE);
            foreach ($generatedPdfs as $pdfFile) {
                $zip->addFromString($pdfFile['name'], $pdfFile['content']);
            }
            $zip->close();
            return response()->download($tempZipPath, $cleanZipFileName)->deleteFileAfterSend(true);
        }
    }

    /**
     * Helper function untuk membangun array data PDF.
     */
    private function buildPdfData(array $job, Transaksi $transaksi, User $pemeriksa, int $totalHalaman): array
    {
        return [
            'digambar' => $transaksi->user->name,
            'diperiksa' => $pemeriksa->name,
            'disetujui' => $transaksi->customer->pj,
            'tanggal' => now()->format('d.m.y'),
            'catatan' => $job['varian'],
            'judul_gambar' => $job['title'],
            'karoseri' => $transaksi->customer->nama_pt,
            'no_halaman' => str_pad($job['page'], 2, '0', STR_PAD_LEFT),
            'total_halaman' => str_pad($totalHalaman, 2, '0', STR_PAD_LEFT),
            'source_pdf_path' => $job['source_pdf'],
            'signature_path' => $transaksi->user->signature ? Storage::disk('user_paraf')->path($transaksi->user->signature) : null,
            'signature_path_2' => $pemeriksa->signature ? Storage::disk('user_paraf')->path($pemeriksa->signature) : null,
            'signature_path_3' => $transaksi->customer->signature_pj ? Storage::disk('customer_paraf')->path($transaksi->customer->signature_pj) : null,
            'deskripsi_optional' => $job['deskripsi_optional'] ?? null,
        ];
    }

    /**
     * Method private untuk menghasilkan satu halaman PDF.
     */
    private function generateSinglePdfPage(array $data): string
    {
        $pdf = new Fpdi('L', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);

        $templatePath = Storage::disk('master_gambar')->path($data['source_pdf_path']);

        if (!file_exists($templatePath)) {
            $pdf->AddPage();
            $pdf->SetFont('arial', 'B', 12);
            $pdf->Text(10, 10, 'Error: Template PDF not found at ' . $data['source_pdf_path']);
            return $pdf->Output('error.pdf', 'S');
        }

        $pdf->setSourceFile($templatePath);
        $templateId = $pdf->importPage(1);

        $pdf->AddPage();
        $pdf->useTemplate($templateId, ['adjustPageSize' => true]);

        $pdf->SetFont('arial', '', 4.3);
        $pdf->setFontSpacing(0);

        $pdf->SetXY(225.862, 175.205);
        $pdf->Write(0, $data['digambar']);
        $pdf->SetXY(225.862, 177.768);
        $pdf->Write(0, $data['diperiksa']);
        $pdf->SetXY(225.862, 180.331);
        $pdf->Write(0, $data['disetujui']);

        $pdf->SetXY(243.53, 175.205);
        $pdf->Cell(8.377, 0, $data['tanggal'], 0, 0, 'C');
        $pdf->SetXY(243.53, 177.768);
        $pdf->Cell(8.377, 0, $data['tanggal'], 0, 0, 'C');
        $pdf->SetXY(243.53, 180.331);
        $pdf->Cell(8.377, 0, $data['tanggal'], 0, 0, 'C');

        $pdf->SetFont('arial', '', 6);
        $pdf->setFontSpacing(-0.09);
        $pdf->SetXY(215.686, 183.252);
        $pdf->Cell(68.654, 0, $data['judul_gambar'], 0, 0, 'C');

        $pdf->SetFont('arial', '', 8);
        $pdf->setFontSpacing(0);
        $pdf->SetXY(217.004, 194.679);
        $pdf->Cell(44.149, 0, $data['karoseri'], 0, 0, 'C');

        $pdf->SetFont('arial', '', 7);
        $pdf->SetXY(274.381, 194.118);
        $pdf->Write(0, $data['no_halaman']);

        $pdf->SetFont('arial', '', 5);
        $pdf->SetXY(275.342, 198.311);
        $pdf->Cell(10.139, 0, $data['no_halaman'] . ' / ' . $data['total_halaman'], 0, 0, 'C');

        $boxX = 238.59;
        $boxWidth = 4.529;
        $boxHeight = 2.074;
        $this->placeSignature($pdf, $data['signature_path'], $boxX, 175.062, $boxWidth, $boxHeight);
        $this->placeSignature($pdf, $data['signature_path_2'], $boxX, 177.625, $boxWidth, $boxHeight);
        $this->placeSignature($pdf, $data['signature_path_3'], $boxX, 180.188, $boxWidth, $boxHeight);

        if (!empty($data['deskripsi_optional'])) {
            $pdf->SetFont('arial', '', 6);
            $pdf->SetXY(208.573, 163.897);
            $pdf->Write(0, $data['deskripsi_optional']);
        }
        return $pdf->Output('doc.pdf', 'S');
    }

    private function placeSignature(Fpdi &$pdf, $imagePath, $boxX, $boxY, $boxWidth, $boxHeight)
    {
        if (!$imagePath || !file_exists($imagePath)) {
            return;
        }
        list($originalWidth, $originalHeight) = getimagesize($imagePath);
        if ($originalHeight == 0) return;
        $newWidth = ($originalWidth / $originalHeight) * $boxHeight;
        $newHeight = $boxHeight;
        $calculatedX = $boxX + (($boxWidth - $newWidth) / 2);
        $pdf->Image($imagePath, $calculatedX, $boxY, $newWidth, $newHeight, 'PNG');
    }
}
