<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class X_CustomerController extends Controller
{
    /**
     * Menampilkan data customer dengan paginasi, pencarian, dan sorting.
     */
    public function index(Request $request)
    {
        // 1. Tentukan parameter dari request
        $perPage = $request->input('per_page', 25);
        $search = $request->input('search');

        $sortBy = $request->input('sort_by', 'updated_at');
        $sortAsc = $request->input('sort_asc', 'false') === 'true';

        // 2. Tentukan kolom yang diizinkan untuk di-sort
        $allowedSorts = ['nama_pt', 'pj', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'updated_at';
        }

        // 3. Mulai query
        $query = Customer::query();

        // 4. Terapkan logika pencarian
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_pt', 'like', "%{$search}%")
                    ->orWhere('pj', 'like', "%{$search}%");
            });
        }

        // 5. Terapkan logika sorting
        $query->orderBy($sortBy, $sortAsc ? 'asc' : 'desc');

        // 6. Ambil data dengan paginasi
        $paginated = $query->paginate($perPage);

        // 7. Format response sesuai kebutuhan Flutter
        return response()->json([
            'data' => $paginated->items(),
            'total' => $paginated->total(),
        ]);
    }

    /**
     * Menyimpan customer baru. (POST)
     */
    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated());
        return response()->json($customer, 201); // 201 Created
    }

    /**
     * Menampilkan satu data customer spesifik. (GET by ID)
     */
    public function show(Customer $customer)
    {
        return response()->json($customer);
    }

    /**
     * Memperbarui data customer. (PUT/PATCH)
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());
        return response()->json($customer->fresh());
    }

    /**
     * Menghapus data customer. (DELETE)
     */
    public function destroy(Customer $customer)
    {
        // Cek apakah customer memiliki file paraf (signature_pj)
        if ($customer->signature_pj) {
            // Ambil nama folder dari path file
            $folderPath = dirname($customer->signature_pj);

            // Hapus seluruh folder milik customer tersebut dari disk 'customer_paraf'
            Storage::disk('customer_paraf')->deleteDirectory($folderPath);
        }

        // Hapus data customer dari database
        $customer->delete();

        return response()->json(null, 204); // 204 No Content
    }
}
