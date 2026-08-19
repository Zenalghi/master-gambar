# Paraf Management

API untuk mengelola file Tanda Tangan / Paraf milik User (Drafter/Pemeriksa) maupun Customer. 
- **Akses:** Khusus Admin untuk proses Upload/Delete, semua user/admin bisa melakukan proses view.

## 1. Paraf User (Drafter / Pemeriksa)
- `POST /api/admin/users/{userId}/paraf`
  - **Deskripsi:** Upload paraf milik User. Wajib `multipart/form-data`.
  - **Payload Key:** `paraf` (File gambar).
- `DELETE /api/admin/users/{userId}/paraf`
  - **Deskripsi:** Menghapus paraf milik User.
- `GET /api/admin/users/{userId}/paraf`
  - **Deskripsi:** Melihat/menampilkan (View) paraf milik User.

## 2. Paraf Customer
- `POST /api/admin/customers/{customerId}/paraf`
  - **Deskripsi:** Upload paraf untuk Customer/Instansi tertentu. Wajib `multipart/form-data`.
  - **Payload Key** (Pilih salah satu berdasarkan role yang ingin diupload):
    - `paraf_pj` : Untuk tanda tangan Penanggung Jawab.
    - `paraf_drafter` : Untuk tanda tangan Drafter instansi.
    - `paraf_pemeriksa` : Untuk tanda tangan Pemeriksa instansi.
- `GET /api/admin/customers/{customerId}/paraf`
  - **Deskripsi:** Menampilkan paraf dari Customer tersebut (PJ).
- `GET /api/admin/customers/{customerId}/paraf-drafter`
  - **Deskripsi:** Menampilkan paraf Drafter yang ditugaskan kepada Customer.
- `GET /api/admin/customers/{customerId}/paraf-pemeriksa`
  - **Deskripsi:** Menampilkan paraf Pemeriksa yang ditugaskan kepada Customer.

---

### Contoh Hasil Response (Biner / Image)

Semua endpoint dengan *method* `GET` pada halaman ini (seperti `GET /api/admin/customers/1/paraf`) berfungsi sebagai **Viewer** gambar tanda tangan (paraf). 

- **Response Format:** Biner
- **Content-Type:** `image/png` (atau tipe ekstensi gambar aslinya)

Anda bisa merender URL ini secara langsung di dalam _widget Image_ pada aplikasi Flutter:
```dart
// Pada Flutter
Image.network(
  '$baseUrl/admin/customers/1/paraf?v=$timestamp',
  headers: {'Authorization': 'Bearer $token'},
)
```
