<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SkrbSetting;
use Illuminate\Http\Request;

class SkrbSettingController extends Controller
{
    /**
     * Mengambil data identifikasi & alamat penerima SKRB saat ini.
     */
    public function show()
    {
        $setting = SkrbSetting::first();
        
        if (!$setting) {
            $setting = SkrbSetting::create([
                'recipient_address' => "Bapak Direktur Jendral Perhubungan Darat\nCq. Direktur Sarana dan Keselamatan\nTransportasi Jalan\nJl. Merdeka Barat No.8\nDi Jakarta",
            ]);
        }

        return response()->json($setting);
    }

    /**
     * Memperbarui data identifikasi & alamat penerima (wajib terisi / tidak boleh kosong).
     */
    public function update(Request $request)
    {
        $request->validate([
            'recipient_address' => 'required|string|min:5',
        ], [
            'recipient_address.required' => 'Identifikasi dan alamat penerima tidak boleh kosong!',
        ]);

        $setting = SkrbSetting::first();
        if (!$setting) {
            $setting = new SkrbSetting();
        }

        $setting->recipient_address = $request->input('recipient_address');
        $setting->save();

        return response()->json([
            'message' => 'Identifikasi dan alamat penerima berhasil diperbarui.',
            'data' => $setting
        ]);
    }
}
