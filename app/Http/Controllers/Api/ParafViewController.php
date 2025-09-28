<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
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

    /**
     * Menampilkan file paraf user.
     */
    public function showUserParaf(User $user)
    {
        if (!$user->signature) {
            return response()->json(['message' => 'User signature not found.'], 404);
        }

        if (!Storage::disk('user_paraf')->exists($user->signature)) {
            return response()->json(['message' => 'Signature file not found on disk.'], 404);
        }

        // Kembalikan file sebagai respons gambar
        return Storage::disk('user_paraf')->response($user->signature);
    }
}
