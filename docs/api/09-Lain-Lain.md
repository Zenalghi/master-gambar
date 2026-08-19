# Lain-lain & Utility

Endpoint pendukung (utility) untuk maintenance dan testing di sistem.

## 1. Garbage Collector (Admin Only)
Berfungsi untuk menghapus secara permanen (membersihkan) file-file di server (seperti gambar yang sudah terputus dari database) agar tidak memenuhi storage.
- `POST /api/admin/garbage-collector/utama` - Membersihkan file orphaned Gambar Utama.
- `POST /api/admin/garbage-collector/optional` - Membersihkan file orphaned Gambar Optional.
- `POST /api/admin/garbage-collector/kelistrikan` - Membersihkan file orphaned Kelistrikan.
- `POST /api/admin/garbage-collector/all` - Eksekusi pembersihan semuanya.

## 2. Monitoring (Admin Only)
- `GET /api/admin/image-status` - Mengambil log/laporan status kelengkapan dari seluruh file gambar di sistem.

## 3. PDF Generator Testing (Public / Development Use)
- `GET /api/test-skrb-pdf`
  - **Deskripsi:** Merender dan menampilkan (inline) output dari template PDF SKRB untuk proses testing tanpa harus submit form.
- `GET /api/test-pdf-uncopyable`
  - **Deskripsi:** Generate PDF dengan mode gambar/watermark/restriction sehingga tidak bisa disalin.

---

### Contoh Hasil Response

**1. Garbage Collector (`POST /api/admin/garbage-collector/utama`)**:
- **Format Response**: JSON
- Biasanya mengembalikan status sukses beserta rincian jumlah file yang telah dihapus:
```json
{
    "message": "Garbage Collector berhasil dijalankan.",
    "deleted_files_count": 12
}
```

**2. PDF Generator Testing (`GET /api/test-skrb-pdf`)**:
- **Format Response**: Biner
- **Content-Type**: `application/pdf`
- Anda bisa mengakses/merender URL ini langsung di web browser, atau di Flutter lewat widget viewer PDF.
