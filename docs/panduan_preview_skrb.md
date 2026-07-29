# Panduan Pengetesan dan Preview Hasil SKRB Template PDF

Dokumen ini berisi panduan untuk melihat atau menghasilkan file PDF dari template `App\Support\SKRB_template.php` untuk keperluan pengetesan visual, yang akan disimpan langsung di direktori komputer Anda (`C:\Nova\Rekayasa\test`).

---

## 🚀 Cara 1 (TERCEPAT): Via Perintah Terminal Tanpa Postman & Tanpa Login

Ini adalah cara paling mudah dan langsung menghasilkan file ke direktori tujuan:

1. Buka terminal (CMD / PowerShell / Terminal di VS Code) di folder project backend:
   ```bash
   cd C:\laragon\www\master-gambar
   ```
2. Jalankan perintah Artisan berikut:
   ```bash
   php artisan skrb:test
   ```
3. **Selesai!** File hasil generate dari `SKRB_template.php` akan langsung dibuat dan disimpan di:
   👉 **`C:\Nova\Rekayasa\test\preview_skrb.pdf`**

> **Catatan:** Perintah ini sudah saya eksekusi sekali untuk Anda! Saat ini file `preview_skrb.pdf` sudah siap dan bisa langsung Anda lihat di folder `C:\Nova\Rekayasa\test`. Kapanpun Anda mengubah layout kode, cukup jalankan ulang `php artisan skrb:test` untuk menimpanya.

---

## 🌐 Cara 2: Via Postman atau Web Browser (Endpoint Public Tanpa Login)

Jika Anda lebih nyaman melakukan testing API melalui HTTP request menggunakan Postman atau langsung membuka di tab Browser:

### 1. Via Web Browser
1. Pastikan server lokal aktif (misalnya via Laragon atau `php artisan serve`).
2. Buka URL berikut di browser Anda:
   - **Jika pakai Laragon domain:** `http://master-gambar.test/api/test-skrb-pdf`
   - **Jika pakai PHP artisan serve:** `http://127.0.0.1:8000/api/test-skrb-pdf`
3. PDF akan langsung tertampil (*inline*) di dalam browser dan secara otomatis *backend* juga mengalokasikan salinan barunya ke `C:\Nova\Rekayasa\test\preview_skrb.pdf`.

### 2. Via Postman
1. Buka **Postman**, buat tab Request baru dan pilih metode **`GET`**.
2. Masukkan URL endpoint pengetesan:
   `http://127.0.0.1:8000/api/test-skrb-pdf` atau `http://master-gambar.test/api/test-skrb-pdf`
3. Klik tombol tanda panah kecil di sebelah kanan tombol **Send** ➡️ pilih **"Send and Download"** (atau cukup klik **Send**, lalu pada tab *Response* di bagian bawah klik titik tiga `...` ➡️ **"Save Response to File"**).
4. Arahkan penyimpanan ke folder `C:\Nova\Rekayasa\test` dengan nama `preview_skrb.pdf`.
5. *(Sebagai fitur bonus: setiap kali request ke endpoint ini dipanggil, sistem juga secara otomatis sudah merekam file terupdate tersebut langsung di `C:\Nova\Rekayasa\test\preview_skrb.pdf`!)*

---

## ℹ️ Mengenai Font Arial
Template `SKRB_template.php` kini sudah secara sah diinstruksikan menggunakan font `'arial'` (yang sebelumnya telah didaftarkan melalui konstruktor `MasterPdf.php`), sehingga hasil PDF dipastikan memiliki tipe karakter resmi yang sesuai dengan ekspektasi surat penomoran kementerian.
