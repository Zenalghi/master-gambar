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
            'sortBy' => 'nullable|string|in:id,type_chassis,jenis_tipe,created_at,updated_at',
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
}
