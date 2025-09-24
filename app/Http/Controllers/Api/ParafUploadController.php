<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ParafUploadController extends Controller
{
    /**
     * Mengunggah atau memperbarui paraf untuk User.
     */
    public function uploadUserParaf(Request $request, User $user)
    {
        $request->validate([
            'paraf' => 'required|image|mimes:png|max:1024',
        ]);

        if ($user->signature) {
            Storage::disk('user_paraf')->delete($user->signature);
        }

        $folderPath = $user->id . '-' . Str::slug($user->username);

        // --- PERUBAHAN DI SINI ---
        // Gunakan nama user sebagai nama file
        $fileName = Str::slug($user->name) . '.png';

        $path = $request->file('paraf')->storeAs($folderPath, $fileName, 'user_paraf');
        $user->update(['signature' => $path]);

        return response()->json($user->fresh());
    }

    /**
     * Mengunggah atau memperbarui paraf untuk Penanggung Jawab Customer.
     */
    public function uploadCustomerParaf(Request $request, Customer $customer)
    {
        $request->validate([
            'paraf_pj' => 'required|image|mimes:png|max:1024',
        ]);

        if ($customer->signature_pj) {
            Storage::disk('customer_paraf')->delete($customer->signature_pj);
        }

        $folderPath = $customer->id . '-' . Str::slug($customer->nama_pt);

        // --- PERUBAHAN DI SINI ---
        // Gunakan nama penanggung jawab (pj) sebagai nama file
        $fileName = Str::slug($customer->pj) . '.png';

        $path = $request->file('paraf_pj')->storeAs($folderPath, $fileName, 'customer_paraf');
        $customer->update(['signature_pj' => $path]);

        return response()->json($customer->fresh());
    }

    /**
     * Menghapus paraf User.
     */
    public function destroyUserParaf(User $user)
    {
        if ($user->signature) {
            Storage::disk('user_paraf')->delete($user->signature);
            $user->update(['signature' => null]);
        }
        return response()->json(null, 204);
    }

    /**
     * Menghapus paraf Customer.
     */
    public function destroyCustomerParaf(Customer $customer)
    {
        if ($customer->signature_pj) {
            Storage::disk('customer_paraf')->delete($customer->signature_pj);
            $customer->update(['signature_pj' => null]);
        }
        return response()->json(null, 204);
    }
}
