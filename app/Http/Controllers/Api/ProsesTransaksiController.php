<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EVarianBody;
use App\Models\GGambarUtama;
use App\Models\HGambarOptional;
use App\Models\IGambarKelistrikan;
use App\Models\JJudulGambar;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ProsesTransaksiController extends Controller
{
    public function saveDraft(Request $request, Transaksi $transaksi)
    {
        $validated = $request->validate([
            'pemeriksa_id' => 'required|exists:users,id',
            'jumlah_gambar' => 'required|integer|min:1|max:4',
            'data_gambar_utama' => 'required|array',
            'deskripsi_optional' => 'nullable|string',
            'ordered_independent_ids' => 'nullable|array',
            'ordered_independent_ids.*' => 'integer',
            'i_gambar_kelistrikan_id' => 'nullable|integer|exists:i_gambar_kelistrikan,id',
        ]);

        $detail = TransaksiDetail::updateOrCreate(
            ['transaksi_id' => $transaksi->id],
            [
                'pemeriksa_id' => $request->pemeriksa_id,
                'jumlah_gambar' => $request->jumlah_gambar,
                'data_gambar_utama' => $request->data_gambar_utama,
                'ordered_independent_ids' => $request->ordered_independent_ids ?? [],
                'deskripsi_optional' => $request->deskripsi_optional,
                'i_gambar_kelistrikan_id' => $request->input('i_gambar_kelistrikan_id'),
            ]
        );
        $detail->touch();

        return response()->json(['message' => 'Draft berhasil disimpan', 'detail' => $detail]);
    }

    public function proses(Request $request, Transaksi $transaksi)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $varianCount = count($request->input('varian_body_ids', []));

        $validated = $request->validate([
            'pemeriksa_id' => 'required|exists:users,id',
            'varian_body_ids' => 'required|array|min:1|max:20',
            'varian_body_ids.*' => 'required|integer|exists:e_varian_body,id',
            'judul_gambar_ids' => ['required', 'array', "size:$varianCount"],
            'judul_gambar_ids.*' => 'required|integer|exists:j_judul_gambars,id',
            'h_gambar_optional_ids' => 'nullable|array',
            'h_gambar_optional_ids.*' => 'integer|exists:h_gambar_optional,id',
            'i_gambar_kelistrikan_id' => 'nullable|integer|exists:i_gambar_kelistrikan,id',
            'aksi' => 'required|in:preview,proses',
            'preview_page' => 'nullable|integer|min:1',
            'ordered_independent_ids' => 'nullable|array',
            'ordered_independent_ids.*' => 'integer',
            'deskripsi_optional' => 'nullable|string|max:255',
        ]);

        // Logic Data Gambar Utama
        $dataGambarUtamaJSON = [];
        $inputVarian = $request->input('varian_body_ids', []);
        $inputJudul = $request->input('judul_gambar_ids', []);

        foreach ($inputVarian as $index => $varianId) {
            $dataGambarUtamaJSON[] = [
                'varian_id' => $varianId,
                'judul_id' => $inputJudul[$index] ?? null
            ];
        }

        // Simpan Transaksi Detail
        TransaksiDetail::updateOrCreate(
            ['transaksi_id' => $transaksi->id],
            [
                'pemeriksa_id' => $request->pemeriksa_id,
                'jumlah_gambar' => count($dataGambarUtamaJSON),
                'data_gambar_utama' => $dataGambarUtamaJSON,
                'ordered_independent_ids' => $validated['ordered_independent_ids'] ?? [],
                'deskripsi_optional' => $request->deskripsi_optional,
                'i_gambar_kelistrikan_id' => $request->i_gambar_kelistrikan_id,
            ]
        );
        $transaksi->detail->touch();

        // Load Relasi
        $transaksi->load([
            'user',
            'customer',
            'fPengajuan',
            'masterData.typeEngine',
            'masterData.merk',
            'masterData.typeChassis',
            'masterData.jenisKendaraan'
        ]);

        $pemeriksa = User::find($validated['pemeriksa_id']);
        $masterData = $transaksi->masterData;
        $jenisPengajuan = strtoupper($transaksi->fPengajuan->jenis_pengajuan);
        $isGambarTU = ($jenisPengajuan === 'GAMBAR TU');

        $jobsUtama = [];
        $jobsTerurai = [];
        $jobsKontruksi = [];
        $jobsPaket = [];
        $jobsIndependen = [];
        $jobsKelistrikan = [];

        // LOOPING VARIAN (Logic Sama)
        if (!empty($validated['varian_body_ids'])) {
            foreach ($validated['varian_body_ids'] as $index => $varian_id) {
                $varianBody = EVarianBody::find($varian_id);
                $gambarUtamaData = GGambarUtama::with('gambarOptionals')->where('e_varian_body_id', $varian_id)->first();
                $jenisJudul = JJudulGambar::find($validated['judul_gambar_ids'][$index]);

                if ($gambarUtamaData && $jenisJudul) {
                    $jobsUtama[] = [
                        'type' => 'standard',
                        'title' => 'GAMBAR TAMPAK UTAMA ' . $jenisJudul->nama_judul,
                        'varian' => $varianBody->varian_body,
                        'source_pdf' => $gambarUtamaData->path_gambar_utama,
                        'deskripsi_optional' => $validated['deskripsi_optional'] ?? null
                    ];

                    if ($isGambarTU) continue;

                    $jobsTerurai[] = [
                        'type' => 'standard',
                        'title' => 'GAMBAR TAMPAK TERURAI ' . $jenisJudul->nama_judul,
                        'varian' => $varianBody->varian_body,
                        'source_pdf' => $gambarUtamaData->path_gambar_terurai,
                        'deskripsi_optional' => null
                    ];

                    $jobsKontruksi[] = [
                        'type' => 'standard',
                        'title' => 'GAMBAR DETAIL KONTRUKSI ' . $jenisJudul->nama_judul,
                        'varian' => $varianBody->varian_body,
                        'source_pdf' => $gambarUtamaData->path_gambar_kontruksi,
                        'deskripsi_optional' => null
                    ];

                    foreach ($gambarUtamaData->gambarOptionals as $gambarPaket) {
                        if ($gambarPaket->tipe === 'paket' && in_array($gambarPaket->id, $validated['h_gambar_optional_ids'] ?? [])) {
                            $judulLengkap = ($gambarPaket->deskripsi ?: 'GAMBAR OPTIONAL PAKET') . ' ' . $jenisJudul->nama_judul;
                            $jobsPaket[] = [
                                'type' => 'standard',
                                'title' => $judulLengkap,
                                'varian' => '',
                                'source_pdf' => $gambarPaket->path_gambar_optional,
                                'deskripsi_optional' => null
                            ];
                        }
                    }
                }
            }
        }

        if (!$isGambarTU) {
            // INDEPENDEN
            if ($request->has('ordered_independent_ids') && !empty($request->ordered_independent_ids)) {
                $orderedIds = $request->ordered_independent_ids;
                $gambarIndependen = HGambarOptional::whereIn('id', $orderedIds)->where('tipe', 'independen')->get();
                $idMap = array_flip($orderedIds);
                $gambarIndependen = $gambarIndependen->sortBy(function ($model) use ($idMap) {
                    return $idMap[$model->id] ?? 999;
                });

                foreach ($gambarIndependen as $gambarOptional) {
                    $jobsIndependen[] = [
                        'type' => 'standard',
                        'title' => $gambarOptional->deskripsi ?: 'GAMBAR OPTIONAL',
                        'varian' => '',
                        'source_pdf' => $gambarOptional->path_gambar_optional,
                        'deskripsi_optional' => null
                    ];
                }
            } else if (!empty($validated['varian_body_ids'])) {
                $masterDataIds = EVarianBody::whereIn('id', $validated['varian_body_ids'])->pluck('master_data_id')->unique();
                $gambarIndependen = HGambarOptional::whereIn('master_data_id', $masterDataIds)->where('tipe', 'independen')->get();
                $urutanVarian = array_flip($validated['varian_body_ids']);
                $gambarIndependen = $gambarIndependen->sortBy(function ($model) use ($urutanVarian) {
                    return $urutanVarian[$model->e_varian_body_id] ?? 999;
                });

                foreach ($gambarIndependen as $gambarOptional) {
                    $jobsIndependen[] = [
                        'type' => 'standard',
                        'title' => $gambarOptional->deskripsi ?: 'GAMBAR OPTIONAL',
                        'varian' => '',
                        'source_pdf' => $gambarOptional->path_gambar_optional,
                        'deskripsi_optional' => null
                    ];
                }
            }

            // KELISTRIKAN
            if (isset($validated['i_gambar_kelistrikan_id'])) {
                $gambarKelistrikan = IGambarKelistrikan::with('fileKelistrikan')->find($validated['i_gambar_kelistrikan_id']);
                if ($gambarKelistrikan && $gambarKelistrikan->fileKelistrikan) {
                    $jobsKelistrikan[] = [
                        'type' => 'kelistrikan',
                        'title' => $gambarKelistrikan->deskripsi ?: 'GAMBAR KELISTRIKAN',
                        'jenis_kendaraan' => $masterData->jenisKendaraan->jenis_kendaraan ?? '',
                        'varian' => '',
                        'source_pdf' => $gambarKelistrikan->fileKelistrikan->path_file,
                        'deskripsi_optional' => null
                    ];
                }
            }
        }

        $drawingJobs = array_merge($jobsUtama, $jobsTerurai, $jobsKontruksi, $jobsPaket, $jobsIndependen, $jobsKelistrikan);

        $pageCounter = 1;
        foreach ($drawingJobs as &$job) {
            $job['page'] = $pageCounter++;
        }
        unset($job);

        $totalHalaman = count($drawingJobs);

        // --- EKSEKUSI (MODIFIED) ---
        if ($validated['aksi'] === 'preview') {
            $previewPage = $validated['preview_page'] ?? 1;
            $previewIndex = $previewPage - 1;

            if (isset($drawingJobs[$previewIndex])) {
                $job = $drawingJobs[$previewIndex];

                if (!Storage::disk('master_gambar')->exists($job['source_pdf'])) {
                    return response()->json(['message' => 'File PDF sumber tidak ditemukan: ' . $job['source_pdf']], 404);
                }

                $pdfData = $this->buildPdfData($job, $transaksi, $pemeriksa, $totalHalaman);

                // MENGGUNAKAN LOGIKA BARU: RASTERIZE + OVERLAY
                $pdfContent = $this->generateUncopyablePdfPage($pdfData);

                if (ob_get_length()) ob_clean();
                return response($pdfContent, 200)->header('Content-Type', 'application/pdf');
            } else {
                return response()->json(['message' => 'Halaman preview tidak ditemukan.'], 404);
            }
        } else {
            try {
                $generatedPdfs = [];
                foreach ($drawingJobs as $job) {
                    if (!Storage::disk('master_gambar')->exists($job['source_pdf'])) continue;

                    $pdfData = $this->buildPdfData($job, $transaksi, $pemeriksa, $totalHalaman);

                    // MENGGUNAKAN LOGIKA BARU DI SINI JUGA
                    $pdfContent = $this->generateUncopyablePdfPage($pdfData);

                    $generatedPdfs[] = ['name' => $job['page'] . '.pdf', 'content' => $pdfContent];
                }

                if (empty($generatedPdfs)) {
                    return response()->json(['message' => 'Tidak ada gambar yang berhasil diproses.'], 404);
                }

                $zipFileName = sprintf('%s (%s) %s_%s %s (%s).zip', $transaksi->user->username, $transaksi->fPengajuan->jenis_pengajuan, $transaksi->customer->nama_pt, $masterData->merk->merk, $masterData->typeChassis->type_chassis, $masterData->jenisKendaraan->jenis_kendaraan);
                $cleanZipFileName = Str::slug(pathinfo($zipFileName, PATHINFO_FILENAME)) . '.zip';

                $zip = new \ZipArchive();
                $tempZipPath = tempnam(sys_get_temp_dir(), 'gambar_');
                $zip->open($tempZipPath, \ZipArchive::CREATE);
                foreach ($generatedPdfs as $pdfFile) {
                    $zip->addFromString($pdfFile['name'], $pdfFile['content']);
                }
                $zip->close();

                if (ob_get_length()) ob_clean();
                return response()->download($tempZipPath, $cleanZipFileName)->deleteFileAfterSend(true);
            } catch (\Exception $e) {
                Log::error("Error Proses PDF: " . $e->getMessage());
                return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
            }
        }
    }

    // --- Helper Data Builder (Tetap Sama) ---
    private function buildPdfData(array $job, Transaksi $transaksi, User $pemeriksa, int $totalHalaman): array
    {
        return [
            'type' => $job['type'],
            'digambar' => $transaksi->user->name,
            'diperiksa' => $pemeriksa->name,
            'disetujui' => $transaksi->customer->pj,
            'tanggal' => now()->format('d.m.y'),
            'judul_gambar' => $job['title'],
            'catatan' => $job['varian'],
            'jenis_kendaraan' => $job['jenis_kendaraan'] ?? '',
            'karoseri' => $transaksi->customer->nama_pt,
            'no_halaman' => str_pad($job['page'], 2, '0', STR_PAD_LEFT),
            'total_halaman' => str_pad($totalHalaman, 2, '0', STR_PAD_LEFT),
            'source_pdf_path' => $job['source_pdf'],
            'signature_path' => $transaksi->user->signature ? Storage::disk('user_paraf')->path($transaksi->user->signature) : null,
            'signature_path_2' => $pemeriksa->signature ? Storage::disk('user_paraf')->path($pemeriksa->signature) : null,
            'signature_path_3' => $transaksi->customer->signature_pj ? Storage::disk('customer_paraf')->path($transaksi->customer->signature_pj) : null,
            'deskripsi_optional' => $job['deskripsi_optional'],
        ];
    }

    // --- FUNGSI UTAMA BARU: RASTERIZE PDF ---
    private function generateUncopyablePdfPage(array $data): string
    {
        // 1. Setup Temporary Files
        $tempDir = sys_get_temp_dir();
        $uniqueId = uniqid('pdf_secure_', true);
        $tempPdfVector = $tempDir . DIRECTORY_SEPARATOR . $uniqueId . '_vector.pdf';
        $tempPng = $tempDir . DIRECTORY_SEPARATOR . $uniqueId . '.png';

        // 2. Generate Vector PDF (HANYA TEKS + TEMPLATE, TANPA PARAF)
        $this->createVectorPdfFile($tempPdfVector, $data);

        // 3. Rasterize using Ghostscript (Vector PDF -> PNG)
        // Command Windows (Laragon)
        $gsCmd = sprintf(
            'gswin64c -dSAFER -dBATCH -dNOPAUSE -sDEVICE=png16m -r300 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile="%s" "%s"',
            $tempPng,
            $tempPdfVector
        );
        exec($gsCmd, $output, $returnVar);

        // Fallback jika GS gagal: Return Vector PDF (agar user tetap dapat file)
        if (!file_exists($tempPng) || $returnVar !== 0) {
            Log::warning("Ghostscript conversion failed. Returning vector PDF. Error: " . implode(" ", $output));
            $content = file_get_contents($tempPdfVector); // Kembalikan vector jika gagal
            @unlink($tempPdfVector);
            return $content;
        }

        // 4. Create Final PDF (Background Image + Signatures Overlay)
        $finalPdf = new Fpdi('L', 'mm', 'A4');
        $finalPdf->setPrintHeader(false);
        $finalPdf->setPrintFooter(false);
        $finalPdf->SetAutoPageBreak(false, 0);
        $finalPdf->AddPage();

        // Layer 1: Background Image (Anti Copy)
        $finalPdf->Image($tempPng, 0, 0, 297, 210, 'PNG');

        // Layer 2: Signatures (High Quality Overlay)
        $boxX = 238.59;
        $boxWidth = 4.529;
        $boxHeight = 2.074;
        $this->placeSignature($finalPdf, $data['signature_path'], $boxX, 175.062, $boxWidth, $boxHeight);
        $this->placeSignature($finalPdf, $data['signature_path_2'], $boxX, 177.625, $boxWidth, $boxHeight);
        $this->placeSignature($finalPdf, $data['signature_path_3'], $boxX, 180.188, $boxWidth, $boxHeight);

        // 5. Output Content
        $content = $finalPdf->Output('doc.pdf', 'S');

        // 6. Cleanup
        @unlink($tempPdfVector);
        @unlink($tempPng);

        return $content;
    }

    // --- HELPER: Buat PDF Vektor Sementara (Tanpa Paraf) ---
    private function createVectorPdfFile($outputPath, $data)
    {
        $pdf = new Fpdi('L', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);

        $templatePath = Storage::disk('master_gambar')->path($data['source_pdf_path']);

        if (!file_exists($templatePath)) {
            // Error handling minimal untuk vector file
            $pdf->AddPage();
            $pdf->SetFont('arial', '', 12);
            $pdf->Text(10, 10, 'File not found');
            $pdf->Output($outputPath, 'F');
            return;
        }

        $pdf->setSourceFile($templatePath);
        $tplId = $pdf->importPage(1);
        $pdf->AddPage();
        $pdf->useTemplate($tplId, ['adjustPageSize' => true]);

        // LOGIKA PENULISAN TEKS (SAMA SEPERTI SEBELUMNYA)
        $pdf->SetFont('arial', '', 4.3);
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

        // CATATAN: KITA TIDAK MEMANGGIL placeSignature DI SINI (AGAR PARAF TIDAK DIRASTER)

        $text = $data['karoseri'];
        $cellWidth = 44.149;
        $fontSize = 8;
        $pdf->SetFont('arial', '', $fontSize);
        while ($pdf->GetStringWidth($text) > ($cellWidth - 1)) {
            $fontSize -= 0.1;
            $pdf->SetFont('arial', '', $fontSize);
            if ($fontSize < 5) break;
        }
        $pdf->SetXY(216.984, 194.679);
        $pdf->Cell($cellWidth, 0, $text, 0, 0, 'C');

        $pdf->SetFont('arial', '', 7);
        $pdf->SetXY(274.381, 194.118);
        $pdf->Write(0, $data['no_halaman']);
        $pdf->SetFont('arial', '', 5);
        $pdf->SetXY(275.342, 198.311);
        $pdf->Cell(10.139, 0, $data['no_halaman'] . ' / ' . $data['total_halaman'], 0, 0, 'C');

        if ($data['type'] === 'kelistrikan') {
            $finalText = sprintf('%s', $data['judul_gambar']);
            $maxWidth = 68.54;
            $fontSize = 6;
            $minFontSize = 3;
            $pdf->SetFont('arial', '', $fontSize);
            while ($pdf->GetStringWidth($finalText) > ($maxWidth - 1) && $fontSize > $minFontSize) {
                $fontSize -= 0.2;
                $pdf->SetFont('arial', '', $fontSize);
            }
            $pdf->SetXY(216.847, 188.632);
            $pdf->Cell($maxWidth, 0, $finalText, 0, 0, 'C');
        } else {
            $text = $data['judul_gambar'];
            $maxWidth = 68.54;
            $fontSize = 6;
            $minFontSize = 3;
            $pdf->SetFont('arial', '', $fontSize);
            $pdf->setFontSpacing(-0.09);
            while ($pdf->GetStringWidth($text) > $maxWidth && $fontSize > $minFontSize) {
                $fontSize -= 0.2;
                $pdf->SetFont('arial', '', $fontSize);
                $pdf->setFontSpacing(-0.09);
            }
            $pdf->SetXY(216.847, 183.252);
            $pdf->Cell($maxWidth, 0, $text, 0, 0, 'C');

            if (!empty($data['deskripsi_optional'])) {
                $pdf->SetFont('arial', '', 8);
                $pdf->SetXY(211.878, 161.858);
                $pdf->Write(0, $data['deskripsi_optional']);
            }
        }

        $pdf->Output($outputPath, 'F');
    }

    private function placeSignature(Fpdi &$pdf, $imagePath, $boxX, $boxY, $boxWidth, $boxHeight)
    {
        if (!$imagePath || !file_exists($imagePath)) return;
        list($w, $h) = getimagesize($imagePath);
        if ($h == 0) return;
        $newW = ($w / $h) * $boxHeight;
        $newH = $boxHeight;
        $posX = $boxX + (($boxWidth - $newW) / 2);
        $pdf->Image($imagePath, $posX, $boxY, $newW, $newH, 'PNG');
    }
}
