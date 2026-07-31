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

        $tahomaPath = resource_path('fonts/tahoma.php');
        if (file_exists($tahomaPath)) {
            $this->AddFont('tahoma', '', $tahomaPath);
            $this->AddFont('tahoma', 'B', $tahomaPath);
            $this->AddFont('tahoma', 'I', $tahomaPath);
            $this->AddFont('tahoma', 'BI', $tahomaPath);
        }

        $arialPath = resource_path('fonts/arial.php');
        if (file_exists($arialPath)) {
            $this->AddFont('arial', '', $arialPath);
            $this->AddFont('arial', 'B', $arialPath);
            $this->AddFont('arial', 'I', $arialPath);
            $this->AddFont('arial', 'BI', $arialPath);
        }
    }
}
