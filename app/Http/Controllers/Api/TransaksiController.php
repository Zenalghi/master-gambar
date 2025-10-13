<?php

namespace App\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\UpdateTransaksiRequest;
use App\Http\Requests\StoreTransaksiRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use Illuminate\Http\Request;

class TransaksiController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        // 1. Validasi parameter (termasuk untuk filter baru)
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'perPage' => 'integer|in:25,50,100',
            'sortBy' => 'nullable|string',
            'sortDirection' => 'string|in:asc,desc',
            'search' => 'nullable|string',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'a_type_engine_id' => 'nullable|string|exists:a_type_engines,id',
            'b_merk_id' => 'nullable|string|exists:b_merks,id',
            'c_type_chassis_id' => 'nullable|string|exists:c_type_chassis,id',
            'd_jenis_kendaraan_id' => 'nullable|string|exists:d_jenis_kendaraan,id',
            'f_pengajuan_id' => 'nullable|integer|exists:f_pengajuan,id',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $perPage = $validated['perPage'] ?? 25;
        $sortBy = $validated['sortBy'] ?? 'updated_at';
        $sortDirection = $validated['sortDirection'] ?? 'desc';
        $search = $validated['search'] ?? '';

        $query = Transaksi::with([
            'user:id,name',
            'customer:id,nama_pt',
            'aTypeEngine',
            'bMerk',
            'cTypeChassis',
            'dJenisKendaraan',
            'fPengajuan'
        ]);

        // Terapkan filter spesifik per kolom jika ada
        if (isset($validated['customer_id'])) {
            $query->where('customer_id', $validated['customer_id']);
        }
        if (isset($validated['a_type_engine_id'])) {
            $query->where('a_type_engine_id', $validated['a_type_engine_id']);
        }
        if (isset($validated['b_merk_id'])) {
            $query->where('b_merk_id', $validated['b_merk_id']);
        }
        if (isset($validated['c_type_chassis_id'])) {
            $query->where('c_type_chassis_id', $validated['c_type_chassis_id']);
        }
        if (isset($validated['d_jenis_kendaraan_id'])) {
            $query->where('d_jenis_kendaraan_id', $validated['d_jenis_kendaraan_id']);
        }
        if (isset($validated['f_pengajuan_id'])) {
            $query->where('f_pengajuan_id', $validated['f_pengajuan_id']);
        }
        if (isset($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        // Filter pencarian global (tidak berubah)
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                // ... (logika search sama seperti sebelumnya)
            });
        }

        // Daftar kolom yang bisa di-sort dari tabel relasi
        $sortableRelations = [
            'customer' => ['customers', 'nama_pt'],
            'type_engine' => ['a_type_engines', 'type_engine'],
            'merk' => ['b_merks', 'merk'],
            'type_chassis' => ['c_type_chassis', 'type_chassis'],
            'jenis_kendaraan' => ['d_jenis_kendaraan', 'jenis_kendaraan'],
            'jenis_pengajuan' => ['f_pengajuan', 'jenis_pengajuan'],
            'user' => ['users', 'name'],
        ];

        // Jika sorting berdasarkan relasi
        if (array_key_exists($sortBy, $sortableRelations)) {
            $relation = $sortableRelations[$sortBy];
            $relationTable = $relation[0];
            $relationColumn = $relation[1];
            $foreignKey = substr($relationTable, 0, 1) . '_' . str_replace('s', '', $relationTable) . '_id';
            if ($sortBy == 'jenis_pengajuan') { // Pengecualian untuk f_pengajuan
                $foreignKey = 'f_pengajuan_id';
            }
            if ($sortBy == 'customer') { // Pengecualian untuk customers
                $foreignKey = 'customer_id';
            }
            if ($sortBy == 'user') { // Pengecualian untuk users
                $foreignKey = 'user_id';
            }


            $query->join($relationTable, "transaksis.{$foreignKey}", '=', "{$relationTable}.id")
                ->orderBy($relationColumn, $sortDirection)
                ->select('transaksis.*'); // Penting untuk menghindari ambiguitas kolom 'id'
        } else {
            // Sorting berdasarkan kolom di tabel transaksi itu sendiri (default)
            $query->orderBy($sortBy, $sortDirection);
        }

        $transaksis = $query->paginate($perPage);

        return response()->json($transaksis);
    }

    /**
     * Menyimpan transaksi baru.
     */
    public function store(StoreTransaksiRequest $request)
    {
        $validated = $request->validated();
        $jenisKendaraanId = $validated['d_jenis_kendaraan_id'];

        $lastTransaksi = Transaksi::where('id', 'like', $jenisKendaraanId . '-%')->orderBy('id', 'desc')->first();
        $counter = 1;
        if ($lastTransaksi) {
            $parts = explode('-', $lastTransaksi->id);
            $counter = intval(end($parts)) + 1;
        }
        $newId = $jenisKendaraanId . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);

        $engineId = substr($jenisKendaraanId, 0, 2);
        $merkId = substr($jenisKendaraanId, 0, 4);
        $chassisId = substr($jenisKendaraanId, 0, 7);

        $transaksi = Transaksi::create([
            'id' => $newId,
            'a_type_engine_id' => $engineId,
            'b_merk_id' => $merkId,
            'c_type_chassis_id' => $chassisId,
            'd_jenis_kendaraan_id' => $jenisKendaraanId,
            'customer_id' => $validated['customer_id'],
            'f_pengajuan_id' => $validated['f_pengajuan_id'],
            'user_id' => Auth::id(),
        ]);

        return response()->json($transaksi->load(['user', 'customer', 'aTypeEngine', 'bMerk', 'cTypeChassis', 'dJenisKendaraan', 'fPengajuan']), 201);
    }

    /**
     * Menampilkan satu data transaksi spesifik.
     */
    public function show(Transaksi $transaksi)
    {
        return response()->json($transaksi->load(['user', 'customer', 'aTypeEngine', 'bMerk', 'cTypeChassis', 'dJenisKendaraan', 'fPengajuan']));
    }

    /**
     * Memperbarui data transaksi.
     */
    public function update(UpdateTransaksiRequest $request, Transaksi $transaksi)
    {
        $this->authorize('update', $transaksi);
        $transaksi->update($request->validated());
        return response()->json($transaksi->fresh()->load(['user', 'customer', 'aTypeEngine', 'bMerk', 'cTypeChassis', 'dJenisKendaraan', 'fPengajuan']));
    }

    /**
     * Menghapus data transaksi.
     */
    public function destroy(Transaksi $transaksi)
    {
        $this->authorize('delete', $transaksi);
        $transaksi->delete();
        return response()->json(null, 204);
    }
}
