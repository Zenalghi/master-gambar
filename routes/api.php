<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OptionController;
use App\Http\Controllers\Api\CustomerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TypeEngineController;
use App\Http\Controllers\Api\MerkController;
use App\Http\Controllers\Api\TypeChassisController;
use App\Http\Controllers\Api\JenisKendaraanController;
use App\Http\Controllers\Api\VarianBodyController;
use App\Http\Controllers\Api\DrawingController;

// Rute Publik (tidak perlu login)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']); // Jika Anda butuh registrasi mandiri
Route::post('/drawings/generate-preview', [DrawingController::class, 'generatePdf'])->middleware('auth.api');

// Rute Terproteksi (Sekarang menggunakan alias 'auth.api')
Route::middleware('auth.api')->group(function () {
    // Rute autentikasi
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Rute untuk mendapatkan data dropdown
    Route::get('/options/type-engines', [OptionController::class, 'getTypeEngines']);
    Route::get('/options/merks/{engine_id}', [OptionController::class, 'getMerks']);
    Route::get('/options/type-chassis/{merk_id}', [OptionController::class, 'getTypeChassis']);
    Route::get('/options/jenis-kendaraan/{chassis_id}', [OptionController::class, 'getJenisKendaraan']);
    Route::get('/options/varian-body/{jenis_kendaraan_id}', [OptionController::class, 'getVarianBody']);
    Route::get('/options/users', [OptionController::class, 'getUsers']);
    Route::get('/options/customers', [OptionController::class, 'getCustomers']);

    Route::apiResource('type-engines', TypeEngineController::class);
    Route::apiResource('merks', MerkController::class);
    Route::apiResource('type-chassis', TypeChassisController::class)
     ->parameters(['type-chassis' => 'typeChassis']);
    Route::apiResource('jenis-kendaraan', JenisKendaraanController::class);
    Route::apiResource('varian-body', VarianBodyController::class);
    
    Route::apiResource('customers', CustomerController::class);
    // Route::post('/drawings/generate-preview', [DrawingController::class, 'generatePdf']);
    
    // Anda bisa tambahkan rute untuk PROSES UTAMA di sini
    // Contoh:
    // Route::post('/drawings/preview', [DrawingController::class, 'generatePreview']);
    // Route::post('/drawings/store', [DrawingController::class, 'storeFinalDrawing']);
}

);

//pakai alias, g jadi dipake:dibawah ini
// Rute Terproteksi (WAJIB login dan mengirim token)
// Route::middleware('auth:sanctum')->group(function () {
//     // Rute autentikasi
//     Route::post('/logout', [AuthController::class, 'logout']);
//     Route::get('/user', function (Request $request) {
//         return $request->user();
//     });
