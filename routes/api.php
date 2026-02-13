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
use App\Http\Controllers\Api\Z_pdf_png_pdfController;
use App\Http\Controllers\Api\TransaksiController;
use App\Http\Controllers\Api\ProsesTransaksiController;
use App\Http\Controllers\Api\GambarMasterController;
use App\Http\Controllers\Api\ParafUploadController;
use App\Http\Controllers\Api\ParafViewController;
use App\Http\Controllers\Api\J_JenisVarianController;
use App\Http\Controllers\Api\H_GambarOptionalController;
use App\Http\Controllers\Api\I_GambarKelistrikanController;
use App\Http\Controllers\Api\ImageStatusController;
use App\Http\Controllers\Api\MasterDataController;

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

        // Dropdown untuk form Transaksi & Master Data (independen & searchable)
        Route::get('/options/type-engines', [OptionController::class, 'getOptionsTypeEngine']);
        Route::get('/options/merks', [OptionController::class, 'getOptionsMerk']);
        Route::get('/options/type-chassis', [OptionController::class, 'getOptionsTypeChassis']);
        Route::get('/options/jenis-kendaraan', [OptionController::class, 'getOptionsJenisKendaraan']);

        // Dropdown untuk form Varian Body (searchable)
        Route::get('/options/master-data', [OptionController::class, 'getOptionsMasterData']);

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
        Route::post('/options/gambar-optional-by-varian', [OptionController::class, 'getGambarOptionalByVarian']);
        Route::post('/options/dependent-optionals', [OptionController::class, 'getDependentOptionals']);
        Route::get('/options/varian-body/{master_data_id}', [OptionController::class, 'getVarianBody']);
        Route::get('/options/varian-body-status', [OptionController::class, 'getVarianBodyForDropdown']);

        Route::get('/options/kelistrikan-status/{masterDataId}', [OptionController::class, 'getKelistrikanStatusByMasterData']);
        Route::post('/transaksi/{transaksi}/save', [ProsesTransaksiController::class, 'saveDraft']);

        Route::get('/options/independent-images/{masterDataId}', [OptionController::class, 'getIndependentOptions']);

        Route::middleware('is.admin')->prefix('admin')->group(function () {
            // --- MANAJEMEN USER & ROLE ---
            Route::apiResource('users', UserController::class);
            Route::get('/options/roles', [OptionController::class, 'getRoles']);

            // --- MANAJEMEN CUSTOMER ---
            Route::apiResource('customers', CustomerController::class);

            // --- MANAJEMEN GAMBAR MASTER (Utama & Optional) ---
            Route::post('/gambar-master/utama', [GambarMasterController::class, 'uploadGambarUtama']);
            Route::post('/gambar-master/optional', [GambarMasterController::class, 'uploadGambarOptional']);
            // SALAH Route::delete('/gambar-master/utama/{e_varian_body_id}', [GambarMasterController::class, 'destroyGambarUtama']);
            Route::delete('/gambar-master/optional/{e_varian_body_id}', [GambarMasterController::class, 'destroyGambarOptional']);
            Route::post('master-data/gambar-optional/{gambarOptional}/update-file', [H_GambarOptionalController::class, 'updateFile']);
            // Route hapus spesifik (jika diperlukan)
            Route::delete('/gambar-master/utama/{id}', [GambarMasterController::class, 'destroy']);

            // --- MANAJEMEN GAMBAR KELISTRIKAN (PERBAIKAN URUTAN) ---
            // PENTING: Route custom ini WAJIB di atas apiResource agar tidak dianggap sebagai ID

            // 1. Cek File (Specific)
            Route::get('/gambar-kelistrikan/check-file/{chassisId}', [I_GambarKelistrikanController::class, 'checkFileStatus']);

            // 2. Gudang File (Specific "files")
            Route::get('/gambar-kelistrikan/files', [I_GambarKelistrikanController::class, 'indexFiles']);
            Route::post('/gambar-kelistrikan/files', [I_GambarKelistrikanController::class, 'storeFile']);
            Route::delete('/gambar-kelistrikan/files/{id}', [I_GambarKelistrikanController::class, 'destroyFile']);

            // 3. Deskripsi (Specific "deskripsi")
            Route::post('/gambar-kelistrikan/deskripsi', [I_GambarKelistrikanController::class, 'storeDeskripsi']);
            Route::delete('/gambar-kelistrikan/deskripsi/{id}', [I_GambarKelistrikanController::class, 'destroyDeskripsi']);

            // 4. Helper View PDF
            Route::get('/gambar-kelistrikan/{gambarKelistrikan}/pdf', [I_GambarKelistrikanController::class, 'showPdf']);

            // 5. Resource Umum (Menangkap sisa request standard CRUD {id})
            Route::apiResource('gambar-kelistrikan', I_GambarKelistrikanController::class);
            // -----------------------------------------------------------

            // --- MANAJEMEN PARAF ---
            Route::post('/users/{user}/paraf', [ParafUploadController::class, 'uploadUserParaf']);
            Route::delete('/users/{user}/paraf', [ParafUploadController::class, 'destroyUserParaf']);
            Route::post('/customers/{customer}/paraf', [ParafUploadController::class, 'uploadCustomerParaf']);
            Route::get('/customers/{customer}/paraf', [ParafViewController::class, 'showCustomerParaf']);
            Route::get('/users/{user}/paraf', [ParafViewController::class, 'showUserParaf']);

            // --- RESOURCE LAINNYA ---
            Route::apiResource('jenis-varian', J_JenisVarianController::class)->parameters(['jenis-varian' => 'jJudulGambar']);
            Route::apiResource('gambar-optional', H_GambarOptionalController::class);

            // --- MONITORING STATUS GAMBAR ---
            Route::get('/image-status', [ImageStatusController::class, 'index']);

            // --- PDF VIEWERS ---
            Route::get('/gambar-optional/{gambarOptional}/pdf', [H_GambarOptionalController::class, 'showPdf']);
            Route::get('/gambar-utama/{gambarUtama}/paths', [GambarMasterController::class, 'showPaths']);
            Route::get('/master-gambar/view', [GambarMasterController::class, 'viewPdf']);

            // --- HELPER OPTIONS ---
            Route::get('/options/check-paket-optional/{varianBodyId}', [OptionController::class, 'checkPaketOptionalExists']);

            // --- MASTER DATA (RECYCLE BIN & CRUD) ---
            Route::delete('master-data/trash/empty', [MasterDataController::class, 'emptyTrash']);
            Route::get('master-data/trash', [MasterDataController::class, 'trash']);
            Route::post('master-data/{id}/restore', [MasterDataController::class, 'restore']);
            Route::delete('master-data/{id}/force-delete', [MasterDataController::class, 'forceDelete']);
            Route::apiResource('master-data', MasterDataController::class)->parameters(['master-data' => 'masterDatum']);

            // --- TYPE ENGINE ---
            Route::get('type-engines/trash', [TypeEngineController::class, 'trash']);
            Route::post('type-engines/{id}/restore', [TypeEngineController::class, 'restore']);
            Route::delete('type-engines/{id}/force-delete', [TypeEngineController::class, 'forceDelete']);
            Route::apiResource('type-engines', TypeEngineController::class);

            // --- MERK ---
            Route::delete('merks/trash/empty', [MerkController::class, 'emptyTrash']);
            Route::get('merks/trash', [MerkController::class, 'trash']);
            Route::post('merks/{id}/restore', [MerkController::class, 'restore']);
            Route::delete('merks/{id}/force-delete', [MerkController::class, 'forceDelete']);
            Route::apiResource('merks', MerkController::class);
            // --- TYPE CHASSIS ---
            Route::delete('type-chassis/trash/empty', [TypeChassisController::class, 'emptyTrash']);
            Route::get('type-chassis/trash', [TypeChassisController::class, 'trash']);
            Route::post('type-chassis/{id}/restore', [TypeChassisController::class, 'restore']);
            Route::delete('type-chassis/{id}/force-delete', [TypeChassisController::class, 'forceDelete']);
            Route::apiResource('type-chassis', TypeChassisController::class)->parameters(['type-chassis' => 'typeChassis']);

            // --- JENIS KENDARAAN ---
            Route::delete('jenis-kendaraan/trash/empty', [JenisKendaraanController::class, 'emptyTrash']);
            Route::get('jenis-kendaraan/trash', [JenisKendaraanController::class, 'trash']);
            Route::post('jenis-kendaraan/{id}/restore', [JenisKendaraanController::class, 'restore']);
            Route::delete('jenis-kendaraan/{id}/force-delete', [JenisKendaraanController::class, 'forceDelete']);
            Route::apiResource('jenis-kendaraan', JenisKendaraanController::class);

            // --- VARIAN BODY ---
            Route::delete('varian-body/trash/empty', [VarianBodyController::class, 'emptyTrash']);
            Route::get('varian-body/trash', [VarianBodyController::class, 'trash']);
            Route::post('varian-body/{id}/restore', [VarianBodyController::class, 'restore']);
            Route::delete('varian-body/{id}/force-delete', [VarianBodyController::class, 'forceDelete']);
            Route::apiResource('varian-body', VarianBodyController::class);
        });
        Route::post('/drawings/generate-preview', [DrawingController::class, 'generatePdf']);
        Route::get('/test-pdf-uncopyable', [Z_pdf_png_pdfController::class, 'generateUncopyablePdf']);
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
