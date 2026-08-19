# Authentication & Authorization

Kumpulan API yang digunakan untuk autentikasi user dan manajemen sesi.

## 1. Login
- **Endpoint:** `POST /api/login`
- **Akses:** Publik
- **Deskripsi:** Melakukan login untuk mendapatkan Bearer Token.

## 2. Register
- **Endpoint:** `POST /api/register`
- **Akses:** Publik
- **Deskripsi:** Registrasi user baru (Jika diaktifkan).

## 3. Logout
- **Endpoint:** `POST /api/logout`
- **Akses:** Auth (Bearer Token)
- **Deskripsi:** Menghapus sesi / token yang sedang aktif.

## 4. Get Current User
- **Endpoint:** `GET /api/user`
- **Akses:** Auth (Bearer Token)
- **Deskripsi:** Mendapatkan data profile user yang sedang login.
