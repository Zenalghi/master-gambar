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
        $perPage = $request->input('per_page', 50);
        $search = $request->input('search');

        $sortBy = $request->input('sort_by', 'updated_at');
        $sortAsc = $request->input('sort_asc', 'false') === 'true';

        // 2. Tentukan kolom yang diizinkan untuk di-sort
        $allowedSorts = ['nama_pt', 'pj', 'jabatan', 'nama_drafter', 'nama_pemeriksa', 'created_at', 'updated_at', 'status_tdp', 'tdp_masa_berlaku'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'updated_at';
        }

        // 3. Mulai query
        $query = Customer::query()->with('documentCustomer');

        // 4. Terapkan logika pencarian
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_pt', 'like', "%{$search}%")
                    ->orWhere('pj', 'like', "%{$search}%")
                    ->orWhere('jabatan', 'like', "%{$search}%")
                    ->orWhere('nama_drafter', 'like', "%{$search}%")
                    ->orWhere('nama_pemeriksa', 'like', "%{$search}%")
                    ->orWhere('created_at', 'like', "%{$search}%")
                    ->orWhere('updated_at', 'like', "%{$search}%");
            });
        }
        // 5. Terapkan logika sorting
        $direction = $sortAsc ? 'ASC' : 'DESC';

        if (in_array($sortBy, ['status_tdp', 'tdp_masa_berlaku'])) {
            $query->leftJoin('document_customers', 'customers.id', '=', 'document_customers.customer_id')
                  ->select('customers.*')
                  ->orderByRaw("document_customers.tdp_masa_berlaku IS NULL ASC, document_customers.tdp_masa_berlaku {$direction}");
        } elseif (in_array($sortBy, ['jabatan', 'nama_drafter', 'nama_pemeriksa'])) {
            $query->orderByRaw("$sortBy IS NULL ASC, $sortBy $direction");
        } else {
            // Untuk kolom yang tidak nullable (nama_pt, pj, dll)
            $query->orderBy($sortBy, $direction);
        }

        // 6. Ambil data dengan paginasi
        $paginated = $query->paginate($perPage);

        // 7. Transformasi: Tambahkan status_tdp dan tdp_masa_berlaku ke response
        $paginated->getCollection()->transform(function ($customer) {
            $doc = $customer->documentCustomer;
            $customer->setAttribute('status_tdp', $doc ? $doc->status_tdp : null);
            $customer->setAttribute('tdp_masa_berlaku', $doc ? $doc->tdp_masa_berlaku?->format('Y-m-d') : null);
            unset($customer->documentCustomer); // Hapus relasi dari response agar tidak duplikat
            return $customer;
        });

        // 8. Format response sesuai kebutuhan Flutter
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
        // 1. Hapus file paraf dan direktori di disk 'customer_paraf'
        if ($customer->signature_pj) {
            Storage::disk('customer_paraf')->delete($customer->signature_pj);
            Storage::disk('customer_paraf')->deleteDirectory(dirname($customer->signature_pj));
        }
        if ($customer->signature_drafter) {
            Storage::disk('customer_paraf')->delete($customer->signature_drafter);
            Storage::disk('customer_paraf')->deleteDirectory(dirname($customer->signature_drafter));
        }
        if ($customer->signature_pemeriksa) {
            Storage::disk('customer_paraf')->delete($customer->signature_pemeriksa);
            Storage::disk('customer_paraf')->deleteDirectory(dirname($customer->signature_pemeriksa));
        }
        // Hapus juga direktori folder berdasarkan ID customer secara eksplisit
        Storage::disk('customer_paraf')->deleteDirectory((string) $customer->id);

        // 2. Hapus seluruh file PDF & folder di disk 'customer-documents'
        Storage::disk('customer-documents')->deleteDirectory((string) $customer->id);

        // 3. Hapus data di tabel document_customers
        $customer->documentCustomer()->delete();
        \App\Models\DocumentCustomer::where('customer_id', $customer->id)->delete();

        // 4. Hapus data customer dari database (termasuk path gambar paraf di DB)
        $customer->delete();

        return response()->json(null, 204); // 204 No Content
    }
}
