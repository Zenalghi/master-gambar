# Customer & Dokumen

API untuk mengelola entitas Customer dan dokumen pendukung terkait customer.

## 1. Customer (Admin Only)
Endpoint untuk mengelola Data Customer, **membutuhkan hak akses Admin**.
- `GET /api/admin/customers` - List semua Customer
- `POST /api/admin/customers` - Menambah Customer
- `GET /api/admin/customers/{id}` - Melihat detail Customer
- `PUT/PATCH /api/admin/customers/{id}` - Memperbarui data Customer
- `DELETE /api/admin/customers/{id}` - Menghapus Customer

## 2. Document Customer (Write Access: Admin)
Pengelolaan dokumen milik Customer. **Hanya Admin** yang bisa melakukan manipulasi (CRUD), sementara User biasa hanya memiliki hak baca (READ).
- `POST /api/admin/customers/{id}/document` - Menambah/memperbarui dokumen.
  - **Wajib menggunakan `multipart/form-data`**.
  - *Payload Keys*:
    - `kop_surat_file` (File) & `remove_kop_surat_file` (1/0)
    - `data_umum_file` (File) & `remove_data_umum_file` (1/0)
    - `tdp_files[]` (Array of Files)
    - Data string lainnya: `tdp_masa_berlaku`, `permohonan_skrb`, `permohonan_rekom`, `alamat_permohonan`, `bidang_usaha`, `alamat_lengkap`.
- `DELETE /api/admin/customers/{id}/document` - Menghapus seluruh dokumen utama (Soft delete/reset).
- `DELETE /api/admin/customers/{id}/document/tdp/{index}` - Menghapus file TDP spesifik (berdasarkan index array).
- `POST /api/admin/customers/{id}/document/tdp/{index}/replace` - Mengganti file TDP (berdasarkan index).

## 3. Document Customer (Read Access: All Users)
Endpoint ini bisa diakses oleh semua user yang telah login (termasuk non-admin).
- `GET /api/customers/{id}/document` - Mendapatkan informasi/dokumen milik customer.
- `GET /api/customers/{id}/document/pdf/{type}/{index?}` - Melakukan view PDF dari dokumen customer berdasarkan tipe, dan opsi index.

---

### Contoh Hasil Response (JSON)

**Response GET Customer (`/api/admin/customers/1`)**:
```json
{
    "id": 1,
    "nama_pt": "PT. ANTIKA RAYA",
    "pj": "Yanuar",
    "signature_pj": "1/1.png",
    "created_at": "2025-11-29T09:12:51.000000Z",
    "updated_at": "2025-12-10T01:24:52.000000Z",
    "nama_drafter": null,
    "signature_drafter": null,
    "nama_pemeriksa": null,
    "signature_pemeriksa": null,
    "jabatan": null
}
```

*Note: Response untuk dokumen PDF (misal `GET /api/customers/1/document/pdf/tdp/0`) akan mengembalikan `Content-Type: application/pdf`.*
