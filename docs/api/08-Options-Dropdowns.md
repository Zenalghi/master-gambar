# Options & Dropdowns

Berisi API yang dikhususkan untuk memanggil daftar *Options* atau dropdown iEndpoint ini bersifat spesifik untuk menghasilkan format *key-value* (id dan label) ringan tanpa paginasi penuh. Sangat berguna untuk di-*bind* langsung ke widget dropdown/select.

> **Catatan Penting (Query Parameters):**  
> Beberapa endpoint di bawah ini (seperti `customers`, `master-data`, dan `varian-body-status`) mendukung _query parameter_ opsional dari frontend:
> - `?search={keyword}` : Untuk mencari/memfilter list berdasarkan nama secara spesifik (digunakan di fitur Autocomplete Dropdown Flutter).
> - `?master_data_id={id}` : (Khusus Varian Body) Untuk memfilter varian body berdasarkan master data tertentu.

- `GET /api/options/customers`
  - **Deskripsi:** List Customer. Format JSON biasanya di-*mapping* frontend dari *key* `nama_pt` ke `name`.
- `GET /api/options/users`
  - **Deskripsi:** List User/Pengguna.
- `GET /api/options/master-data`
  - **Deskripsi:** List Judul/Data Master. *Backend* sering memformat kolom *name* menjadi gabungan (contoh: "Type / Merk / Varian").
- `GET /api/options/type-engines`
  - **Deskripsi:** List Tipe Mesin.
- `GET /api/options/merks/{engineId}`
  - **Deskripsi:** List Merk berdasarkan Engine ID.
- `GET /api/options/type-chassis/{merkId}`
  - **Deskripsi:** List Tipe Chassis berdasarkan Merk ID.
- `GET /api/options/jenis-kendaraans/{chassisId}`
  - **Deskripsi:** List Jenis Kendaraan berdasarkan Chassis ID.
- `GET /api/options/jenis-pengajuans`
  - **Deskripsi:** List Jenis Pengajuan.
- `GET /api/options/varian-body-status`
  - **Deskripsi:** List Varian Body dengan informasi status tambahannya.
- `POST /api/options/gambar-optional-by-varian` - Gambar optional yang dimiliki suatu varian.
- `POST /api/options/dependent-optionals` - Optional yang dependen berdasarkan kondisi form.
- `GET /api/options/kelistrikan-status/{masterDataId}` - Mengecek kelistrikan status dari Master Data.
- `GET /api/options/independent-images/{masterDataId}` - Gambar independen milik Master Data.
- `GET /api/options/gambar-optional` - Opsi Gambar optional umum.
- `GET /api/options/judul-gambar` - Opsi Judul Gambar.
- `GET /api/admin/options/check-paket-optional/{varianBodyId}` - Cek paket optional eksis (Admin).

---

### Contoh Hasil Response (JSON Array)

Kebanyakan endpoint `/options/*` akan mengembalikan sebuah Array berisi key-value label (untuk ditampilkan di Dropdown) dan ID-nya. Contoh:

**Response GET Options Roles (`/api/options/roles`)**:
```json
[
    {
        "id": 1,
        "name": "admin",
        "created_at": "2025-11-29T09:12:49.000000Z",
        "updated_at": "2025-11-29T09:12:49.000000Z"
    },
    {
        "id": 2,
        "name": "drafter",
        "created_at": "2025-11-29T09:12:49.000000Z",
        "updated_at": "2025-11-29T09:12:49.000000Z"
    }
]
```
