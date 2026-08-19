# Master Data

Semua endpoint untuk Master Data sudah disiapkan sebagai Standard RESTful API (Resource) serta mendukung fungsi recycle bin (Trash, Restore, Force Delete) khusus untuk endpoint yang berada di bawah prefix `/admin`.

*Note: Pastikan header `Authorization: Bearer {token}` disertakan dan beberapa operasi destroy/restore memerlukan role Admin.*

## 1. Type Engine
- `GET /api/type-engines` - List all Type Engine
- `POST /api/type-engines` - Create Type Engine
- `GET /api/type-engines/{id}` - Show Type Engine
- `PUT/PATCH /api/type-engines/{id}` - Update Type Engine
- `DELETE /api/type-engines/{id}` - Delete Type Engine
**Admin Only (Trash/Recycle Bin):**
- `GET /api/admin/type-engines/trash` - List Trash
- `POST /api/admin/type-engines/{id}/restore` - Restore dari Trash
- `DELETE /api/admin/type-engines/{id}/force-delete` - Hapus Permanen

## 2. Merk
- `GET /api/merks`
- `POST /api/merks`
- `GET /api/merks/{id}`
- `PUT/PATCH /api/merks/{id}`
- `DELETE /api/merks/{id}`
**Admin Only:**
- `GET /api/admin/merks/trash`
- `POST /api/admin/merks/{id}/restore`
- `DELETE /api/admin/merks/{id}/force-delete`
- `DELETE /api/admin/merks/trash/empty` - Kosongkan Trash

## 3. Type Chassis
- `GET /api/type-chassis`
- `POST /api/type-chassis`
- `GET /api/type-chassis/{id}`
- `PUT/PATCH /api/type-chassis/{id}`
- `DELETE /api/type-chassis/{id}`
- `GET /api/type-chassis/{id}/sut-pdf` - View SUT PDF
**Admin Only:**
- Trash, Restore, Force Delete, Empty Trash tersedia pada `/api/admin/type-chassis/*`

## 4. Jenis Kendaraan
- `GET /api/jenis-kendaraan`
- `POST /api/jenis-kendaraan`
- `GET /api/jenis-kendaraan/{id}`
- `PUT/PATCH /api/jenis-kendaraan/{id}`
- `DELETE /api/jenis-kendaraan/{id}`
**Admin Only:**
- Trash, Restore, Force Delete, Empty Trash tersedia pada `/api/admin/jenis-kendaraan/*`

## 5. Master Varian
- `GET /api/admin/master-varian`
- `POST /api/admin/master-varian`
- `GET /api/admin/master-varian/{id}`
- `PUT/PATCH /api/admin/master-varian/{id}`
- `DELETE /api/admin/master-varian/{id}`
- `GET /api/admin/master-varian/export-excel` - Export data
- `POST /api/admin/master-varian/import-excel` - Import data
**Trash:**
- Tersedia pada `/api/admin/master-varian-trash/*`

## 6. Varian Body
- `GET /api/varian-body`
- `POST /api/varian-body`
- `GET /api/varian-body/{id}`
- `PUT/PATCH /api/varian-body/{id}`
- `DELETE /api/varian-body/{id}`
**Admin Only:**
- `GET /api/admin/varian-body/export-excel`
- `POST /api/admin/varian-body/import-excel`
- Trash functionality tersedia.

## 7. Jenis Varian
- `GET /api/admin/jenis-varian`
- `POST /api/admin/jenis-varian`
- `GET /api/admin/jenis-varian/{id}`
- `PUT/PATCH /api/admin/jenis-varian/{id}`
- `DELETE /api/admin/jenis-varian/{id}`

## 8. Master Data Umum
- `GET /api/admin/master-data` (CRUD Resource)
- Trash, restore, force-delete, empty-trash.

---

### Contoh Hasil Response (JSON)

**Response GET Type Engine (`/api/type-engines/2`)**:
```json
{
    "id": 2,
    "type_engine": "EURO 2",
    "created_at": "2025-11-29T09:12:51.000000Z",
    "updated_at": "2025-11-29T09:12:51.000000Z",
    "deleted_at": null
}
```

**Response GET Merk (`/api/merks/1`)**:
```json
{
    "id": 1,
    "merk": "MITSUBISHI",
    "created_at": "2025-11-29T09:12:51.000000Z",
    "updated_at": "2025-11-29T09:12:51.000000Z",
    "deleted_at": null
}
```

**Response GET Varian Body (`/api/varian-body/8`)**:
```json
{
    "id": 8,
    "master_data_id": 6,
    "varian_body": "FIX SIDE",
    "created_at": "2025-12-08T21:32:23.000000Z",
    "updated_at": "2026-02-06T06:44:41.000000Z",
    "deleted_at": null
}
```
