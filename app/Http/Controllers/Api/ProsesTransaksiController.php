<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Models\TransaksiOptional;
use App\Models\TransaksiDetail;
use App\Models\TransaksiVarian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProsesTransaksiController extends Controller
{
    /**
     * Method utama untuk memproses transaksi.
     */
    public function proses(Request $request, Transaksi $transaksi)
    {
        $varianCount = count($request->input('varian_body_ids', []));

        $validated = $request->validate([
            'pemeriksa_id' => 'required|exists:users,id',
            'varian_body_ids' => 'required|array|min:1|max:4',
            'varian_body_ids.*' => 'required|exists:e_varian_body,id',
            'judul_gambar_ids' => ['required', 'array', "size:$varianCount"],
            'judul_gambar_ids.*' => ['required', 'integer', 'exists:j_judul_gambars,id'],
            'h_gambar_optional_ids' => 'nullable|array|min:1|max:5',
            'h_gambar_optional_ids.*' => 'required|integer|exists:h_gambar_optional,id',
            'i_gambar_kelistrikan_id' => 'nullable|exists:i_gambar_kelistrikan,id',
            'aksi' => 'required|in:preview,proses',
        ]);

        // 1. Simpan semua detail transaksi dalam SATU transaction block
        try {
            DB::beginTransaction();

            $detail = TransaksiDetail::updateOrCreate(
                ['z_transaksi_id' => $transaksi->id],
                [
                    'pemeriksa_id' => $validated['pemeriksa_id'],
                    'i_gambar_kelistrikan_id' => $validated['i_gambar_kelistrikan_id'] ?? null,
                ]
            );

            // Hapus data lama
            $detail->varians()->delete();
            $detail->optionals()->delete();

            // Buat data Varian Utama yang baru
            foreach ($validated['varian_body_ids'] as $index => $varian_id) {
                TransaksiVarian::create([
                    'z_transaksi_detail_id' => $detail->id,
                    'e_varian_body_id' => $varian_id,
                    'urutan' => $index + 1,
                    'j_judul_gambar_id' => $validated['judul_gambar_ids'][$index],
                ]);
            }

            // Buat data Varian Optional yang baru
            if (!empty($validated['h_gambar_optional_ids'])) {
                foreach ($validated['h_gambar_optional_ids'] as $index => $optional_id) {
                    TransaksiOptional::create([
                        'z_transaksi_detail_id' => $detail->id,
                        'h_gambar_optional_id' => $optional_id,
                        'urutan' => $index + 1,
                    ]);
                }
            }

            // Hanya ada SATU commit di akhir
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan detail transaksi.', 'error' => $e->getMessage()], 500);
        }

        // 2. Muat semua data yang diperlukan (kode ini tidak berubah)
        $transaksi->load([
            'user',
            'customer',
            'fPengajuan',
            'detail.pemeriksa',
            'detail.optionals.gambarOptional',
            'detail.gambarKelistrikan',
            'detail.varians.varianBody.gambarUtama',
            'detail.varians.judulGambar',
            'detail.varians.varianBody.jenisKendaraan.typeChassis.merk.typeEngine'
        ]);

        // 3. Bangun "Daftar Pekerjaan Gambar"
        $drawingJobs = [];
        $pageCounter = 1;

        $jenisKendaraan = $transaksi->detail->varians->first()->varianBody->jenisKendaraan;
        $chassis = $jenisKendaraan->typeChassis;
        $merk = $chassis->merk;

        foreach ($transaksi->detail->varians as $transaksiVarian) {
            $varianBody = $transaksiVarian->varianBody;
            $gambarUtamaData = $varianBody->gambarUtama;
            $jenisJudul = $transaksiVarian->judulGambar->nama_judul;

            if ($gambarUtamaData) {
                $drawingJobs[] = ['title' => 'GAMBAR TAMPAK UTAMA ' . $jenisJudul, 'varian' => $varianBody->varian_body, 'page' => $pageCounter++, 'source_pdf' => $gambarUtamaData->path_gambar_utama];
                $drawingJobs[] = ['title' => 'GAMBAR TAMPAK TERURAI ' . $jenisJudul, 'varian' => $varianBody->varian_body, 'page' => $pageCounter++, 'source_pdf' => $gambarUtamaData->path_gambar_terurai];
                $drawingJobs[] = ['title' => 'GAMBAR DETAIL KONTRUKSI ' . $jenisJudul, 'varian' => $varianBody->varian_body, 'page' => $pageCounter++, 'source_pdf' => $gambarUtamaData->path_gambar_kontruksi];
            }
        }

        foreach ($transaksi->detail->optionals as $transaksiOptional) {
            $gambarOptional = $transaksiOptional->gambarOptional;
            if ($gambarOptional) {
                $drawingJobs[] = ['title' => $gambarOptional->deskripsi ?: 'GAMBAR OPTIONAL', 'varian' => '', 'page' => $pageCounter++, 'source_pdf' => $gambarOptional->path_gambar_optional];
            }
        }
        if ($transaksi->detail->gambarKelistrikan) {
            $drawingJobs[] = ['title' => $transaksi->detail->gambarKelistrikan->deskripsi ?: 'GAMBAR KELISTRIKAN', 'varian' => '', 'page' => $pageCounter++, 'source_pdf' => $transaksi->detail->gambarKelistrikan->path_gambar_kelistrikan];
        }

        $totalHalaman = count($drawingJobs);
        $generatedPdfs = [];

        // 4. Proses setiap pekerjaan
        foreach ($drawingJobs as $job) {
            $pdfData = [
                'digambar' => $transaksi->user->name,
                'diperiksa' => $transaksi->detail->pemeriksa->name,
                'disetujui' => $transaksi->customer->pj,
                'tanggal' => now()->format('d.m.y'),
                'catatan' => $job['varian'],
                'judul_gambar' => $job['title'],
                'karoseri' => $transaksi->customer->nama_pt,
                'no_halaman' => str_pad($job['page'], 2, '0', STR_PAD_LEFT),
                'total_halaman' => str_pad($totalHalaman, 2, '0', STR_PAD_LEFT),
                'source_pdf_path' => $job['source_pdf'],
                'signature_path' => $transaksi->user->signature ? Storage::disk('user_paraf')->path($transaksi->user->signature) : null,
                'signature_path_2' => $transaksi->detail->pemeriksa->signature ? Storage::disk('user_paraf')->path($transaksi->detail->pemeriksa->signature) : null,
                'signature_path_3' => $transaksi->customer->signature_pj ? Storage::disk('customer_paraf')->path($transaksi->customer->signature_pj) : null,
            ];

            $pdfContent = $this->generateSinglePdfPage($pdfData);
            $generatedPdfs[] = ['name' => $job['page'] . '.pdf', 'content' => $pdfContent];
        }

        // 5. Tentukan aksi final
        if ($validated['aksi'] === 'preview') {
            return response($generatedPdfs[0]['content'], 200)->header('Content-Type', 'application/pdf');
        } else {
            // --- LOGIKA PEMBUATAN FOLDER ---
            $folderName = sprintf(
                '%s (%s) %s_%s_%s (%s)',
                $transaksi->user->username,
                $transaksi->fPengajuan->jenis_pengajuan,
                $transaksi->customer->nama_pt,
                $merk->merk,
                $chassis->type_chassis,
                $jenisKendaraan->jenis_kendaraan
            );
            // Nama subfolder yang bersih (tanpa spasi/karakter aneh)
            $folderPath = Str::slug($folderName);

            // --- LOGIKA PENYIMPANAN ---
            foreach ($generatedPdfs as $pdfFile) {
                // Gunakan disk 'hasil_transaksi' yang baru
                Storage::disk('hasil_transaksi')->put($folderPath . '/' . $pdfFile['name'], $pdfFile['content']);
            }
            return response()->json(['message' => 'Proses berhasil!', 'folder_path' => 'D:/_Master/hasil/' . $folderPath]);
        }
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
