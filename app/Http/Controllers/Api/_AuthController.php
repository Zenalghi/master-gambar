<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class _AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string', // Ganti dari email
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Auth::attempt($request->only('username', 'password'))) {
            return response()->json(['message' => 'Username atau Password salah.'], 401);
        }

        $user = User::with('role')->where('username', $request->username)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil']);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users', // Ganti dari email
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role_id' => 3,
            // 'role' akan default ke 'drafter'
        ]);

        return response()->json([
            'message' => 'Registrasi berhasil',
            'user' => $user
        ], 201);
    }
}
// DIBAWAH INI PAKE EMAIL 
// class AuthController extends Controller
// {
//     /**
//      * Handle user login.
//      */
//     public function login(Request $request)
//     {
//         $validator = Validator::make($request->all(), [
//             'email' => 'required|email',
//             'password' => 'required|string',
//         ]);

//         if ($validator->fails()) {
//             return response()->json(['errors' => $validator->errors()], 422);
//         }

//         if (!Auth::attempt($request->only('email', 'password'))) {
//             return response()->json(['message' => 'Email atau Password salah.'], 401);
//         }

//         $user = User::where('email', $request->email)->firstOrFail();

//         $token = $user->createToken('auth_token')->plainTextToken;

//         return response()->json([
//             'message' => 'Login berhasil',
//             'access_token' => $token,
//             'token_type' => 'Bearer',
//             'user' => $user,
//         ]);
//     }

//     /**
//      * Handle user logout.
//      */
//     public function logout(Request $request)
//     {
//         $request->user()->currentAccessToken()->delete();

//         return response()->json(['message' => 'Logout berhasil']);
//     }

//     /**
//      * (Opsional) Handle user registration.
//      * Mungkin tidak Anda perlukan jika user dibuat oleh admin.
//      */
//     public function register(Request $request)
//     {
//         $validator = Validator::make($request->all(), [
//             'name' => 'required|string|max:255',
//             'email' => 'required|string|email|max:255|unique:users',
//             'password' => 'required|string|min:8|confirmed',
//         ]);

//         if ($validator->fails()) {
//             return response()->json($validator->errors(), 422);
//         }

//         $user = User::create([
//             'name' => $request->name,
//             'email' => $request->email,
//             'password' => Hash::make($request->password),
//             // 'role' akan default ke 'drafter' sesuai migrasi Anda
//         ]);

//         return response()->json([
//             'message' => 'Registrasi berhasil',
//             'user' => $user
//         ], 201);
//     }
// }