<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DocumentCustomer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentCustomerController extends Controller
{
    /**
     * Menampilkan document customer. (GET - semua user bisa akses)
     */
    public function show(Customer $customer): JsonResponse
    {
        $document = $customer->documentCustomer;

        if (!$document) {
            return response()->json(null);
        }

        return response()->json($document);
    }

    /**
     * Menyimpan / update document customer. (POST - admin only)
     * Menggunakan POST karena ada file upload (multipart/form-data).
     */
    public function store(Request $request, Customer $customer): JsonResponse
    {
        // 1. Validasi
        $request->validate([
            'kop_surat'          => 'nullable|file|mimes:pdf|max:1024',
            'data_umum'          => 'nullable|file|mimes:pdf|max:1024',
            'tdp_files'          => 'nullable|array|max:20',
            'tdp_files.*'        => 'file|mimes:pdf|max:1024',
            'tdp_masa_berlaku'   => 'required|date',
            'permohonan_skrb'    => 'nullable|string|max:255',
            'permohonan_rekom'   => 'nullable|string|max:255',
            'alamat_permohonan'  => 'nullable|string|max:255',
            'bidang_usaha'       => 'nullable|string|max:255',
            'alamat_lengkap'     => 'nullable|string|max:1000',
            // Flag untuk menandakan field mana yang dikirim (untuk partial update)
            'remove_kop_surat'   => 'nullable|boolean',
            'remove_data_umum'   => 'nullable|boolean',
        ]);

        $disk = Storage::disk('customer-documents');
        $folder = (string) $customer->id;
        $now = Carbon::now()->format('Ymd-His');

        // 2. Ambil atau buat document baru
        $document = $customer->documentCustomer ?? new DocumentCustomer(['customer_id' => $customer->id]);

        // 3. Proses Kop Surat
        if ($request->hasFile('kop_surat')) {
            // Hapus file lama jika ada
            if ($document->kop_surat && $disk->exists($document->kop_surat)) {
                $disk->delete($document->kop_surat);
            }
            $fileName = "{$customer->id}-1-kop-{$now}.pdf";
            $path = $request->file('kop_surat')->storeAs($folder, $fileName, 'customer-documents');
            $document->kop_surat = $path;
        } elseif ($request->boolean('remove_kop_surat')) {
            if ($document->kop_surat && $disk->exists($document->kop_surat)) {
                $disk->delete($document->kop_surat);
            }
            $document->kop_surat = null;
        }

        // 4. Proses Data Umum
        if ($request->hasFile('data_umum')) {
            if ($document->data_umum && $disk->exists($document->data_umum)) {
                $disk->delete($document->data_umum);
            }
            $fileName = "{$customer->id}-2-data-{$now}.pdf";
            $path = $request->file('data_umum')->storeAs($folder, $fileName, 'customer-documents');
            $document->data_umum = $path;
        } elseif ($request->boolean('remove_data_umum')) {
            if ($document->data_umum && $disk->exists($document->data_umum)) {
                $disk->delete($document->data_umum);
            }
            $document->data_umum = null;
        }

        // 5. Proses TDP Files (append baru, pertahankan yang lama)
        if ($request->hasFile('tdp_files')) {
            $existingTdp = $document->tdp_files ?? [];
            $nextIndex = count($existingTdp) + 1;

            foreach ($request->file('tdp_files') as $file) {
                if ($nextIndex > 20) break; // max 20 file

                $fileName = "{$customer->id}-tdp{$nextIndex}-{$now}.pdf";
                $path = $file->storeAs($folder, $fileName, 'customer-documents');
                $existingTdp[] = [
                    'path' => $path,
                    'uploaded_at' => Carbon::now()->toIso8601String(),
                ];
                $nextIndex++;
            }

            $document->tdp_files = $existingTdp;
        }

        // 6. Proses TDP Masa Berlaku
        if ($request->has('tdp_masa_berlaku')) {
            $document->tdp_masa_berlaku = $request->input('tdp_masa_berlaku');
        }

        // 7. Proses Format Penomoran
        $textFields = ['permohonan_skrb', 'permohonan_rekom', 'alamat_permohonan', 'bidang_usaha', 'alamat_lengkap'];
        foreach ($textFields as $field) {
            if ($request->has($field)) {
                $document->$field = $request->input($field);
            }
        }

        // 8. Simpan
        $document->save();

        return response()->json($document->fresh(), $document->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Menghapus seluruh document customer beserta file PDF. (DELETE - admin only)
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $document = $customer->documentCustomer;

        if (!$document) {
            return response()->json(['message' => 'Document tidak ditemukan.'], 404);
        }

        $disk = Storage::disk('customer-documents');
        $folder = (string) $customer->id;

        // Hapus seluruh folder customer dari storage
        if ($disk->exists($folder)) {
            $disk->deleteDirectory($folder);
        }

        // Hapus record dari database
        $document->delete();

        return response()->json(null, 204);
    }

    /**
     * Menghapus satu file TDP berdasarkan index. (DELETE - admin only)
     * TDP 1 (index 0) tidak boleh dihapus.
     */
    public function deleteTdpFile(Customer $customer, int $index): JsonResponse
    {
        $document = $customer->documentCustomer;

        if (!$document) {
            return response()->json(['message' => 'Document tidak ditemukan.'], 404);
        }

        $tdpFiles = $document->tdp_files ?? [];

        if ($index < 1 || $index >= count($tdpFiles)) {
            return response()->json(['message' => 'Index TDP tidak valid. TDP 1 tidak dapat dihapus.'], 422);
        }

        $disk = Storage::disk('customer-documents');

        // Hapus file dari storage
        $fileToDelete = $tdpFiles[$index];
        if (isset($fileToDelete['path']) && $disk->exists($fileToDelete['path'])) {
            $disk->delete($fileToDelete['path']);
        }

        // Hapus dari array dan re-index
        array_splice($tdpFiles, $index, 1);

        // Re-rename file agar urutan tetap konsisten
        $now = Carbon::now()->format('Ymd-His');
        $folder = (string) $customer->id;
        $renamedFiles = [];

        foreach ($tdpFiles as $i => $tdp) {
            $newNumber = $i + 1;
            $newFileName = "{$customer->id}-tdp{$newNumber}-{$now}.pdf";
            $newPath = "{$folder}/{$newFileName}";

            if (isset($tdp['path']) && $disk->exists($tdp['path']) && $tdp['path'] !== $newPath) {
                $disk->move($tdp['path'], $newPath);
            }

            $renamedFiles[] = [
                'path' => $newPath,
                'uploaded_at' => $tdp['uploaded_at'] ?? Carbon::now()->toIso8601String(),
            ];
        }

        $document->tdp_files = $renamedFiles;
        $document->save();

        return response()->json($document->fresh());
    }

    /**
     * Mengganti satu file TDP berdasarkan index. (POST - admin only)
     */
    public function replaceTdpFile(Request $request, Customer $customer, int $index): JsonResponse
    {
        $request->validate([
            'tdp_file' => 'required|file|mimes:pdf|max:1024',
        ]);

        $document = $customer->documentCustomer;

        if (!$document) {
            return response()->json(['message' => 'Document tidak ditemukan.'], 404);
        }

        $tdpFiles = $document->tdp_files ?? [];

        if ($index < 0 || $index >= count($tdpFiles)) {
            return response()->json(['message' => 'Index TDP tidak valid.'], 422);
        }

        $disk = Storage::disk('customer-documents');
        $folder = (string) $customer->id;
        $now = Carbon::now()->format('Ymd-His');

        // Hapus file lama
        $oldFile = $tdpFiles[$index];
        if (isset($oldFile['path']) && $disk->exists($oldFile['path'])) {
            $disk->delete($oldFile['path']);
        }

        // Upload file baru
        $number = $index + 1;
        $fileName = "{$customer->id}-tdp{$number}-{$now}.pdf";
        $path = $request->file('tdp_file')->storeAs($folder, $fileName, 'customer-documents');

        $tdpFiles[$index] = [
            'path' => $path,
            'uploaded_at' => Carbon::now()->toIso8601String(),
        ];

        $document->tdp_files = $tdpFiles;
        $document->save();

        return response()->json($document->fresh());
    }

    /**
     * Serve file PDF untuk preview. (GET - semua user bisa akses)
     */
    public function viewPdf(Customer $customer, string $type, ?int $index = null): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $document = $customer->documentCustomer;

        if (!$document) {
            return response()->json(['message' => 'Document tidak ditemukan.'], 404);
        }

        $disk = Storage::disk('customer-documents');
        $path = null;

        switch ($type) {
            case 'kop':
                $path = $document->kop_surat;
                break;
            case 'data':
                $path = $document->data_umum;
                break;
            case 'tdp':
                $tdpFiles = $document->tdp_files ?? [];
                if ($index !== null && isset($tdpFiles[$index]['path'])) {
                    $path = $tdpFiles[$index]['path'];
                }
                break;
        }

        if (!$path || !$disk->exists($path)) {
            return response()->json(['message' => 'File tidak ditemukan.'], 404);
        }

        return $disk->response($path, null, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
