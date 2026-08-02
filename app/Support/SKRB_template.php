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

        // Margin Kiri & Atas presisi berdasarkan koordinat desain (13.531 mm)
        $this->SetMargins(13.531, 13.531, 13.531);
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

        // 1. Import KOP Surat dari path atau fallback lokal
        $kopPath = !empty($data['kop_path']) && file_exists($data['kop_path'])
            ? $data['kop_path']
            : "C:\\Nova\\Rekayasa\\Modul\\KOP.pdf";

        if (file_exists($kopPath)) {
            try {
                $pageCount = $this->setSourceFile($kopPath);
                $templateId = $this->importPage(1);
                $this->useTemplate($templateId, 0, 0, 210, 297);
            } catch (Exception $e) {
                // Biarkan lanjut jika KOP gagal dibaca
            }
        }

        // Set Font Tahoma 10.5pt
        $this->SetFont('tahoma', '', 10.5);
        $this->SetTextColor(0, 0, 0);

        // Koordinat Y awal sesuai pengukuran presisi (40 mm)
        $startY = $data['start_y'] ?? 40;
        $this->SetY($startY);

        // === PREPARASI DATA ===
        $jenisPengajuan = !empty($data['jenis_pengajuan']) ? $data['jenis_pengajuan'] : 'Varian';
        $nomorSurat     = $data['nomor_surat'] ?? '-';
        $lampiran       = $data['lampiran'] ?? '1 (Satu) Berkas';

        Carbon::setLocale('id');
        $today = Carbon::now();
        $bulanIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $dateOnly = $today->day . ' ' . $bulanIndo[$today->month] . ' ' . $today->year;

        $alamatPermohonan = trim((string)($data['alamat_permohonan'] ?? ''));
        $isTanggalError = empty($alamatPermohonan) || $alamatPermohonan === '-';

        // Identifikasi Penerima
        $setting = SkrbSetting::first();
        $dbRecipient = $setting ? $setting->recipient_address : "Bapak Direktur Jendral Perhubungan Darat\nCq. Direktur Sarana dan Keselamatan\nTransportasi Jalan\nJl. Merdeka Barat No.8\nDi Jakarta";
        $rawRecipient = !empty($data['identifikasi_penerima']) ? $data['identifikasi_penerima'] : $dbRecipient;
        $recipientFormatted = $this->formatRecipientAddress($rawRecipient);

        // === BAGIAN 1: HEADER SURAT ===
        $leftX  = 14.031;
        $rightX = 115.5;

        // Baris 1: Nomor & Tanggal
        $this->SetX($leftX);
        $this->Cell(25, 5, 'Nomor', 0, 0, 'L');
        $this->Cell(4, 5, ':', 0, 0, 'L');
        $this->Cell(65, 5, $nomorSurat, 0, 0, 'L');

        $this->SetXY($rightX, $startY);
        if ($isTanggalError && empty($data['tanggal'])) {
            $this->SetFont('tahoma', 'B', 10.5);
            $this->SetTextColor(255, 0, 0);
            $this->Cell(75, 5, 'Data Belum diisi, hubungi admin', 0, 1, 'L');
            $this->SetFont('tahoma', '', 10.5);
            $this->SetTextColor(0, 0, 0);
        } else {
            $tanggalString = !empty($data['tanggal']) ? $data['tanggal'] : ($alamatPermohonan . ', ' . $dateOnly);
            $this->Cell(75, 5, $tanggalString, 0, 1, 'L');
        }

        // Baris 2: Lampiran
        $this->SetX($leftX);
        $this->Cell(25, 5, 'Lampiran', 0, 0, 'L');
        $this->Cell(4, 5, ':', 0, 0, 'L');
        $this->Cell(65, 5, $lampiran, 0, 1, 'L');

        // Baris 3: Perihal & Tujuan Surat
        $perihalStartY = $this->GetY();
        $this->SetXY($leftX, $perihalStartY);
        $this->Cell(25, 5, 'Perihal', 0, 0, 'L');
        $this->Cell(4, 5, ':', 0, 0, 'L');

        $perihalText = "Permohonan {$jenisPengajuan}\nRancang Bangun dan Rekayasa\nKendaraan Bermotor";
        $this->MultiCell(65, 5, $perihalText, 0, 'L');
        $leftMaxY = $this->GetY();

        $this->SetXY($rightX, $perihalStartY);
        $this->Cell(75, 5, 'Kepada Yth.', 0, 1, 'L');
        $this->SetX($rightX);
        $this->MultiCell(75, 5, $recipientFormatted, 0, 'L');
        $rightMaxY = $this->GetY();

        $this->SetY(max($leftMaxY, $rightMaxY) + 2);


        // === BAGIAN 2: SALAM PEMBUKA & DATA PIHAK PEMOHON ===
        $this->SetX($leftX);
        $this->Cell(170, 5, 'Dengan Hormat,', 0, 1, 'L');

        $this->SetX($leftX);
        $this->Cell(170, 5, '1. Yang bertanda tangan di bawah ini:', 0, 1, 'L');

        $nama    = $data['nama_pemohon'] ?? null;
        $jabatan = $data['jabatan'] ?? null;
        $alamat  = $data['alamat'] ?? null;
        $bidang  = $data['bidang_usaha'] ?? null;

        $this->printIdentitasRow('Nama', $nama);
        $this->printIdentitasRow('Jabatan', $this->toTitleCase($jabatan));
        $this->printIdentitasRow('Alamat', $alamat, true);
        $this->printIdentitasRow('Bidang Usaha', $bidang);

        $this->Ln(2);


        // === BAGIAN 3: MENGAJUKAN PERMOHONAN ===
        $this->SetX($leftX);
        $introKendaraan = "Mengajukan permohonan {$jenisPengajuan} rancang bangun dan rekayasa kendaraan bermotor :";
        $this->MultiCell(175, 5, $introKendaraan, 0, 'L');

        $merekTipe  = $data['merek_tipe'] ?? null;
        $jenis      = $data['jenis'] ?? null;
        $peruntukan = $data['peruntukan'] ?? null;

        $this->printKendaraanRow('a. ', 'Merk / Tipe', $merekTipe);
        $this->printKendaraanRow('b. ', 'Jenis', $this->toTitleCase($jenis));
        $this->printKendaraanRow('c. ', 'Peruntukan', $this->toTitleCase($peruntukan));

        $varianList = !empty($data['varian_list']) ? $data['varian_list'] : [
            ['prefix' => 'd. ', 'label' => 'Varian Body', 'value' => null],
        ];

        foreach ($varianList as $v) {
            $prefix = $v['prefix'] ?? '   ';
            $label  = $this->toTitleCase($v['label'] ?? '') ?? '';
            $value  = $this->toTitleCase($v['value'] ?? null);
            $this->printKendaraanRow($prefix, $label, $value);
        }

        $this->Ln(9);


        // === BAGIAN 4: KELENGKAPAN PERMOHONAN ===
        $this->SetX($leftX);
        $this->Cell(175, 5, 'Sebagai kelengkapan permohonan ini, bersama ini kami lampirkan:', 0, 1, 'L');

        $this->printLampiranItem('a.', 'Data Umum Perusahaan');
        $this->printLampiranItem('b.', 'Gambar Teknik');
        $this->printLampiranItem('c.', 'Spesifikasi Teknik Kendaraan');

        if (!empty($data['foto_copy_skrb'])) {
            $this->printLampiranItem('d.', "Foto Copy SKRB No : {$data['foto_copy_skrb']}");
        }

        $this->Ln(3);


        // === BAGIAN 5: PENUTUP  ===
        $this->SetX($leftX);
        $penutupText = "Demikian permohonan kami dan atas perhatian Bapak Direktur, kami mengucapkan banyak terima kasih.";
        $this->MultiCell(175, 5, $penutupText, 0, 'L');

        $this->Ln(4);

        return $this;
    }

    /**
     * Helper mengubah string menjadi Kapital Setiap Kata (Title Case).
     */
    private function toTitleCase(?string $text): ?string
    {
        if (empty($text) || $text === '-' || str_contains(strtolower($text), 'data belum diisi')) {
            return $text;
        }
        return ucwords(strtolower($text));
    }

    /**
     * Helper mencetak baris identitas pemohon.
     */
    private function printIdentitasRow(string $label, ?string $value, bool $isMultiLine = false): void
    {
        $indentX = 19.052;
        $this->SetX($indentX);
        $this->Cell(36, 5, $label, 0, 0, 'L');
        $this->Cell(4, 5, ':', 0, 0, 'L');

        $val = trim((string)$value);
        $isError = empty($val) || $val === '-' || str_contains(strtolower($val), 'data belum diisi');

        if ($isError) {
            $val = 'Data Belum diisi, hubungi admin';
            $this->SetFont('tahoma', 'B', 10.5);
            $this->SetTextColor(255, 0, 0);
        }

        if ($isMultiLine || str_contains($val, "\n")) {
            $this->MultiCell(130, 5, $val, 0, 'L');
        } else {
            $this->Cell(130, 5, $val, 0, 1, 'L');
        }

        if ($isError) {
            $this->SetFont('tahoma', '', 10.5);
            $this->SetTextColor(0, 0, 0);
        }
    }

    /**
     * Helper mencetak baris spesifikasi kendaraan.
     */
    private function printKendaraanRow(string $prefix, string $label, ?string $value): void
    {
        $indentX = 19.052;
        $this->SetX($indentX);
        $this->Cell(6, 5, $prefix, 0, 0, 'L');
        $this->Cell(30, 5, $label, 0, 0, 'L');
        $this->Cell(4, 5, ':', 0, 0, 'L');

        $val = trim((string)$value);
        $isError = empty($val) || $val === '-' || str_contains(strtolower($val), 'data belum diisi');

        if ($isError) {
            $val = 'Data Belum diisi, hubungi admin';
            $this->SetFont('tahoma', 'B', 10.5);
            $this->SetTextColor(255, 0, 0);
        }

        if (strlen($val) > 50 || str_contains($val, "\n")) {
            $this->MultiCell(130, 5, $val, 0, 'L');
        } else {
            $this->Cell(130, 5, $val, 0, 1, 'L');
        }

        if ($isError) {
            $this->SetFont('tahoma', '', 10.5);
            $this->SetTextColor(0, 0, 0);
        }
    }

    /**
     * Helper mencetak item daftar lampiran.
     */
    private function printLampiranItem(string $prefix, string $text): void
    {
        $indentX = 19.052;
        $this->SetX($indentX);
        $this->Cell(6, 5, $prefix, 0, 0, 'L');
        $this->Cell(145, 5, $text, 0, 1, 'L');
    }
}
