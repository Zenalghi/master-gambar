# Surat Keputusan Rancang Bangun (SKRB)

Endpoint untuk mengelola SKRB dan Setting terkait. 
- **Akses:** Auth (Bearer Token)

## 1. SKRB Setting
- `GET /api/skrb-setting` - Melihat konfigurasi default / pengaturan SKRB.
- `POST /api/skrb-setting` - Memperbarui konfigurasi SKRB.

## 2. Permohonan & Detail SKRB (CRUD Utama)
- `GET /api/skrbs`
  - **Deskripsi:** Menampilkan daftar seluruh SKRB (dengan format paginasi JSON).
- `POST /api/skrbs`
  - **Deskripsi:** Membuat SKRB baru.
  - Terdapat dua cara (alur) pembuatan:
    1. **Via ID Transaksi** (Otomatis): Menggunakan payload `transaksi_id` dan `nomor_urut_manual` (opsional). Sistem akan menarik data *customer*, kendaraan, dan jenis pengajuan dari transaksi tersebut.
    2. **Via Manual**: Menggunakan payload `customer_id`, `master_data_id`, `jenis_pengajuan_id`, dan `nomor_urut_manual` (opsional).
- `GET /api/skrbs/{id}` - Melihat detail SKRB.
- `PUT/PATCH /api/skrbs/{id}` - Memperbarui SKRB.
- `DELETE /api/skrbs/{id}` - Menghapus data SKRB.

## 3. Fitur Spesifik SKRB
- `GET /api/skrbs/available-transactions`
  - **Deskripsi:** Mendapatkan daftar transaksi yang bisa dibuatkan SKRB (belum memiliki SKRB).
- `GET /api/skrbs/preview-id`
  - **Deskripsi:** Mengecek/preview ID SKRB sistem berikutnya untuk seorang *Customer*. Butuh query parameter `?customer_id=`.
- `GET /api/skrbs/by-transaksi/{transaksiId}`
  - **Deskripsi:** Mengecek apakah SKRB sudah dibuat untuk Transaksi tertentu. Respons biasanya berupa `{"exists": true, "data": {...}}`.
- `GET /api/skrbs/{id}/storage-info` - Informasi file storage pada SKRB.
- `POST /api/skrbs/{id}/generate-gambar` - Menggenerate / render gambar SKRB.
- `POST /api/skrbs/{id}/reset-files` - Mereset file yang menempel pada SKRB.
- `POST /api/skrbs/{id}/upload/{key}` - Upload file spesifik (menggunakan parameter {key}).
- `GET /api/skrbs/{id}/preview/{key}` - View/Preview file spesifik (berdasarkan {key}).
- `POST /api/skrbs/{id}/merge` - Melakukan merge dokumen/gambar SKRB menjadi satu.

## 4. History SKRB
- `GET /api/skrb-histories/{historyId}/view` - View file dari history.
- `GET /api/skrb-histories/{historyId}/download` - Download file dari history.
- `DELETE /api/skrb-histories/{historyId}` - Menghapus 1 entri history SKRB.
- `DELETE /api/skrbs/{id}/histories` - Menghapus semua history dari SKRB terkait.

---

### Contoh Hasil Response (JSON)

**Response GET Detail SKRB (`/api/skrbs/24`)**:
```json
{
    "id": 24,
    "id_skrb": "01/VCLAS-SKRB/VIII/2026",
    "transaksi_id": "0526-0003",
    "master_data_id": null,
    "jenis_pengajuan_id": null,
    "customer_id": 2,
    "bulan_tahun": "08-2026",
    "nomor_urut": 1,
    "is_tdp_updated_by_admin": false,
    "foto_copy_skrb": "test foto copy text",
    "snapshot_documents": {
        "merk": "ISUZU",
        "sut_file": "sut-162/162-sut-20260730-091152.pdf",
        "tdp_files": [
            {
                "path": "docus-2/2-tdp1-20260730-091757.pdf",
                "size": 378784,
                "uploaded_at": "2026-07-30T09:17:57+07:00"
            }
        ],
        "jenis_tipe": "MOBIL ANGKUTAN BARANG",
        "customer_pj": "Kwan Pha Jie",
        "type_engine": "EURO 4",
        "bidang_usaha": "Karoseri Kendaraan Bermotor",
        "manual_jenis": "MOBIL ANGKUTAN BARANG",
        "type_chassis": "NLR85U-HAYIN1 (4X2) M/T (VARIAN KESATU)",
        "customer_name": "ADI JAYA MAKMUR",
        "gambar_status": "ready"
    },
    "custom_files": {
        "5": "skrb-0526-0003/0526-0003-Surat_Pernyataan.pdf"
    },
    "fase": 3,
    "created_at": "2026-08-02T11:34:22.000000Z",
    "updated_at": "2026-08-12T03:58:03.000000Z"
}
```

*Note: Response untuk preview/download PDF (misal `GET /api/skrbs/24/preview/sut_file`) akan mengembalikan file biner berformat `application/pdf`.*
