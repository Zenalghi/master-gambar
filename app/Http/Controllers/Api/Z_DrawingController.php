<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use setasign\Fpdi\Tcpdf\Fpdi;

class Z_DrawingController extends Controller
{
    public function generatePdf(Request $request)
    {
        // --- DATA INPUT ---
        $data = [
            'digambar' => 'Ridho',
            'diperiksa' => 'Umardani',
            'disetujui' => 'Harsoyo',
            'tanggal' => '01.02.22',
            // 'catatan' => '- Model Bak Besi 5 Way',
            'judul_gambar_1' => 'GAMBAR TAMPAK UTAMA STANDAR',
            // 'judul_gambar_2' => 'MEREK MITSUBISHI TIPE CANTER FE 74 N (4X2) M/T',
            // 'judul_gambar_3' => 'SEBAGAI MOBIL BARANG BAK MUATAN TERBUKA ( BAK BESI )',
            'karoseri' => 'PT SURYA INDAH PRATAMA',
            'no_halaman' => '01',
            'total_halaman' => '13',
            'signature_path' => storage_path('app/signatures/ridho_ttd.png'),
            'signature_path_2' => storage_path('app/signatures/umardani_ttd.png'),
            'signature_path_3' => storage_path('app/signatures/harsoyo_ttd.png')
        ];

        // 1. Inisialisasi PDF dengan orientasi LANDSCAPE
        $pdf = new Fpdi('L', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);

        // 2. Impor halaman dari template
        $templatePath = 'D:/_Master/BEFORE/pdf_kosong.pdf';
        $pdf->setSourceFile($templatePath);
        $templateId = $pdf->importPage(1);

        $pdf->AddPage();
        $pdf->useTemplate($templateId, ['adjustPageSize' => true]);

        $pdf->SetFont('arial', '', 4.3); // Gunakan 'arial'
        $pdf->setFontSpacing(0);

        // $pdf->SetXY(215.979, 175.205);
        // $pdf->Write(0, 'DIGAMBAR');
        // // ... (sisa kode SetXY dan Write lainnya tetap sama) ...
        // $pdf->SetXY(215.979, 177.768);
        // $pdf->Write(0, 'DIPERIKSA');
        // $pdf->SetXY(215.979, 180.331);
        // $pdf->Write(0, 'DISETUJUI');
        // --- Koordinat diestimasi untuk A4 Landscape (297x210 mm) ---
        $pdf->SetXY(225.862, 175.205);
        $pdf->Write(0, $data['digambar']);
        // ... (sisa kode SetXY dan Write lainnya tetap sama) ...
        $pdf->SetXY(225.862, 177.768);
        $pdf->Write(0, $data['diperiksa']);
        $pdf->SetXY(225.862, 180.331);
        $pdf->Write(0, $data['disetujui']);


        $pdf->SetXY(243.53, 175.205);
        // $pdf->Write(0, $data['tanggal']);
        $pdf->Cell(8.377, 0, $data['tanggal'], 0, 0, 'C');
        $pdf->SetXY(243.53, 177.768);
        $pdf->Cell(8.377, 0, $data['tanggal'], 0, 0, 'C');
        $pdf->SetXY(243.53, 180.331);
        $pdf->Cell(8.377, 0, $data['tanggal'], 0, 0, 'C');

        // $pdf->SetFont($arial, 'B', 8); 
        $pdf->SetFont('arial', '', 6); // Gunakan 'arial'
        $pdf->setFontSpacing(-0.09);
        $pdf->SetXY(215.686, 183.252);
        $pdf->Cell(68.654, 0, $data['judul_gambar_1'], 0, 0, 'C');
        // $pdf->SetXY(215.686, 185.934);
        // $pdf->Cell(68.654, 0, $data['judul_gambar_2'], 0, 0, 'C');
        // $pdf->SetXY(257, 199);
        // $pdf->Write(0, $data['judul_gambar_3']);
        $pdf->SetFont('arial', '', 8); // Gunakan 'arial'
        $pdf->setFontSpacing(0);

        $pdf->SetXY(217.004, 194.679);
        $pdf->Cell(44.149, 0, $data['karoseri'], 0, 0, 'C');
        // $pdf->Write(0, $data['karoseri']);

        // $pdf->SetFont($arial, '', 8); 
        // $pdf->SetXY(257, 181.5);
        // $pdf->Write(0, $data['catatan']);
        $pdf->SetFont('arial', '', 7); // Gunakan 'arial'
        $pdf->SetXY(274.381, 194.118);
        $pdf->Write(0, $data['no_halaman']);
        $pdf->SetFont('arial', '', 5); // Gunakan 'arial'
        $pdf->SetXY(275.342, 198.311);
        $pdf->Cell(10.139, 0, $data['no_halaman'] . ' / ' . $data['total_halaman'], 0, 0, 'C');
        // $pdf->Write(0, $data['no_halaman'] . ' / ' . $data['total_halaman']);

        // 5. Menempatkan Gambar Tanda Tangan
        $pdf->Image($data['signature_path'], 237.527, 175.205, 15, 15, 'PNG');
        $pdf->Image($data['signature_path_2'], 237.527, 177.768, 4.529, 2.074, 'PNG');
        $pdf->Image($data['signature_path_3'], 237.527, 180.331, 4.529, 2.074, 'PNG');

        // 6. Kirim PDF ke browser
        return $pdf->Output('hasil.pdf', 'D');
    }
}
