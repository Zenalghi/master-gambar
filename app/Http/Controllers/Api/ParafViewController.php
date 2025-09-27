<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Support\Facades\Storage;

class ParafViewController extends Controller
{
    /**
     * Menampilkan file paraf customer.
     */
    public function showCustomerParaf(Customer $customer)
    {
        // Periksa apakah customer memiliki path signature
        if (!$customer->signature_pj) {
            return response()->json(['message' => 'Paraf not found.'], 404);
        }

        // Periksa apakah file benar-benar ada di storage
        if (!Storage::disk('customer_paraf')->exists($customer->signature_pj)) {
            return response()->json(['message' => 'File paraf not found on disk.'], 404);
        }

        // Kembalikan file sebagai respons gambar
        return Storage::disk('customer_paraf')->response($customer->signature_pj);
    }
}
