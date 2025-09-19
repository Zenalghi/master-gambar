<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use setasign\Fpdi\Tcpdf\Fpdi;

class DrawingController extends Controller
{
    public function generatePdf(Request $request)
    {
        // --- DATA INPUT ---
        $data = [
            'digambar' => 'Ridho',
            'diperiksa' => 'Umardani',
            'disetujui' => 'Harsoyo',
            'tanggal' => '01.02.22',
            'catatan' => '- Model Bak Besi 5 Way',
            'judul_gambar_1' => 'GAMBAR TAMPAK UTAMA STANDAR',
            'judul_gambar_2' => 'MEREK MITSUBISHI TIPE CANTER FE 74 N (4X2) M/T',
            'judul_gambar_3' => 'SEBAGAI MOBIL BARANG BAK MUATAN TERBUKA ( BAK BESI )',
            'karoseri' => 'CV SURYA INDAH PRATAMA',
            'no_halaman' => '01',
            'total_halaman' => '14',
            'signature_path' => storage_path('app/signatures/ridho_ttd.png'),
            'signature_path_2' => storage_path('app/signatures/umardani_ttd.png'),
            'signature_path_3' => storage_path('app/signatures/harsoyo_ttd.png')
        ];

        // 1. Inisialisasi PDF dengan orientasi LANDSCAPE
        $pdf = new Fpdi('L', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // 2. Impor halaman dari template
        $templatePath = storage_path('app/templates/pdf_kosong.pdf');
        $pdf->setSourceFile($templatePath);
        $templateId = $pdf->importPage(1);

        $pdf->AddPage();
        $pdf->useTemplate($templateId, ['adjustPageSize' => true]);

        $pdf->SetFont('arial', '', 8); // Gunakan 'arial'

        // --- Koordinat diestimasi untuk A4 Landscape (297x210 mm) ---
        $pdf->SetXY(202, 185.5);
        $pdf->Write(0, $data['digambar']);
        // ... (sisa kode SetXY dan Write lainnya tetap sama) ...
        $pdf->SetXY(202, 189.5);
        $pdf->Write(0, $data['diperiksa']);
        $pdf->SetXY(202, 193.5);
        $pdf->Write(0, $data['disetujui']);

        $pdf->SetXY(237, 185.5);
        $pdf->Write(0, $data['tanggal']);
        $pdf->SetXY(237, 189.5);
        $pdf->Write(0, $data['tanggal']);
        $pdf->SetXY(237, 193.5);
        $pdf->Write(0, $data['tanggal']);

        // $pdf->SetFont($arial, 'B', 8); 
        $pdf->SetXY(257, 191);
        $pdf->Write(0, $data['judul_gambar_1']);
        $pdf->SetXY(257, 195);
        $pdf->Write(0, $data['judul_gambar_2']);
        $pdf->SetXY(257, 199);
        $pdf->Write(0, $data['judul_gambar_3']);
        $pdf->SetXY(202, 206);
        $pdf->Write(0, $data['karoseri']);

        // $pdf->SetFont($arial, '', 8); 
        $pdf->SetXY(257, 181.5);
        $pdf->Write(0, $data['catatan']);
        $pdf->SetXY(280, 206);
        $pdf->Write(0, $data['no_halaman']);
        $pdf->SetXY(280, 212.5);
        $pdf->Write(0, $data['no_halaman'] . ' / ' . $data['total_halaman']);

        // 5. Menempatkan Gambar Tanda Tangan
        $pdf->Image($data['signature_path'], 219, 182, 15, 15, 'PNG');
        $pdf->Image($data['signature_path_2'], 219, 186, 15, 15, 'PNG');
        $pdf->Image($data['signature_path_3'], 219, 190, 15, 15, 'PNG');

        // 6. Kirim PDF ke browser
        return $pdf->Output('hasil.pdf', 'I');
    }
}
