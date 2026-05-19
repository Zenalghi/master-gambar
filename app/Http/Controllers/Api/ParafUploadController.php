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
     * Mengunggah atau memperbarui paraf untuk Customer (PJ, Drafter, Pemeriksa).
     */
    public function uploadCustomerParaf(Request $request, Customer $customer)
    {
        $request->validate([
            'paraf_pj' => 'nullable|image|mimes:png|max:1024',
            'paraf_drafter' => 'nullable|image|mimes:png|max:1024',
            'paraf_pemeriksa' => 'nullable|image|mimes:png|max:1024',
        ]);

        $folderPath = (string) $customer->id;

        // 1. Upload Paraf PJ
        if ($request->hasFile('paraf_pj')) {
            if ($customer->signature_pj) {
                Storage::disk('customer_paraf')->delete($customer->signature_pj);
            }
            $fileName = $customer->id . '_pj.png'; // Penamaan dibedakan
            $path = $request->file('paraf_pj')->storeAs($folderPath, $fileName, 'customer_paraf');
            $customer->signature_pj = $path;
        }

        // 2. Upload Paraf Drafter
        if ($request->hasFile('paraf_drafter')) {
            if ($customer->signature_drafter) {
                Storage::disk('customer_paraf')->delete($customer->signature_drafter);
            }
            $fileName = $customer->id . '_drafter.png';
            $path = $request->file('paraf_drafter')->storeAs($folderPath, $fileName, 'customer_paraf');
            $customer->signature_drafter = $path;
        }

        // 3. Upload Paraf Pemeriksa
        if ($request->hasFile('paraf_pemeriksa')) {
            if ($customer->signature_pemeriksa) {
                Storage::disk('customer_paraf')->delete($customer->signature_pemeriksa);
            }
            $fileName = $customer->id . '_pemeriksa.png';
            $path = $request->file('paraf_pemeriksa')->storeAs($folderPath, $fileName, 'customer_paraf');
            $customer->signature_pemeriksa = $path;
        }

        $customer->save();
        $customer->touch(); // Paksa update timestamp

        return response()->json($customer->fresh());
    }

    /**
     * Menghapus paraf Customer berdasarkan tipe (opsional: via query string ?type=drafter).
     */
    public function destroyCustomerParaf(Request $request, Customer $customer)
    {
        $type = $request->query('type', 'all'); // 'all', 'pj', 'drafter', 'pemeriksa'

        if (in_array($type, ['all', 'pj']) && $customer->signature_pj) {
            Storage::disk('customer_paraf')->delete($customer->signature_pj);
            $customer->signature_pj = null;
        }

        if (in_array($type, ['all', 'drafter']) && $customer->signature_drafter) {
            Storage::disk('customer_paraf')->delete($customer->signature_drafter);
            $customer->signature_drafter = null;
        }

        if (in_array($type, ['all', 'pemeriksa']) && $customer->signature_pemeriksa) {
            Storage::disk('customer_paraf')->delete($customer->signature_pemeriksa);
            $customer->signature_pemeriksa = null;
        }

        $customer->save();

        return response()->json(null, 204);
    }
    
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

        $folderPath = $user->id;
        $fileName = $user->id . '.png';

        $path = $request->file('paraf')->storeAs($folderPath, $fileName, 'user_paraf');

        // Update path dan paksa update timestamp
        $user->update(['signature' => $path]);
        $user->touch();

        return response()->json($user->fresh());
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
}
