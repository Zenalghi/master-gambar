<?php

namespace App\Support;

use setasign\Fpdi\Tcpdf\Fpdi;

class MasterPdf extends Fpdi
{
    public function __construct($orientation = 'L', $unit = 'mm', $size = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false)
    {
        // 1. Panggil constructor bawaan TCPDF/FPDI
        parent::__construct($orientation, $unit, $size, $unicode, $encoding, $diskcache, $pdfa);

        // 2. Setting Default (agar tidak perlu diketik ulang di setiap Controller)
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(false, 0);

        // 3. Daftarkan Font Arial dari resources/fonts/
        // TCPDF secara otomatis akan mendeteksi file arial.z dan arial.ctg.z di folder yang sama
        $fontPath = resource_path('fonts/arial.php');
        if (file_exists($fontPath)) {
            $this->AddFont('arial', '', $fontPath);
        }
    }
}
