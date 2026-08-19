# Gambar Master

Endpoint ini menangani unggahan, pengelolaan, dan view/rendering file (PDF) untuk Gambar Utama, Optional, dan Kelistrikan.

## 1. Gambar Utama (Admin Only)
- `POST /api/admin/gambar-master/utama` - Mengunggah/menyimpan gambar utama. Wajib `multipart/form-data`.
  - *Payload Keys*: `master_data_id`, `varian_body`, `gambar_utama`, `gambar_terurai`, `gambar_kontruksi` (Semua file berformat PDF).
- `DELETE /api/admin/gambar-master/utama/{id}` - Menghapus file gambar utama.
- `GET /api/admin/gambar-utama/{id}/paths` - Melihat path file gambar utama.

## 2. Gambar Optional (Admin Only)
Semua upload/update file gambar optional wajib menggunakan `multipart/form-data`.
- `POST /api/admin/gambar-master/optional` - Mengunggah gambar optional.
- `DELETE /api/admin/gambar-master/optional/{e_varian_body_id}` - Menghapus file gambar optional berdasarkan ID varian body.
- `GET /api/admin/gambar-optional` - List Gambar Optional (Resource).
- `POST /api/admin/gambar-optional` - Tambah Gambar Optional.
  - *Payload Keys*: `tipe`, `deskripsi`, `gambar_optional` (file), `master_data_id` (jika independen), atau `g_gambar_utama_id` (jika dependen).
- `GET /api/admin/gambar-optional/{id}` - Detail Gambar Optional.
- `PUT/PATCH /api/admin/gambar-optional/{id}` - Update data.
- `DELETE /api/admin/gambar-optional/{id}` - Hapus data.
- `POST /api/admin/master-data/gambar-optional/{id}/update-file` - Memperbarui file fisik pada data gambar optional.
  - *Payload Keys*: `deskripsi`, `gambar_optional` (file).
- `GET /api/admin/gambar-optional/{id}/pdf` - View PDF gambar optional.

## 3. Gambar Kelistrikan (Admin Only)
Semua form yang mengirimkan file biner di bawah ini wajib menggunakan `multipart/form-data`.
- `GET /api/admin/gambar-kelistrikan/check-file/{chassisId}` - Mengecek ketersediaan file berdasarkan ID Chassis.
- `GET /api/admin/gambar-kelistrikan/files` - Mendapatkan daftar file di gudang penyimpanan kelistrikan.
- `POST /api/admin/gambar-kelistrikan/files` - Mengupload file gambar kelistrikan baru.
  - *Payload Keys*: `a_type_engine_id`, `b_merk_id`, `c_type_chassis_id`, `gambar_kelistrikan` (file).
- `DELETE /api/admin/gambar-kelistrikan/files/{id}` - Menghapus file kelistrikan.
- `POST /api/admin/gambar-kelistrikan/deskripsi` - Menyimpan informasi/deskripsi kelistrikan beserta file.
  - *Payload Keys*: `master_data_id`, `deskripsi`, `gambar_kelistrikan` (file opsional).
- `DELETE /api/admin/gambar-kelistrikan/deskripsi/{id}` - Menghapus deskripsi.
- `GET /api/admin/gambar-kelistrikan/{id}/pdf` - Helper view PDF kelistrikan.
- `GET/POST/PUT/DELETE /api/admin/gambar-kelistrikan` - Standard CRUD untuk resource ini.

## 4. Master Gambar & Preview (Viewers)
- `GET /api/admin/master-gambar/view` - View PDF Master Gambar secara umum.
- `POST /api/drawings/generate-preview` - (Tersedia Publik/Auth tergantung rute) Generate PDF Preview dari request.

---

### Contoh Pengiriman Payload (Multipart/Form-Data)

**1. Gambar Utama (`POST /api/admin/gambar-master/utama`)**:
Gambar utama memiliki fleksibilitas dalam pengiriman file. Form data ini bisa mengunggah hingga 3 bagian sekaligus: Gambar Utama, Gambar Terurai, dan Gambar Kontruksi. Anda dapat mengirimkan ketiga bagian tersebut, atau hanya salah satu saja (misalnya hanya mengupdate gambar utama).

*Contoh Request (mengunggah lengkap ke-3 bagian):*
```http
POST /api/admin/gambar-master/utama HTTP/1.1
Host: localhost:8000
Content-Type: multipart/form-data; boundary=---Boundary123
Authorization: Bearer {token}

-----Boundary123
Content-Disposition: form-data; name="master_data_id"

12
-----Boundary123
Content-Disposition: form-data; name="varian_body"

FIX SIDE
-----Boundary123
Content-Disposition: form-data; name="gambar_utama"; filename="utama.pdf"
Content-Type: application/pdf

(Binary Data PDF Gambar Utama)
-----Boundary123
Content-Disposition: form-data; name="gambar_terurai"; filename="terurai.pdf"
Content-Type: application/pdf

(Binary Data PDF Terurai)
-----Boundary123
Content-Disposition: form-data; name="gambar_kontruksi"; filename="kontruksi.pdf"
Content-Type: application/pdf

(Binary Data PDF Kontruksi)
-----Boundary123--
```

*Contoh Request (hanya mengunggah Gambar Utama saja):*
```http
POST /api/admin/gambar-master/utama HTTP/1.1
Content-Type: multipart/form-data; boundary=---Boundary456

-----Boundary456
Content-Disposition: form-data; name="master_data_id"

12
-----Boundary456
Content-Disposition: form-data; name="varian_body"

FIX SIDE
-----Boundary456
Content-Disposition: form-data; name="gambar_utama"; filename="utama.pdf"
Content-Type: application/pdf

(Binary Data PDF Gambar Utama)
-----Boundary456--
```

### Contoh Hasil Response (JSON & Biner)

**1. Response Sukses Gambar Utama**:
```json
{
    "id": 41,
    "e_varian_body_id": 56,
    "path_gambar_utama": "13/56/56_1778725383_gambar-utama.pdf",
    "path_gambar_terurai": "13/56/56_1778725383_gambar-terurai.pdf",
    "path_gambar_kontruksi": "13/56/56_1778725383_gambar-kontruksi.pdf",
    "created_at": "2025-12-18T16:15:58.000000Z",
    "updated_at": "2026-05-14T02:23:03.000000Z"
}
```

**2. Response GET Gambar Optional (`/api/admin/gambar-optional/2`)**:
```json
{
    "id": 2,
    "master_data_id": 6,
    "tipe": "independen",
    "e_varian_body_id": null,
    "g_gambar_utama_id": null,
    "path_gambar_optional": "6/independen/2.pdf",
    "deskripsi": "GAMBAR DETAIL SPARKBOARD,PERISAI KOLONG & RUP",
    "created_at": "2025-12-18T05:47:18.000000Z",
    "updated_at": "2026-02-11T08:48:57.000000Z"
}
```

*Note: Untuk endpoint dengan akhiran `/pdf` (seperti `GET /api/admin/gambar-optional/{id}/pdf` atau `/api/admin/master-gambar/view`) akan langsung mengembalikan representasi file biner (`Content-Type: application/pdf`).*
