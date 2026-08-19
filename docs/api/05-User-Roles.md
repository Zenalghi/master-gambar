# User & Roles

API untuk mengelola data akun pengguna dan Role/Hak Akses dalam sistem. 
- **Akses:** Khusus Administrator (menggunakan middleware `is.admin` dan prefix `/admin`).

## 1. User Management (CRUD)
- `GET /api/admin/users`
  - **Deskripsi:** Mendapatkan daftar user yang terdaftar.
- `POST /api/admin/users`
  - **Deskripsi:** Mendaftarkan user baru (Create).
- `GET /api/admin/users/{id}`
  - **Deskripsi:** Mendapatkan detail data user tertentu.
- `PUT/PATCH /api/admin/users/{id}`
  - **Deskripsi:** Memperbarui data profile/role user.
- `DELETE /api/admin/users/{id}`
  - **Deskripsi:** Menghapus akun user.

## 2. Helper Data (Roles)
- `GET /api/admin/options/roles`
  - **Deskripsi:** Mengambil list role yang tersedia untuk dropdown saat pembuatan/edit User.
  - *Note: API serupa juga tersedia di `GET /api/options/roles` untuk user umum.*

---

### Contoh Hasil Response (JSON)

**Response GET User (`/api/admin/users/1`)**:
```json
{
    "id": 1,
    "name": "Deni",
    "username": "deni",
    "hint": "denis1234",
    "role_id": 1,
    "signature": "1/1.png",
    "created_at": "2025-11-29T09:12:50.000000Z",
    "updated_at": "2026-04-20T03:02:27.000000Z"
}
```
