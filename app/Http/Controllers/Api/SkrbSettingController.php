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
                'ignore_names' => ['(4x2)', '(6x2)', '(4x4)', 'M/T', 'A/T'],
            ]);
        }

        if (is_null($setting->ignore_names)) {
            $setting->ignore_names = [];
        }

        return response()->json($setting);
    }

    /**
     * Memperbarui data identifikasi & alamat penerima (wajib terisi / tidak boleh kosong) serta ignore_names.
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
        if ($request->has('ignore_names')) {
            $ignoreNames = $request->input('ignore_names');
            if (is_string($ignoreNames)) {
                $ignoreNames = json_decode($ignoreNames, true) ?? [];
            }
            $setting->ignore_names = is_array($ignoreNames) ? array_values(array_filter(array_map('trim', $ignoreNames))) : [];
        }
        $setting->save();

        return response()->json([
            'message' => 'Pengaturan SKRB berhasil diperbarui.',
            'data' => $setting
        ]);
    }
}
