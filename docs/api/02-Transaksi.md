# Transaksi

API untuk mengelola data Transaksi di dalam sistem. 
- **Akses:** Membutuhkan autentikasi (Bearer Token).

## 1. Transaksi CRUD
- `GET /api/transaksi` - Mendapatkan daftar transaksi (list).
- `POST /api/transaksi` - Membuat transaksi baru.
- `GET /api/transaksi/{id}` - Mendapatkan detail sebuah transaksi.
- `PUT/PATCH /api/transaksi/{id}` - Memperbarui data transaksi.
- `DELETE /api/transaksi/{id}` - Menghapus data transaksi.

## 2. Proses Transaksi Tambahan
Endpoint untuk mengelola alur state/data pada transaksi secara spesifik.

- `POST /api/transaksi/{id}/detail`
  - **Deskripsi:** Menyimpan informasi detail untuk transaksi tersebut.
  
- `POST /api/transaksi/{id}/proses`
  - **Deskripsi:** Memproses transaksi (mengubah status/tahap transaksi) atau melakukan *preview* PDF.
  - *Payload Keys*: `pemeriksa_id`, `pihak_penyetujuan`, `varian_body_ids`, `judul_gambar_ids`, `h_gambar_optional_ids`, `i_gambar_kelistrikan_id`, `ordered_independent_ids`, `deskripsi_optional`, `desc_space`, `data_gambar_utama`.
  - **Catatan Penting**: Terdapat parameter wajib `aksi`. Jika `aksi: 'preview'`, tambahkan `preview_page` dan respons berupa Biner (`application/pdf`). Jika `aksi: 'proses'`, respons mengembalikan *zip* biner atau status proses selesai.

- `POST /api/transaksi/{id}/save`
  - **Deskripsi:** Menyimpan transaksi ke dalam status Draft (Save as Draft).
  - *Payload Keys*: Sama seperti proses di atas, namun tanpa parameter `aksi`. Menyimpan state seperti `pemeriksa_id`, `pihak_penyetujuan`, `jumlah_gambar`, `ordered_independent_ids`, dll.

---

### Contoh Hasil Response (JSON)

**Response GET Detail Transaksi (`/api/transaksi/0126-0001`)**:
```json
{
    "id": "0126-0001",
    "master_data_id": 42,
    "f_pengajuan_id": 2,
    "pdf_date_type": "today",
    "customer_id": 203,
    "user_id": 8,
    "created_at": "2026-01-05T09:04:45.000000Z",
    "updated_at": "2026-01-05T09:04:45.000000Z"
}
```
