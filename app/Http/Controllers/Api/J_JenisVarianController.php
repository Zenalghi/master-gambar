<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JJudulGambar;
use Illuminate\Http\Request;

class J_JenisVarianController extends Controller
{
    public function index()
    {
        return JJudulGambar::orderBy('id')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_judul' => 'required|string|unique:j_judul_gambars,nama_judul',
        ]);
        return JJudulGambar::create($validated);
    }

    public function show(JJudulGambar $jJudulGambar)
    {
        return $jJudulGambar;
    }

    public function update(Request $request, JJudulGambar $jJudulGambar)
    {
        $validated = $request->validate([
            'nama_judul' => 'required|string|unique:j_judul_gambars,nama_judul,' . $jJudulGambar->id,
        ]);
        $jJudulGambar->update($validated);
        return $jJudulGambar;
    }

    public function destroy(JJudulGambar $jJudulGambar)
    {
        // Tambahkan proteksi jika diperlukan di masa depan
        $jJudulGambar->delete();
        return response()->noContent();
    }
}
