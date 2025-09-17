<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVarianBodyRequest;
use App\Http\Requests\UpdateVarianBodyRequest;
use App\Models\EVarianBody;

class VarianBodyController extends Controller
{
    public function index()
    {
        // Mengambil semua varian body dengan data induknya
        return response()->json(EVarianBody::with('jenisKendaraan')->orderBy('id')->get());
    }

    public function store(StoreVarianBodyRequest $request)
    {
        $varianBody = EVarianBody::create($request->validated());
        return response()->json($varianBody, 201);
    }

    public function show(EVarianBody $varianBody)
    {
        return response()->json($varianBody->load('jenisKendaraan'));
    }

    public function update(UpdateVarianBodyRequest $request, EVarianBody $varianBody)
    {
        $varianBody->update($request->validated());
        return response()->json($varianBody);
    }

    public function destroy(EVarianBody $varianBody)
    {
        // Tidak perlu proteksi, karena ini adalah data level paling bawah
        $varianBody->delete();
        return response()->json(null, 204);
    }
}