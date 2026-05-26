<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\OsCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Support\MasterPdf;

class Z_pdf_png_pdfController extends Controller
{
    public function generateUncopyablePdf(Request $request)
    {
        $data = [
            'digambar' => 'Deni',
            'diperiksa' => 'Umardani',
            'disetujui' => 'Yohannes',
            'tanggal' => now()->format('d.m.y'),
            'judul_gambar_1' => 'GAMBAR TAMPAK UTAMA STANDAR',
            'karoseri' => 'PT SURYA INDAH PRATAMA',
            'no_halaman' => '01',
            'total_halaman' => '13',
            'signature_path' => Storage::disk('user_paraf')->path('1/1.png'),
            'signature_path_2' => Storage::disk('user_paraf')->path('3/3.png'),
            'signature_path_3' => Storage::disk('customer_paraf')->path('3/3.png'),
            'deskripsi_optional' => 'Contoh deskripsi tambahan jika diperlukan',
        ];

        // --- 1. GENERATE PDF VEKTOR (HANYA TEKS & TEMPLATE, TANPA PARAF) ---
        $tempPdfPath = storage_path('app/public/temp_vector_' . time() . '.pdf');

        $this->createVectorPdf($tempPdfPath, $data);

        // --- 2. KONVERSI PDF -> PNG (RASTERIZE TEKS) ---
        $tempImagePath = storage_path('app/public/temp_image_' . time() . '.png');

        // Resolusi 300 DPI cukup untuk teks tajam
        $command = OsCommand::buildGhostscriptCommand($tempPdfPath, $tempImagePath);
        exec($command, $output, $returnVar);

        if (!file_exists($tempImagePath) || $returnVar !== 0) {
            return response()->json(['message' => 'Gagal convert PDF ke Gambar.', 'debug' => $output], 500);
        }

        // MENGGUNAKAN CLASS BARU (MasterPdf)
        $finalPdf = new MasterPdf();
        $finalPdf->AddPage();

        // A. TEMPEL GAMBAR BACKGROUND (TEKS YANG SUDAH JADI GAMBAR)
        // Ini membuat teks tidak bisa diblok/copy
        $finalPdf->Image($tempImagePath, 0, 0, 297, 210, 'PNG');

        // B. TEMPEL PARAF (LAYER ATAS - ORIGINAL QUALITY)
        // Kita tempel ulang paraf di sini agar tetap tajam (Vector/High Res Image)
        $boxX = 238.59;
        $boxWidth = 4.529;
        $boxHeight = 2.074;

        $this->placeSignature($finalPdf, $data['signature_path'], $boxX, 175.062, $boxWidth, $boxHeight);
        $this->placeSignature($finalPdf, $data['signature_path_2'], $boxX, 177.625, $boxWidth, $boxHeight);
        $this->placeSignature($finalPdf, $data['signature_path_3'], $boxX, 180.188, $boxWidth, $boxHeight);

        // --- 4. CLEANUP ---
        @unlink($tempPdfPath);
        @unlink($tempImagePath);

        // --- 5. OUTPUT ---
        return $finalPdf->Output('dokumen_anti_copy_paraf_hd.pdf', 'I');
    }

    /**
     * Membuat PDF dasar berisi Template + Teks (TANPA PARAF)
     * Tujuannya agar ini yang di-convert jadi gambar (background).
     */
    private function createVectorPdf($outputPath, $data)
    {
        // MENGGUNAKAN CLASS BARU (MasterPdf)
        $pdf = new MasterPdf();

        $templatePath = Storage::disk('master_gambar')->path('1/5/gambar-utama.pdf');

        if (file_exists($templatePath)) {
            $pdf->setSourceFile($templatePath);
            $templateId = $pdf->importPage(1);
            $pdf->AddPage();
            $pdf->useTemplate($templateId, ['adjustPageSize' => true]);
        } else {
            $pdf->AddPage();
        }

        $pdf->SetFont('arial', '', 4.3);
        $pdf->setFontSpacing(0);

        // --- TULIS SEMUA TEXT DI SINI ---
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
        $pdf->Cell(68.654, 0, $data['judul_gambar_1'], 0, 0, 'C');

        $pdf->SetXY(208.573, 163.897);
        $pdf->Write(0, $data['deskripsi_optional']);

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

        // CATATAN: KITA TIDAK MEMANGGIL placeSignature DI SINI
        // Agar paraf tidak ikut dikonversi jadi gambar (tidak buram).

        $pdf->Output($outputPath, 'F');
    }

    private function placeSignature(MasterPdf &$pdf, $imagePath, $boxX, $boxY, $boxWidth, $boxHeight)
    {
        if (!file_exists($imagePath)) return;
        list($originalWidth, $originalHeight) = getimagesize($imagePath);
        if ($originalHeight == 0) return;

        $newWidth = ($originalWidth / $originalHeight) * $boxHeight;
        $newHeight = $boxHeight;
        $calculatedX = $boxX + (($boxWidth - $newWidth) / 2);

        $pdf->Image($imagePath, $calculatedX, $boxY, $newWidth, $newHeight, 'PNG');
    }
}
