<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class X_UserController extends Controller
{
    /**
     * Menampilkan daftar semua user.
     * Admin akan melihat nama, username, role, dan timestamps (created_at, updated_at).
     */
    public function index()
    {
        return response()->json(User::all());
    }

    /**
     * Membuat user baru oleh admin.
     */
    public function store(StoreUserRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
        ]);
        return response()->json($user, 201);
    }

    /**
     * Menampilkan detail satu user.
     */
    public function show(User $user)
    {
        return response()->json($user);
    }

    /**
     * Mengupdate data user.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        // Hanya update password jika admin mengisinya di form
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']); // Hapus password dari data jika kosong
        }

        $user->update($data);
        return response()->json($user->fresh()); // Kirim data terbaru
    }

    /**
     * Menghapus user.
     */
    public function destroy(User $user)
    {
        // Proteksi agar admin tidak bisa menghapus akunnya sendiri
        if (Auth::id() === $user->id) {
            return response()->json(['message' => 'Admin cannot delete their own account.'], 403);
        }

        // --- LOGIKA BARU UNTUK MENGHAPUS PARAF ---
        // 2. Cek apakah user memiliki file paraf (signature)
        if ($user->signature) {
            // Dapatkan nama folder dari path file
            $folderPath = dirname($user->signature);

            // Hapus seluruh folder milik user tersebut dari disk 'user_paraf'
            Storage::disk('user_paraf')->deleteDirectory($folderPath);
        }
        // --- AKHIR LOGIKA BARU ---

        // 3. Hapus data user dari database
        $user->delete();

        return response()->json(null, 204);
    }
}
