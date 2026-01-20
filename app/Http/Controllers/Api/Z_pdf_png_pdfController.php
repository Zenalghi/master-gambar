<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Facades\Storage;

class Z_pdf_png_pdfController extends Controller
{
    public function generateUncopyablePdf(Request $request)
    {
        // --- 1. GENERATE PDF VEKTOR (SEMENTARA) ---
        // Kita buat dulu PDF normalnya seperti biasa, tapi simpan ke file sementara (temp)

        $tempPdfPath = storage_path('app/public/temp_vector_' . time() . '.pdf');
        $this->createVectorPdf($tempPdfPath);

        // --- 2. KONVERSI PDF -> PNG (RASTERIZE) ---
        // Menggunakan Ghostscript untuk mengubah PDF menjadi Gambar High Quality (300 DPI)
        // Output image path
        $tempImagePath = storage_path('app/public/temp_image_' . time() . '.png');

        // Command untuk Windows (Laragon biasanya perlu path full ke gswin64c jika belum di env path)
        // Pastikan Ghostscript terinstall. Jika di linux pakai 'gs'.
        // -r300 artinya resolusi 300 DPI (biar teks tetap tajam saat di-zoom dikit)
        $command = "gswin64c -dSAFER -dBATCH -dNOPAUSE -sDEVICE=png16m -r300 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=\"{$tempImagePath}\" \"{$tempPdfPath}\"";

        // Eksekusi command cmd
        exec($command, $output, $returnVar);

        // Cek jika konversi gagal
        if (!file_exists($tempImagePath) || $returnVar !== 0) {
            return response()->json([
                'message' => 'Gagal mengonversi PDF ke Gambar. Pastikan Ghostscript terinstall.',
                'debug' => $output
            ], 500);
        }

        // --- 3. BUAT PDF FINAL (GAMBAR SAJA) ---
        // Buat PDF baru yang isinya cuma gambar tadi ditempel full page
        $finalPdf = new Fpdi('L', 'mm', 'A4');
        $finalPdf->setPrintHeader(false);
        $finalPdf->setPrintFooter(false);
        $finalPdf->SetAutoPageBreak(false, 0);

        $finalPdf->AddPage();

        // Tempel gambar. 
        // 0, 0 = koordinat pojok kiri atas
        // 297 = Lebar A4 Landscape
        // 210 = Tinggi A4 Landscape
        $finalPdf->Image($tempImagePath, 0, 0, 297, 210, 'PNG');

        // --- 4. BERSIH-BERSIH FILE SEMENTARA ---
        // Hapus file temp pdf dan temp image agar storage tidak penuh
        @unlink($tempPdfPath);
        @unlink($tempImagePath);

        // --- 5. OUTPUT ---
        return $finalPdf->Output('dokumen_anti_copy.pdf', 'I'); // 'I' untuk preview di browser
    }

    /**
     * Fungsi ini isinya SAMA PERSIS dengan logic Z_DrawingController Anda.
     * Bedanya: Outputnya disimpan ke File ($path), bukan ke Browser ('D'/'I').
     */
    private function createVectorPdf($outputPath)
    {
        $data = [
            'digambar' => 'Deni',
            'diperiksa' => 'Umardani',
            'disetujui' => 'Yohannes',
            'tanggal' => '01.02.22',
            'judul_gambar_1' => 'GAMBAR TAMPAK UTAMA STANDAR',
            'karoseri' => 'PT SURYA INDAH PRATAMA',
            'no_halaman' => '01',
            'total_halaman' => '13',
            // Pastikan path ini valid di komputer Anda
            'signature_path' => 'C:/laragon/www/master-gambar/storage/app/master/user/1/1.png',
            'signature_path_2' => 'C:/laragon/www/master-gambar/storage/app/master/user/3/3.png',
            'signature_path_3' => 'C:/laragon/www/master-gambar/storage/app/master/customer/3/3.png',
            'deskripsi_optional' => 'Contoh deskripsi tambahan jika diperlukan',
        ];

        $pdf = new Fpdi('L', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);

        // Template Path (Sesuaikan dengan path lokal Anda)
        $templatePath = 'C:/laragon/www/master-gambar/storage/app/master/gambar/1/5/gambar-utama.pdf';

        if (file_exists($templatePath)) {
            $pdf->setSourceFile($templatePath);
            $templateId = $pdf->importPage(1);
            $pdf->AddPage();
            $pdf->useTemplate($templateId, ['adjustPageSize' => true]);
        } else {
            // Fallback jika template tidak ada (untuk testing)
            $pdf->AddPage();
        }

        $pdf->SetFont('arial', '', 4.3);
        $pdf->setFontSpacing(0);

        // Koordinat Text (Copy paste dari kode Anda)
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

        // Paraf Logic
        $boxX = 238.59;
        $boxWidth = 4.529;
        $boxHeight = 2.074;

        $this->placeSignature($pdf, $data['signature_path'], $boxX, 175.062, $boxWidth, $boxHeight);
        $this->placeSignature($pdf, $data['signature_path_2'], $boxX, 177.625, $boxWidth, $boxHeight);
        $this->placeSignature($pdf, $data['signature_path_3'], $boxX, 180.188, $boxWidth, $boxHeight);

        // SAVE KE FILE (Bukan Output ke Browser)
        $pdf->Output($outputPath, 'F');
    }

    private function placeSignature(Fpdi &$pdf, $imagePath, $boxX, $boxY, $boxWidth, $boxHeight)
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
