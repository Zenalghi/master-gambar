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
        $fileName = Str::slug($user->name) . '.png';

        $path = $request->file('paraf')->storeAs($folderPath, $fileName, 'user_paraf');

        // Update path dan paksa update timestamp
        $user->update(['signature' => $path]);
        $user->touch();

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
        $fileName = Str::slug($customer->pj) . '.png';

        $path = $request->file('paraf_pj')->storeAs($folderPath, $fileName, 'customer_paraf');

        // Update path dan paksa update timestamp
        $customer->update(['signature_pj' => $path]);
        $customer->touch();

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
