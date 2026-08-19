# API Documentation - Overview

Dokumentasi ini berisi daftar API yang tersedia dalam aplikasi `master_gambar`. Dokumentasi dibagi menjadi beberapa bagian berdasarkan konteks fungsionalitasnya untuk memudahkan integrasi dan pengembangan, termasuk penggunaan via Postman.

## Base URL
Semua endpoint dalam dokumentasi ini memiliki prefix `/api` (misal: `http://localhost:8000/api`).

## Authentication & Authorization
Sebagian besar endpoint memerlukan autentikasi. Endpoint yang terproteksi wajib menyertakan token Bearer di dalam header HTTP:
`Authorization: Bearer {token}`

Beberapa endpoint dengan prefix `/admin` juga mensyaratkan user memiliki role Admin.

## Daftar Dokumentasi
1. [Authentication](00-Authentication.md)
2. [Master Data](01-Master-Data.md)
3. [Transaksi](02-Transaksi.md)
4. [SKRB](03-SKRB.md)
5. [Customer & Dokumen](04-Customer.md)
6. [User & Roles](05-User-Roles.md)
7. [Gambar (Utama, Optional, Kelistrikan)](06-Gambar-Master.md)
8. [Paraf](07-Paraf.md)
9. [Options & Dropdowns](08-Options-Dropdowns.md)
10. [Lain-lain & Utility](09-Lain-Lain.md)

## Penggunaan di Aplikasi Flutter
Pada aplikasi Flutter (`master_gambar`), integrasi API di-*handle* secara tersentralisasi melalui `lib/data/providers/api_client.dart` dan `ApiClient`. 

### 1. Setup URL & Headers
- **Base URL Dinamis:** 
  Pada `lib/main.dart`, jika di-build untuk production (`kReleaseMode && kIsWeb`), base URL akan otomatis membaca dari domain browser saat ini (`${Uri.base.origin}/api`). Jika *development*, menggunakan IP localhost/local domain.
- **Timeouts:** Request akan otomatis *time out* jika melebih 15 detik (`connectTimeout: 15s`, `receiveTimeout: 15s`).
- **Headers Wajib:**
  ```http
  Accept: application/json
  Content-Type: application/json
  Authorization: Bearer {token_dari_shared_preferences}
  ```

### 2. Standar Parameter Pagination & Sorting
Untuk *endpoint* yang menampilkan data list/tabel (seperti Master Data), backend mensyaratkan 5 parameter *query* berikut yang dikirim dari Flutter:
- `page`: Nomor halaman aktif (misal: 1, 2)
- `perPage`: Jumlah data per halaman (contoh: 10)
- `search`: Kata kunci pencarian (string)
- `sortBy`: Nama kolom yang akan di-sorting
- `sortDirection`: Arah sorting (`asc` atau `desc`)

### 3. Standar Handling Error (JSON)
Backend mengembalikan JSON khusus apabila terjadi *error* (seperti validasi). Flutter (DioException) akan membaca response dengan urutan prioritas:
1. `data['errors']['general'][0]` (jika ada error *array* validasi secara umum).
2. `data['message']` (untuk pesan error *throw* bawaan server).

### 4. Fetch File Biner / PDF
Ketika Flutter memerlukan *preview* PDF atau mendownload gambar melalui _endpoint API_, *method* GET wajib menggunakan konfigurasi khusus:
```dart
// Mencegah konversi ke string yang merusak file
options: Options(responseType: ResponseType.bytes)
```
Data biner (_Uint8List_) kemudian langsung bisa dimasukkan ke widget PDF Viewer atau widget `Image.memory`.

---
Untuk men-generate *sample* JSON asli dari database Anda sewaktu-waktu, telah dibuatkan script sementara `get_examples.php` di _root_ server Laragon Anda. Anda bisa menjalankannya via terminal lokal:
```bash
php get_examples.php
```
