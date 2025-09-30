<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\_AuthController as AuthController;
use App\Http\Controllers\Api\_OptionController as OptionController;
use App\Http\Controllers\Api\A_TypeEngineController as TypeEngineController;
use App\Http\Controllers\Api\B_MerkController as MerkController;
use App\Http\Controllers\Api\C_TypeChassisController as TypeChassisController;
use App\Http\Controllers\Api\D_JenisKendaraanController as JenisKendaraanController;
use App\Http\Controllers\Api\E_VarianBodyController as VarianBodyController;
use App\Http\Controllers\Api\X_CustomerController as CustomerController;
use App\Http\Controllers\Api\X_UserController as UserController;
use App\Http\Controllers\Api\Z_DrawingController as DrawingController;
use App\Http\Controllers\Api\TransaksiController;
use App\Http\Controllers\Api\ProsesTransaksiController;
use App\Http\Controllers\Api\GambarMasterController;
use App\Http\Controllers\Api\ParafUploadController;
use App\Http\Controllers\Api\ParafViewController;
use App\Http\Controllers\Api\J_JenisVarianController;
use App\Http\Controllers\Api\H_GambarOptionalController;

// Rute Publik (tidak perlu login)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']); // Jika Anda butuh registrasi mandiri
Route::post('/drawings/generate-preview', [DrawingController::class, 'generatePdf'])->middleware('auth.api');

// Rute Terproteksi (Sekarang menggunakan alias 'auth.api')
Route::middleware('auth.api')->group(
    function () {
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
        Route::get('/options/pengajuan', [OptionController::class, 'getPengajuan']);
        Route::get('/options/users', [OptionController::class, 'getUsers']);
        Route::get('/options/customers', [OptionController::class, 'getCustomers']);
        Route::get('/options/roles', [OptionController::class, 'getRoles']);
        Route::get('/options/users/pemeriksa', [OptionController::class, 'getPemeriksa']);
        Route::get('/options/gambar-optional', [OptionController::class, 'getGambarOptional']);
        Route::get('/options/gambar-kelistrikan/{chassis_id}', [OptionController::class, 'getGambarKelistrikan']);
        Route::get('/options/judul-gambar', [OptionController::class, 'getJudulGambar']);

        Route::apiResource('type-engines', TypeEngineController::class);
        Route::apiResource('merks', MerkController::class);
        Route::apiResource('type-chassis', TypeChassisController::class)
            ->parameters(['type-chassis' => 'typeChassis']);
        Route::apiResource('jenis-kendaraan', JenisKendaraanController::class);
        Route::apiResource('varian-body', VarianBodyController::class);

        Route::apiResource('transaksi', TransaksiController::class);
        Route::post('/transaksi/{transaksi}/detail', [ProsesTransaksiController::class, 'simpanDetail']);
        Route::post('/transaksi/{transaksi}/proses', [ProsesTransaksiController::class, 'proses']);


        Route::middleware('is.admin')->prefix('admin')->group(function () {
            // Rute CRUD untuk mengelola User
            Route::apiResource('users', UserController::class);
            Route::get('/options/roles', [OptionController::class, 'getRoles']);

            // Rute CRUD untuk mengelola Customer
            Route::apiResource('customers', CustomerController::class);
            Route::post('/gambar-master/utama', [GambarMasterController::class, 'uploadGambarUtama']);
            Route::post('/gambar-master/optional', [GambarMasterController::class, 'uploadGambarOptional']);

            Route::delete('/gambar-master/utama/{e_varian_body_id}', [GambarMasterController::class, 'destroyGambarUtama']);
            Route::delete('/gambar-master/optional/{e_varian_body_id}', [GambarMasterController::class, 'destroyGambarOptional']);

            Route::post('/gambar-master/kelistrikan', [GambarMasterController::class, 'uploadGambarKelistrikan']);
            Route::delete('/gambar-master/kelistrikan/{c_type_chassis_id}', [GambarMasterController::class, 'destroyGambarKelistrikan']);

            // --- RUTE BARU UNTUK UPLOAD & DELETE PARAF ---
            Route::post('/users/{user}/paraf', [ParafUploadController::class, 'uploadUserParaf']);
            Route::delete('/users/{user}/paraf', [ParafUploadController::class, 'destroyUserParaf']);

            Route::post('/customers/{customer}/paraf', [ParafUploadController::class, 'uploadCustomerParaf']);
            Route::get('/customers/{customer}/paraf', [ParafViewController::class, 'showCustomerParaf']);
            Route::get('/users/{user}/paraf', [ParafViewController::class, 'showUserParaf']);
            Route::apiResource('jenis-varian', J_JenisVarianController::class)->parameters(['jenis-varian' => 'jJudulGambar']);
            Route::apiResource('gambar-optional', H_GambarOptionalController::class);
        });
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
