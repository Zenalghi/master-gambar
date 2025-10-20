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
    public function index(Request $request)
    {
        // Ambil parameter dari request dengan nilai default
        $perPage = $request->input('per_page', 25);
        $sortBy = $request->input('sort_by', 'nama_pt');
        $sortAsc = $request->input('sort_asc', 'true') === 'true';
        $search = $request->input('search');

        // Mulai query
        $query = Customer::query();

        // Jika ada pencarian, tambahkan kondisi where
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_pt', 'like', "%{$search}%")
                    ->orWhere('pj', 'like', "%{$search}%");
            });
        }

        // Terapkan sorting
        $query->orderBy($sortBy, $sortAsc ? 'asc' : 'desc');

        // Ambil data dengan paginasi
        $paginated = $query->paginate($perPage);

        // Format response sesuai kebutuhan Flutter
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
        return response()->json($customer);
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
