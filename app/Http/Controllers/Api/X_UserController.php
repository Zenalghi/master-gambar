<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class X_UserController extends Controller
{
    /**
     * Menampilkan daftar user dengan paginasi, pencarian, dan sorting.
     */
    public function index(Request $request)
    {
        // 1. Tentukan parameter dari request
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');

        $sortBy = $request->input('sort_by', 'updated_at');
        $sortAsc = $request->input('sort_asc', 'false') === 'true';

        // 2. Tentukan kolom yang diizinkan untuk di-sort
        $allowedSorts = ['name', 'username', 'role', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'updated_at';
        }

        // 3. Mulai query
        $query = User::with('role');

        // 4. Terapkan logika pencarian
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhereHas('role', function ($roleQuery) use ($search) {
                        $roleQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // 5. Terapkan logika sorting
        if ($sortBy == 'role') {
            $query->join('roles', 'users.role_id', '=', 'roles.id')
                ->orderBy('roles.name', $sortAsc ? 'asc' : 'desc')
                ->select('users.*');
        } else {
            $query->orderBy($sortBy, $sortAsc ? 'asc' : 'desc');
        }

        // 6. Ambil data dengan paginasi
        $paginated = $query->paginate($perPage);

        // 7. Format response sesuai kebutuhan Flutter
        return response()->json([
            'data' => $paginated->items(),
            'total' => $paginated->total(),
        ]);
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
        // Eager load relasi role
        return response()->json($user->load('role'));
    }

    /**
     * Mengupdate data user.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        // Kirim data terbaru, termasuk relasi role
        return response()->json($user->fresh()->load('role'));
    }

    /**
     * Menghapus user.
     */
    public function destroy(User $user)
    {
        if (Auth::id() === $user->id) {
            return response()->json(['message' => 'Admin cannot delete their own account.'], 403);
        }

        if ($user->signature) {
            $folderPath = dirname($user->signature);
            Storage::disk('user_paraf')->deleteDirectory($folderPath);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
