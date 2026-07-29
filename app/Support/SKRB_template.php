<?php

namespace App\Support;

use Exception;
use Carbon\Carbon;
use App\Models\SkrbSetting;

class SKRB_template extends MasterPdf
{
    /**
     * Constructor menginisiasi dokumen berorientasi Portrait A4.
     */
    public function __construct()
    {
        // Panggil MasterPdf dengan orientasi Portrait ('P'), unit 'mm', ukuran 'A4'
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false, false);
        
        $this->SetMargins(20, 20, 20);
        $this->SetAutoPageBreak(true, 15);
    }

    /**
     * Helper untuk mengubah string alamat penerima yang diinput admin dalam 1 baris
     * menjadi beberapa baris (newline \n) secara otomatis berdasarkan kata kunci (Cq., Jl., Di, dll).
     */
    public function formatRecipientAddress(string $rawText): string
    {
        // Jika sudah ada baris baru dari textarea/input, gunakan apa adanya
        if (str_contains($rawText, "\n")) {
            return trim($rawText);
        }

        $text = trim($rawText);

        // Sisipkan baris baru di sebelum "Cq."
        $text = preg_replace('/\s+(Cq\.)/i', "\n$1", $text);

        // Sisipkan baris baru di sebelum "Jl." atau "Jalan"
        $text = preg_replace('/\s+(Jl\.|Jalan\s+)/i', "\n$1", $text);

        // Sisipkan baris baru di sebelum "Di " atau "di " yang diikuti nama kota/lokasi (misal: Di Jakarta)
        $text = preg_replace('/\s+(Di\s+[A-Z])/i', "\n$0", $text);
        
        // Bersihkan spasi ganda setelah replacement
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        
        return implode("\n", $lines);
    }

    /**
     * Fungsi utama untuk men-generate dokumen PDF SKRB.
     * Menerima array $data berisikan parameter dokumen.
     */
    public function generate(array $data = []): self
    {
        $this->AddPage('P', 'A4');

        // 1. Import dan pasang background KOP Surat (untuk pengujian dari local storage)
        $kopPath = $data['kop_path'] ?? "C:\\Nova\\Rekayasa\\Modul\\KOP.pdf";
        if (file_exists($kopPath)) {
            try {
                $pageCount = $this->setSourceFile($kopPath);
                $templateId = $this->importPage(1);
                // Tempatkan KOP dari koordinat 0,0 sepenuh halaman A4 (210 x 297 mm)
                $this->useTemplate($templateId, 0, 0, 210, 297);
            } catch (Exception $e) {
                // Abaikan jika ada kegagalan membaca KOP agar proses pembutan PDF tetap berjalan
            }
        }

        // Set font Arial ukuran 10.5
        $this->SetFont('arial', '', 10.5);
        $this->SetTextColor(0, 0, 0);

        // Atur posisi Y awal di bawah area KOP Surat (sekitar 48 mm dari atas)
        $startY = $data['start_y'] ?? 50;
        $this->SetY($startY);

        // === PERSIAPAN VARIABEL UTAMA ===
        // <jenis_pengajuan> validasi / default value "Varian"
        $jenisPengajuan = !empty($data['jenis_pengajuan']) ? $data['jenis_pengajuan'] : 'Varian';
        
        // Penomoran & Tanggal
        $nomorSurat   = $data['nomor_surat'] ?? '15/VCLAS-SKRB/V/2026';
        $lampiran     = $data['lampiran'] ?? '-';
        
        // Format Tanggal Hari Ini (dalam bahasa Indonesia, contoh: Jakarta, 29 Juli 2026)
        if (!empty($data['tanggal'])) {
            $tanggalString = $data['tanggal'];
        } else {
            Carbon::setLocale('id');
            $today = Carbon::now();
            $bulanIndo = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];
            $tanggalString = "Jakarta, " . $today->day . ' ' . $bulanIndo[$today->month] . ' ' . $today->year;
        }

        // Identifikasi Penerima (Alamat Tujuan Surat) dari parameter atau dari tabel database skrb_settings
        $setting = SkrbSetting::first();
        $dbRecipient = $setting ? $setting->recipient_address : "Bapak Direktur Jendral Perhubungan Darat\nCq. Direktur Sarana dan Keselamatan\nTransportasi Jalan\nJl. Merdeka Barat No.8\nDi Jakarta";
        $rawRecipient = !empty($data['identifikasi_penerima']) ? $data['identifikasi_penerima'] : $dbRecipient;
        $recipientFormatted = $this->formatRecipientAddress($rawRecipient);

        // === BAGIAN 1: HEADER SURAT (KOLOM KIRI: NOMOR/LAMPIRAN/PERIHAL, KOLOM KANAN: TANGGAL/TUJUAN) ===
        
        // Baris 1: Nomor & Tanggal
        $this->SetX(20);
        $this->Cell(22, 5, 'Nomor', 0, 0, 'L');
        $this->Cell(4, 5, ':', 0, 0, 'L');
        $this->Cell(69, 5, $nomorSurat, 0, 0, 'L');
        
        $this->SetXY(115, $this->GetY());
        $this->Cell(75, 5, $tanggalString, 0, 1, 'L');

        // Baris 2: Lampiran
        $this->SetX(20);
        $this->Cell(22, 5, 'Lampiran', 0, 0, 'L');
        $this->Cell(4, 5, ':', 0, 0, 'L');
        $this->Cell(69, 5, $lampiran, 0, 1, 'L');

        // Baris 3: Perihal & Alamat Tujuan Surat
        $perihalStartY = $this->GetY() + 1;
        $this->SetXY(20, $perihalStartY);
        $this->Cell(22, 5, 'Perihal', 0, 0, 'L');
        $this->Cell(4, 5, ':', 0, 0, 'L');
        
        // Teks Perihal (MultiCell Kolom Kiri)
        $perihalText = "Permohonan {$jenisPengajuan}\nRancang Bangun dan Rekayasa\nKendaraan Bermotor";
        $this->MultiCell(69, 5, $perihalText, 0, 'L');
        $leftMaxY = $this->GetY();

        // Kolom Kanan: Kepada Yth & Alamat Penerima
        $this->SetXY(115, $perihalStartY + 4); // Turun sedikit sejajar baris ke-2 perihal
        $this->Cell(75, 5, 'Kepada Yth.', 0, 1, 'L');
        $this->SetX(115);
        $this->MultiCell(75, 5, $recipientFormatted, 0, 'L');
        $rightMaxY = $this->GetY();

        // Ambil titik terendah dari kedua kolom untuk melanjutkan baris ke bawah
        $nextY = max($leftMaxY, $rightMaxY) + 6;
        $this->SetY($nextY);


        // === BAGIAN 2: SALAM PEMBUKA & DATA PIHAK PEMOHON ===
        $this->SetX(20);
        $this->Cell(170, 5, 'Dengan Hormat,', 0, 1, 'L');
        
        $this->SetX(20);
        $this->Cell(170, 5, '1. Yang bertandatangan dibawah ini :', 0, 1, 'L');

        // Indentasi Data Pemohon (X = 26)
        $nama       = $data['nama_pemohon'] ?? 'Drs. Sularjo';
        $jabatan    = $data['jabatan'] ?? 'Direktur';
        $alamat     = $data['alamat'] ?? "Ruko Avenue D-8-165 Jakarta Garden City\nJalan Raya Cakung Cilincing KM. 0,5 Jakarta";
        $bidang     = $data['bidang_usaha'] ?? 'Karoseri Kendaraan Bermotor';

        $this->printIdentitasRow('Nama', $nama);
        $this->printIdentitasRow('Jabatan', $jabatan);
        $this->printIdentitasRow('Alamat', $alamat, true); // Mendukung multi-line
        $this->printIdentitasRow('Bidang Usaha', $bidang);

        $this->Ln(3); // Jarak antar paragraf


        // === BAGIAN 3: MENGAJUKAN PERMOHONAN ===
        $this->SetX(20);
        $introKendaraan = "Mengajukan Permohonan {$jenisPengajuan} rancang bangun dan rekayasa kendaraan bermotor :";
        $this->MultiCell(170, 5, $introKendaraan, 0, 'L');

        // Spesifikasi Kendaraan & Daftar Varian / Standar
        $merekTipe  = $data['merek_tipe'] ?? 'SUZUKI TIPE AEV415P CL TYPE 2 (4x2) M/T (VARIAN KEENAM)';
        $jenis      = $data['jenis'] ?? 'Mobil Angkutan Barang';
        $peruntukan = $data['peruntukan'] ?? 'Mobil Bak Muatan Tertutup - Box Fiber';

        // Item a, b, c
        $this->printKendaraanRow('a. ', 'Merk / Type', $merekTipe);
        $this->printKendaraanRow('b. ', 'Jenis', $jenis);
        $this->printKendaraanRow('c. ', 'Peruntukan', $peruntukan);

        // Item d (Standar & Varian 1, 2, 3...)
        $varianList = $data['varian_list'] ?? [
            ['prefix' => 'd. ', 'label' => 'STANDAR',  'value' => 'Pintu Belakang Double Swing'],
            ['prefix' => '   ', 'label' => 'VARIAN 1', 'value' => 'Pintu Belakang Single Swing'],
            ['prefix' => '   ', 'label' => 'VARIAN 2', 'value' => 'Pintu Belakang Single Swing, Pintu Samping LH Single Swing'],
            ['prefix' => '   ', 'label' => 'VARIAN 3', 'value' => '-'],
        ];

        foreach ($varianList as $v) {
            $prefix = $v['prefix'] ?? '   ';
            $label  = $v['label'] ?? '';
            $value  = $v['value'] ?? '-';
            $this->printKendaraanRow($prefix, $label, $value);
        }

        $this->Ln(3);


        // === BAGIAN 4: KELENGKAPAN PERMOHONAN ===
        $this->SetX(20);
        $this->Cell(170, 5, 'Sebagai Kelengkapan Permohonan ini, bersama ini kami lampirkan :', 0, 1, 'L');

        // Daftar lampiran berindentasi
        $this->printLampiranItem('a.', 'Data Umum Perusahaan');
        $this->printLampiranItem('b.', 'Gambar Teknik');
        $this->printLampiranItem('c.', 'Spesifikasi Teknis Kendaraan');
        
        $skrbNo = $data['skrb_no'] ?? 'KP-DJPD 3253 TAHUN 2026';
        $this->printLampiranItem('d.', "Foto Copy SKRB No    : {$skrbNo}");

        $this->Ln(4);


        // === BAGIAN 5: PENUTUP ===
        $this->SetX(20);
        $penutupText = "Demikian permohonan kami dan atas perhatian Bapak Direktur, kami ucapkan terimakasih.";
        $this->MultiCell(170, 5, $penutupText, 0, 'L');

        return $this;
    }

    /**
     * Helper mencetak baris identitas (Nama, Jabatan, Alamat, dll) dengan indentasi rapi.
     */
    private function printIdentitasRow(string $label, string $value, bool $isMultiLine = false): void
    {
        $this->SetX(26); // Indentasi ke kanan
        $this->Cell(28, 5, $label, 0, 0, 'L');
        $this->Cell(4, 5, ':', 0, 0, 'L');
        
        if ($isMultiLine && str_contains($value, "\n")) {
            $this->MultiCell(112, 5, $value, 0, 'L');
        } else {
            $this->Cell(112, 5, $value, 0, 1, 'L');
        }
    }

    /**
     * Helper mencetak baris spesifikasi kendaraan dengan format prefix huruf (a., b., c., d.).
     */
    private function printKendaraanRow(string $prefix, string $label, string $value): void
    {
        $this->SetX(24);
        $this->Cell(6, 5, $prefix, 0, 0, 'L');
        $this->Cell(26, 5, $label, 0, 0, 'L');
        $this->Cell(4, 5, ':', 0, 0, 'L');
        
        // Gunakan MultiCell jika value panjang
        if (strlen($value) > 55 || str_contains($value, "\n")) {
            $this->MultiCell(110, 5, $value, 0, 'L');
        } else {
            $this->Cell(110, 5, $value, 0, 1, 'L');
        }
    }

    /**
     * Helper mencetak item daftar lampiran (a., b., c., d.).
     */
    private function printLampiranItem(string $prefix, string $text): void
    {
        $this->SetX(24);
        $this->Cell(6, 5, $prefix, 0, 0, 'L');
        $this->Cell(140, 5, $text, 0, 1, 'L');
    }
}
