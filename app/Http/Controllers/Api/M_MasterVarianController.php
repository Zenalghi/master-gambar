<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MMasterVarian;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        // Query join agar bisa nge-sort/search berdasarkan nama jenis kendaraan
        $query = MMasterVarian::query()
            ->join('d_jenis_kendaraan', 'm_master_varians.d_jenis_kendaraan_id', '=', 'd_jenis_kendaraan.id')
            ->select('m_master_varians.*')
            ->with('jenisKendaraan');

        // Filter Dropdown Master (Jika Dibutuhkan)
        if ($request->filled('d_jenis_kendaraan_id')) {
            $query->where('m_master_varians.d_jenis_kendaraan_id', $request->d_jenis_kendaraan_id);
        }

        // Fitur Search
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('m_master_varians.nama_varian', 'like', "%{$search}%")
                    ->orWhere('d_jenis_kendaraan.jenis_kendaraan', 'like', "%{$search}%")
                    ->orWhere('m_master_varians.id', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortColumn = match ($sortBy) {
            'id' => 'm_master_varians.id',
            'nama_varian' => 'm_master_varians.nama_varian',
            'jenis_kendaraan' => 'd_jenis_kendaraan.jenis_kendaraan',
            'created_at' => 'm_master_varians.created_at',
            'updated_at' => 'm_master_varians.updated_at',
            default => 'm_master_varians.updated_at',
        };

        $query->orderBy($sortColumn, $sortDirection);

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
            ->where('nama_varian', Str::upper($validated['nama_varian']))
            ->first();

        if ($exists) {
            return response()->json(['message' => 'Varian ini sudah ada di jenis kendaraan tersebut.'], 422);
        }

        $masterVarian = MMasterVarian::create($validated);
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

        $masterVarian->update($validated);
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
        return $query->orderBy('deleted_at', 'desc')->get();
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
}
