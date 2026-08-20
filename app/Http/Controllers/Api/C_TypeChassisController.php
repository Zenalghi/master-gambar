<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTypeChassisRequest;
use App\Http\Requests\UpdateTypeChassisRequest;
use App\Models\CTypeChassis;
use App\Models\IGambarKelistrikan;
use App\Models\MasterData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\DB;

class C_TypeChassisController extends Controller
{
    /**
     * Menampilkan semua data, diurutkan berdasarkan ID.
     * Memuat relasi merk dan typeEngine induknya.
     */
    public function index(Request $request)
    {
        // 1. Validasi parameter
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:50,100',
            'sortBy' => 'nullable|string|in:id,type_chassis,merek_dagang,jenis_tipe,created_at,updated_at',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        $perPage = $validated['perPage'] ?? 50;
        $sortBy = $validated['sortBy'] ?? 'updated_at'; // Default sort
        $sortDirection = $validated['sortDirection'] ?? 'desc'; // Default direction
        $search = $validated['search'] ?? '';

        $query = CTypeChassis::query();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('type_chassis', 'like', "%{$search}%")
                    ->orWhere('merek_dagang', 'like', "%{$search}%")
                    ->orWhere('jenis_tipe', 'like', "%{$search}%")
                    ->orWhere('created_at', 'like', "%{$search}%")
                    ->orWhere('updated_at', 'like', "%{$search}%");
            });
        }

        // 4. Terapkan sorting
        $query->orderBy($sortBy, $sortDirection);

        // 5. Lakukan paginasi
        return $query->paginate($perPage);
    }

    // --- FITUR BARU: List data sampah ---
    public function trash(Request $request)
    {
        $search = $request->input('search', '');

        return CTypeChassis::onlyTrashed()
            ->where(function ($q) use ($search) {
                $q->where('type_chassis', 'like', "%{$search}%")
                    ->orWhere('merek_dagang', 'like', "%{$search}%")
                    ->orWhere('jenis_tipe', 'like', "%{$search}%");
            })
            ->orderBy('deleted_at', 'desc')
            ->get();
    }

    // --- FITUR BARU: Kosongkan Sampah ---
    public function emptyTrash()
    {
        $trashedItems = CTypeChassis::onlyTrashed()->get();
        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($trashedItems as $item) {
            // Cek apakah dipakai di Master Data
            if (MasterData::where('c_type_chassis_id', $item->id)->exists()) {
                $skippedCount++;
                continue;
            }

            // Cek apakah punya file kelistrikan
            if ($item->fileKelistrikan()->exists()) {
                $skippedCount++;
                continue;
            }

            Storage::disk('sut-pdf')->deleteDirectory((string) $item->id);
            $item->forceDelete();
            $deletedCount++;
        }

        return response()->json([
            'message' => "Berhasil menghapus $deletedCount data. $skippedCount data dilewati karena masih digunakan.",
            'deleted' => $deletedCount,
            'skipped' => $skippedCount
        ]);
    }
    /**
     * Menyimpan data baru dengan ID komposit otomatis.
     */
    public function store(StoreTypeChassisRequest $request)
    {
        $validated = $request->validated();
        $typeChassis = CTypeChassis::create([
            'type_chassis' => $validated['type_chassis'],
            'merek_dagang' => $validated['merek_dagang'] ?? null,
            'jenis_tipe' => $validated['jenis_tipe'] ?? null,
        ]);

        if ($request->hasFile('sut_file')) {
            $folder = "sut-{$typeChassis->id}";
            $now = now()->format('Ymd-His');
            $filename = "{$typeChassis->id}-sut-{$now}.pdf";
            $path = $request->file('sut_file')->storeAs($folder, $filename, 'sut-pdf');
            $typeChassis->sut_file = $path;
            $typeChassis->save();
        }

        return response()->json($typeChassis, 201);
    }

    public function show(CTypeChassis $typeChassis)
    {
        return $typeChassis;
    }

    public function update(UpdateTypeChassisRequest $request, CTypeChassis $typeChassis)
    {
        $typeChassis->type_chassis = $request->input('type_chassis', $typeChassis->type_chassis);
        if ($request->has('merek_dagang')) {
            $typeChassis->merek_dagang = $request->input('merek_dagang');
        }
        if ($request->has('jenis_tipe')) {
            $typeChassis->jenis_tipe = $request->input('jenis_tipe');
        }
        
        $disk = Storage::disk('sut-pdf');
        $folder = "sut-{$typeChassis->id}";

        if ($request->input('remove_sut_file') === '1' || $request->input('remove_sut_pdf') === '1') {
            if ($typeChassis->sut_file && $disk->exists($typeChassis->sut_file)) {
                $disk->delete($typeChassis->sut_file);
            }
            $typeChassis->sut_file = null;
        }

        if ($request->hasFile('sut_file')) {
            if ($typeChassis->sut_file && $disk->exists($typeChassis->sut_file)) {
                $disk->delete($typeChassis->sut_file);
            }
            $now = now()->format('Ymd-His');
            $filename = "{$typeChassis->id}-sut-{$now}.pdf";
            $path = $request->file('sut_file')->storeAs($folder, $filename, 'sut-pdf');
            $typeChassis->sut_file = $path;
        }

        $typeChassis->save();
        return response()->json($typeChassis);
    }

    public function viewSutPdf(CTypeChassis $typeChassis)
    {
        if (!$typeChassis->sut_file || !Storage::disk('sut-pdf')->exists($typeChassis->sut_file)) {
            return response()->json(['message' => 'File PDF SUT tidak ditemukan.'], 404);
        }

        return Storage::disk('sut-pdf')->response($typeChassis->sut_file, null, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function destroy(CTypeChassis $typeChassis)
    {
        // Soft Delete tidak perlu cek relasi yang ketat
        $typeChassis->delete();
        return response()->json(null, 204);
    }

    // --- FITUR RESTORE ---
    public function restore($id)
    {
        $typeChassis = CTypeChassis::onlyTrashed()->findOrFail($id);
        $typeChassis->restore();
        return response()->json($typeChassis);
    }

    // --- FITUR FORCE DELETE ---
    public function forceDelete($id)
    {
        // Cek apakah data ini dipakai di Master Data
        if (MasterData::where('c_type_chassis_id', $id)->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Data tidak bisa dihapus permanen karena masih digunakan di Master Data (Kombinasi).']
            ]);
        }
        $chassis = CTypeChassis::onlyTrashed()->find($id);
        if ($chassis->fileKelistrikan()->exists()) {
            throw ValidationException::withMessages([
                'general' => ['Data tidak bisa dihapus permanen karena masih memiliki file kelistrikan terkait.']
            ]);
        }

        $typeChassis = CTypeChassis::onlyTrashed()->findOrFail($id);
        $sutFolder = 'sut-' . $typeChassis->id;
        if (Storage::disk('sut-pdf')->exists($sutFolder)) {
            Storage::disk('sut-pdf')->deleteDirectory($sutFolder);
        }
        if (Storage::disk('sut-pdf')->exists((string) $typeChassis->id)) {
            Storage::disk('sut-pdf')->deleteDirectory((string) $typeChassis->id);
        }
        $typeChassis->forceDelete();

        return response()->json(null, 204);
    }

    /**
     * Export Master Type Chassis ke Excel
     */
    public function exportExcel()
    {
        $chassis = CTypeChassis::withTrashed()->orderBy('type_chassis', 'asc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Master Type Chassis');

        $headers = [
            'ID Type Chassis',
            'Type Chassis',
            'Nomor SUT',
            'Merek Dagang',
            'Jenis Tipe',
            'Status'
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
        $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        $rowNum = 2;
        foreach ($chassis as $c) {
            $status = $c->trashed() ? 'Di-Recycle Bin (Dihapus)' : 'Aktif';
            if ($c->sut_file) {
                $status .= ', Ada File SUT';
            } else {
                $status .= ', Belum Ada File SUT';
            }

            $sheet->fromArray([
                $c->id,
                $c->type_chassis,
                $c->nomor_sut,
                $c->merek_dagang,
                $c->jenis_tipe,
                $status
            ], null, 'A' . $rowNum);
            $rowNum++;
        }

        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'Master_Type_Chassis_' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Import Master Type Chassis dari Excel
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

        $updatedCount = 0;
        $createdCount = 0;
        $failedRows = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                // Pastikan baris tidak kosong semua
                $isEmpty = true;
                foreach ($row as $cell) {
                    if (trim((string)$cell) !== '') {
                        $isEmpty = false;
                        break;
                    }
                }
                if ($isEmpty) continue;

                $id = trim((string)($row[0] ?? ''));
                $typeChassis = trim((string)($row[1] ?? ''));
                $nomorSut = trim((string)($row[2] ?? '')) ?: null;
                $merekDagang = trim((string)($row[3] ?? '')) ?: null;
                $jenisTipe = trim((string)($row[4] ?? '')) ?: null;

                if (empty($typeChassis)) {
                    $failedRows[] = [
                        'baris' => $index + 2,
                        'data' => $typeChassis,
                        'alasan' => 'Type Chassis tidak boleh kosong.'
                    ];
                    continue;
                }

                // Cek unik Nomor SUT (termasuk yang di recycle bin)
                if ($nomorSut) {
                    $sutQuery = CTypeChassis::withTrashed()->where('nomor_sut', $nomorSut);
                    if ($id) $sutQuery->where('id', '!=', $id);
                    $existingSut = $sutQuery->first();
                    if ($existingSut) {
                        $isDeleted = $existingSut->trashed();
                        $hasSutFile = !empty($existingSut->sut_file);
                        $statusStr = $isDeleted ? 'Di-Recycle Bin (Dihapus)' : 'Aktif';
                        $statusStr .= $hasSutFile ? ', Ada File SUT' : ', Belum Ada File SUT';

                        $failedRows[] = [
                            'baris' => $index + 2,
                            'data' => "$typeChassis ($nomorSut)",
                            'alasan' => 'Nomor SUT sudah digunakan.',
                            'status' => $statusStr,
                            'is_deleted' => $isDeleted,
                            'conflict_id' => $existingSut->id
                        ];
                        continue;
                    }
                }

                // Cek unik kombinasi type_chassis + nomor_sut + merek_dagang + jenis_tipe (termasuk yang di recycle bin)
                $comboQuery = CTypeChassis::withTrashed()->where('type_chassis', $typeChassis);
                
                if ($nomorSut === null) {
                    $comboQuery->whereNull('nomor_sut');
                } else {
                    $comboQuery->where('nomor_sut', $nomorSut);
                }

                if ($merekDagang === null) {
                    $comboQuery->whereNull('merek_dagang');
                } else {
                    $comboQuery->where('merek_dagang', $merekDagang);
                }
                
                if ($jenisTipe === null) {
                    $comboQuery->whereNull('jenis_tipe');
                } else {
                    $comboQuery->where('jenis_tipe', $jenisTipe);
                }

                if ($id) {
                    $comboQuery->where('id', '!=', $id);
                }

                $existingCombo = $comboQuery->first();
                if ($existingCombo) {
                    $isDeleted = $existingCombo->trashed();
                    $hasSutFile = !empty($existingCombo->sut_file);
                    $statusStr = $isDeleted ? 'Di-Recycle Bin (Dihapus)' : 'Aktif';
                    $statusStr .= $hasSutFile ? ', Ada File SUT' : ', Belum Ada File SUT';

                    $failedRows[] = [
                        'baris' => $index + 2,
                        'data' => "$typeChassis - $nomorSut - $merekDagang - $jenisTipe",
                        'alasan' => 'Kombinasi Type Chassis, Nomor SUT, Merek Dagang, dan Jenis Tipe sudah ada.',
                        'status' => $statusStr,
                        'is_deleted' => $isDeleted,
                        'conflict_id' => $existingCombo->id
                    ];
                    continue;
                }

                if (!empty($id)) {
                    $chassis = CTypeChassis::withTrashed()->find($id);
                    if ($chassis) {
                        $chassis->type_chassis = $typeChassis;
                        $chassis->nomor_sut = $nomorSut;
                        $chassis->merek_dagang = $merekDagang;
                        $chassis->jenis_tipe = $jenisTipe;
                        $chassis->save();
                        $updatedCount++;
                    } else {
                        $failedRows[] = [
                            'baris' => $index + 2,
                            'data' => $typeChassis,
                            'alasan' => "ID $id tidak ditemukan di database."
                        ];
                    }
                } else {
                    CTypeChassis::create([
                        'type_chassis' => $typeChassis,
                        'nomor_sut' => $nomorSut,
                        'merek_dagang' => $merekDagang,
                        'jenis_tipe' => $jenisTipe,
                    ]);
                    $createdCount++;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan sistem saat import: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => "Import berhasil: $createdCount data baru, $updatedCount data diupdate.",
            'created' => $createdCount,
            'updated' => $updatedCount,
            'failed' => count($failedRows),
            'failed_rows' => $failedRows
        ]);
    }
}
