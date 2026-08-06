<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MMasterVarian;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\EVarianBody;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class M_MasterVarianController extends Controller
{
    /**
     * Menampilkan data tabel Master Varian (Paginated)
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:50,100',
            'sortBy' => 'nullable|string',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
            'd_jenis_kendaraan_id' => 'nullable|integer'
        ]);

        $perPage = $validated['perPage'] ?? 50;
        $sortBy = $validated['sortBy'] ?? 'jenis_kendaraan'; // Default ubah ke jenis_kendaraan
        $sortDirection = $validated['sortDirection'] ?? 'asc'; // Default ubah ke asc
        $search = $validated['search'] ?? '';

        $query = MMasterVarian::query()
            ->join('d_jenis_kendaraan', 'm_master_varians.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->select('m_master_varians.*')
            ->with('jenisKendaraan');

        if ($request->filled('d_jenis_kendaraan_id')) {
            $query->where('m_master_varians.d_jenis_kendaraan_id', $request->d_jenis_kendaraan_id);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('m_master_varians.nama_varian', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('m_master_varians.id', 'like', "%{$search}%");
            });
        }

        // --- LOGIKA MULTI-SORTING (BEST PRACTICE) ---
        $sortColumn = match ($sortBy) {
            'id' => 'm_master_varians.id',
            'nama_varian' => 'm_master_varians.nama_varian',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'created_at' => 'm_master_varians.created_at',
            'updated_at' => 'm_master_varians.updated_at',
            default => 'd_jenis_kendaraan.jenis_kendaraan',
        };

        if ($sortColumn === 'd_jenis_kendaraan.jenis_kendaraan') {
            // Jika sort berdasarkan jenis kendaraan, sort keduanya berurutan
            $query->orderBy('d_jenis_kendaraan.jenis_kendaraan', $sortDirection)
                ->orderBy('m_master_varians.nama_varian', 'asc');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        return $query->paginate($perPage);
    }

    /**
     * Simpan Data Baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'd_jenis_kendaraan_id' => 'required|integer|exists:d_jenis_kendaraan,id',
            'nama_varian' => 'required|string|max:255'
        ]);

        // Cek duplikasi (Optional tapi disarankan)
        $exists = MMasterVarian::where('d_jenis_kendaraan_id', $validated['d_jenis_kendaraan_id'])
            ->whereRaw('LOWER(nama_varian) = ?', [strtolower(trim($validated['nama_varian']))])
            ->first();

        if ($exists) {
            return response()->json(['message' => 'Varian ini sudah ada di jenis kendaraan tersebut.'], 422);
        }

        $masterVarian = MMasterVarian::create($validated);

        // Sinkronkan ke riwayat di e_varian_body jika ada yang case-nya berbeda (misal sebelumnya huruf kapital)
        $namaVarian = trim($validated['nama_varian']);
        EVarianBody::withTrashed()
            ->whereRaw('LOWER(varian_body) = ?', [strtolower($namaVarian)])
            ->whereRaw('BINARY varian_body != ?', [$namaVarian])
            ->update(['varian_body' => $namaVarian]);

        return response()->json($masterVarian->load('jenisKendaraan'), 201);
    }

    /**
     * Update Data
     */
    public function update(Request $request, MMasterVarian $masterVarian)
    {
        $validated = $request->validate([
            'd_jenis_kendaraan_id' => 'required|integer|exists:d_jenis_kendaraan,id',
            'nama_varian' => 'required|string|max:255'
        ]);

        $oldNamaVarian = $masterVarian->nama_varian;
        $namaVarian = trim($validated['nama_varian']);
        $masterVarian->update($validated);

        // Sinkronkan ke riwayat transaksi di e_varian_body (termasuk jika user hanya mengubah case atau memperbaiki ketikan)
        $targetLower = strtolower($oldNamaVarian ?: $namaVarian);
        $newLower = strtolower($namaVarian);

        EVarianBody::withTrashed()
            ->where(function($q) use ($targetLower, $newLower) {
                $q->whereRaw('LOWER(varian_body) = ?', [$targetLower])
                  ->orWhereRaw('LOWER(varian_body) = ?', [$newLower]);
            })
            ->whereRaw('BINARY varian_body != ?', [$namaVarian])
            ->update(['varian_body' => $namaVarian]);

        return response()->json($masterVarian->fresh()->load('jenisKendaraan'));
    }

    /**
     * Hapus Data (Soft Delete)
     */
    public function destroy(MMasterVarian $masterVarian)
    {
        $masterVarian->delete();
        return response()->noContent();
    }

    /**
     * --- FUNGSI KHUSUS UNTUK DROPDOWN MULTI-SELECT ---
     * Mengambil daftar Varian Body berdasarkan ID Jenis Kendaraan
     */
    public function getOptionsByJenisKendaraan(Request $request, $jenisKendaraanId)
    {
        $search = $request->query('search', '');

        $query = MMasterVarian::where('d_jenis_kendaraan_id', $jenisKendaraanId);

        if (!empty($search)) {
            $query->where('nama_varian', 'like', "%{$search}%");
        }

        $varians = $query->orderBy('nama_varian', 'asc')->get();

        // Format response menjadi [{id: 1, name: 'Varian A'}, ...] 
        // agar persis dengan yang diharapkan oleh OptionItem.fromJson() di Flutter.
        $formatted = $varians->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->nama_varian
            ];
        });

        return response()->json($formatted);
    }

    // --- RECYCLE BIN ---
    public function trash(Request $request)
    {
        $search = $request->input('search', '');
        $query = MMasterVarian::onlyTrashed()->with('jenisKendaraan');
        if (!empty($search)) {
            $query->where('nama_varian', 'like', "%{$search}%");
        }
        // Sama dengan tabel utama, kita sort abjad
        return $query->orderBy('nama_varian', 'asc')->get();
    }

    // FITUR BARU: Empty Trash
    public function emptyTrash()
    {
        $trashedItems = MMasterVarian::onlyTrashed()->get();
        $deletedCount = 0;

        foreach ($trashedItems as $item) {
            $item->forceDelete();
            $deletedCount++;
        }

        return response()->json([
            'message' => "Berhasil menghapus permanen $deletedCount data varian.",
            'deleted' => $deletedCount,
            'skipped' => 0
        ]);
    }

    public function restore($id)
    {
        $masterVarian = MMasterVarian::onlyTrashed()->findOrFail($id);
        $masterVarian->restore();
        return response()->json($masterVarian);
    }

    public function forceDelete($id)
    {
        $masterVarian = MMasterVarian::onlyTrashed()->findOrFail($id);
        $masterVarian->forceDelete();
        return response()->json(null, 204);
    }

    /**
     * Export Master Varian & Orphan Data dari EVarianBody ke Excel (.xlsx)
     */
    public function exportExcel()
    {
        $masterVarians = MMasterVarian::with('jenisKendaraan')->withTrashed()
            ->join('d_jenis_kendaraan', 'm_master_varians.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->select('m_master_varians.*')
            ->orderBy('m_master_varians.nama_varian', 'asc')
            ->get();

        $rows = [];
        $existingKeys = [];

        foreach ($masterVarians as $mv) {
            $jenisId = $mv->d_jenis_kendaraan_id;
            $namaJenis = $mv->jenisKendaraan ? $mv->jenisKendaraan->jenis_kendaraan : '';
            $namaVarian = trim($mv->nama_varian);
            $key = $jenisId . '-' . strtolower($namaVarian);
            $existingKeys[$key] = true;
            
            $status = $mv->trashed() ? 'Di-Recycle Bin (Dihapus)' : 'Aktif';

            $rows[] = [
                $mv->id,
                $jenisId,
                $namaJenis,
                $namaVarian,
                $status
            ];
        }

        // Cari orphan data di e_varian_body (varian jadul dari transaksi yang belum didaftarkan di master)
        $varianBodies = EVarianBody::with(['masterData.jenisKendaraan'])->withTrashed()->get();
        foreach ($varianBodies as $vb) {
            if (!$vb->masterData || !$vb->masterData->d_jenis_kendaraan_id) {
                continue;
            }
            $jenisId = $vb->masterData->d_jenis_kendaraan_id;
            $namaJenis = $vb->masterData->jenisKendaraan ? $vb->masterData->jenisKendaraan->jenis_kendaraan : '';
            $namaVarian = trim($vb->varian_body);
            if (empty($namaVarian)) continue;

            $key = $jenisId . '-' . strtolower($namaVarian);

            if (!isset($existingKeys[$key])) {
                $existingKeys[$key] = true;
                $status = $vb->trashed()
                    ? 'Di-Recycle Bin (Varian Lama dari Riwayat Dihapus)'
                    : 'Aktif (Varian Lama dari Riwayat Belum Terdaftar di Master)';
                $rows[] = [
                    '', // ID kosong agar dibuat otomatis saat import
                    $jenisId,
                    $namaJenis,
                    $namaVarian,
                    $status
                ];
            }
        }

        // Urutkan seluruh baris berdasarkan Nama Varian Body secara abjad A-Z (case-insensitive)
        usort($rows, function ($a, $b) {
            return strcasecmp(trim((string)$a[3]), trim((string)$b[3]));
        });

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Master Varian Body');

        $headers = [
            'ID Master (JANGAN DIUBAH)',
            'ID Jenis Kendaraan',
            'Nama Jenis Kendaraan',
            'Nama Varian Body (Ubah Casing Di Sini)',
            'Status Data (Informasi)'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2E7D32'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        $rowNum = 2;
        foreach ($rows as $r) {
            $sheet->fromArray($r, null, 'A' . $rowNum);
            $rowNum++;
        }

        foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'Master_Varian_' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Import Excel & Sinkronisasi ke EVarianBody (tanpa uppercase)
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Lepas baris header
        array_shift($rows);

        $updatedMaster = 0;
        $createdMaster = 0;
        $syncedTransactions = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, &$updatedMaster, &$createdMaster, &$syncedTransactions, &$skipped) {
            foreach ($rows as $row) {
                if (empty(array_filter($row))) continue;

                $id = trim((string)($row[0] ?? ''));
                $jenisKendaraanId = (int) trim((string)($row[1] ?? 0));
                $namaVarian = trim((string)($row[3] ?? ''));

                if ($jenisKendaraanId <= 0 || empty($namaVarian)) {
                    $skipped++;
                    continue;
                }

                $masterVarian = null;
                $oldNamaVarian = null;

                if (!empty($id) && is_numeric($id)) {
                    $masterVarian = MMasterVarian::withTrashed()->find((int) $id);
                    if ($masterVarian) {
                        $oldNamaVarian = $masterVarian->nama_varian;
                        if ($oldNamaVarian !== $namaVarian) {
                            $masterVarian->nama_varian = $namaVarian;
                            $masterVarian->save();
                            $updatedMaster++;
                        }
                    }
                }

                if (!$masterVarian) {
                    $existingMaster = MMasterVarian::withTrashed()->where('d_jenis_kendaraan_id', $jenisKendaraanId)
                        ->whereRaw('LOWER(nama_varian) = ?', [strtolower($namaVarian)])
                        ->first();
                    
                    if (!$existingMaster) {
                        $masterVarian = MMasterVarian::create([
                            'd_jenis_kendaraan_id' => $jenisKendaraanId,
                            'nama_varian' => $namaVarian,
                        ]);
                        $createdMaster++;
                        $oldNamaVarian = $namaVarian;
                    } else {
                        $oldNamaVarian = $existingMaster->nama_varian;
                        if ($existingMaster->nama_varian !== $namaVarian) {
                            $existingMaster->nama_varian = $namaVarian;
                            $existingMaster->save();
                            $updatedMaster++;
                        }
                    }
                }

                // Sinkronkan ke riwayat transaksi di e_varian_body
                $targetLower = strtolower($oldNamaVarian ?: $namaVarian);
                $newLower = strtolower($namaVarian);

                $affected = EVarianBody::withTrashed()
                    ->where(function($q) use ($targetLower, $newLower) {
                        $q->whereRaw('LOWER(varian_body) = ?', [$targetLower])
                          ->orWhereRaw('LOWER(varian_body) = ?', [$newLower]);
                    })
                    ->whereRaw('BINARY varian_body != ?', [$namaVarian])
                    ->update(['varian_body' => $namaVarian]);

                $syncedTransactions += $affected;
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => "Import Excel berhasil! $updatedMaster Varian diupdate, $createdMaster Varian baru ditambahkan, dan $syncedTransactions data riwayat transaksi diselaraskan.",
            'stats' => [
                'updated_master' => $updatedMaster,
                'created_master' => $createdMaster,
                'synced_transactions' => $syncedTransactions,
                'skipped' => $skipped,
            ]
        ]);
    }
}
