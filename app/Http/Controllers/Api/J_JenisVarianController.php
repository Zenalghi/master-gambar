<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JJudulGambar;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class J_JenisVarianController extends Controller
{
    public function index()
    {
        return JJudulGambar::orderBy('id')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_judul' => [ // <-- Ubah menjadi array
                'required',
                'string',
                'max:255',
                Rule::unique('j_judul_gambars')->whereNull('deleted_at'),
            ],
        ]);
        // Tambahkan mutator di sini karena modelnya sederhana
        $validated['nama_judul'] = Str::upper($validated['nama_judul']);

        $jenisVarian = JJudulGambar::create($validated);
        return response()->json($jenisVarian, 201);
    }

    public function show(JJudulGambar $jJudulGambar)
    {
        return $jJudulGambar;
    }

    public function update(Request $request, JJudulGambar $jJudulGambar)
    {
        $validated = $request->validate([
            'nama_judul' => [ // <-- Ubah menjadi array
                'required',
                'string',
                'max:255',
                Rule::unique('j_judul_gambars')->whereNull('deleted_at')->ignore($jJudulGambar->id),
            ],
        ]);

        $validated['nama_judul'] = Str::upper($validated['nama_judul']);
        $jJudulGambar->update($validated);
        return $jJudulGambar;
    }

    public function destroy(JJudulGambar $jJudulGambar)
    {
        // Di masa depan, Anda bisa menambahkan proteksi di sini untuk mengecek
        // apakah Jenis Varian ini sedang digunakan di tabel z_transaksi_varians.
        // if ($jJudulGambar->transaksiVarians()->exists()) { ... }

        $jJudulGambar->delete();
        return response()->noContent();
    }
}
