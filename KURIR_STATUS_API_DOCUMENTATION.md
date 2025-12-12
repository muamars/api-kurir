# Kurir Status Toggle API Documentation

## Overview
API untuk kurir mengaktifkan/menonaktifkan status mereka sendiri. Ketika status nonaktif, kurir tidak akan muncul di daftar driver yang tersedia untuk assignment.

## Use Case
- Kurir sedang istirahat/tidak bekerja
- Kurir sudah selesai shift
- Kurir sedang tidak tersedia untuk menerima tugas baru
- Kurir ingin mengaktifkan kembali status untuk menerima tugas

---

## Endpoints

### 1. Toggle Status Kurir (Self)
**POST** `/api/v1/my-status/toggle`

Kurir dapat mengaktifkan/menonaktifkan status mereka sendiri.

**Authorization:** Bearer Token (Role: Kurir)

**Request:**
```
POST http://localhost:8000/api/v1/my-status/toggle
Authorization: Bearer {kurir_token}
```

**Response Success (Aktif → Nonaktif):**
```json
{
  "message": "Status Anda sekarang nonaktif. Anda tidak akan menerima tugas pengiriman baru.",
  "data": {
    "id": 5,
    "name": "Agus Kurir",
    "is_active": false,
    "status": "nonaktif"
  }
}
```

**Response Success (Nonaktif → Aktif):**
```json
{
  "message": "Status Anda sekarang aktif. Anda dapat menerima tugas pengiriman.",
  "data": {
    "id": 5,
    "name": "Agus Kurir",
    "is_active": true,
    "status": "aktif"
  }
}
```

**Response Error (Bukan Kurir):**
```json
{
  "message": "Fitur ini hanya untuk kurir"
}
```
Status: 403 Forbidden

---

### 2. Get Status Kurir (Self)
**GET** `/api/v1/my-status`

Mendapatkan status kurir yang sedang login.

**Authorization:** Bearer Token (Semua role)

**Request:**
```
GET http://localhost:8000/api/v1/my-status
Authorization: Bearer {token}
```

**Response:**
```json
{
  "data": {
    "id": 5,
    "name": "Agus Kurir",
    "email": "kurir@example.com",
    "is_active": true,
    "status": "aktif"
  }
}
```

---

### 3. Toggle Status User (Admin Only)
**POST** `/api/v1/users/{id}/toggle-active`

Admin dapat mengaktifkan/menonaktifkan status user lain.

**Authorization:** Bearer Token (Role: Admin)

**Request:**
```
POST http://localhost:8000/api/v1/users/5/toggle-active
Authorization: Bearer {admin_token}
```

**Response:**
```json
{
  "message": "User berhasil dinonaktifkan",
  "data": {
    "id": 5,
    "name": "Agus Kurir",
    "is_active": false
  }
}
```

**Proteksi:**
- Admin tidak bisa menonaktifkan akun sendiri
- Admin tidak bisa menonaktifkan admin terakhir yang aktif

---

## Dampak Status Nonaktif

### 1. Untuk Kurir (is_active = false)
- ❌ Tidak muncul di dropdown assignment driver (`GET /api/v1/drivers`)
- ❌ Tidak bisa di-assign ke shipment baru
- ✅ **TETAP BISA LOGIN** (untuk toggle status kembali)
- ✅ Masih bisa melihat shipment yang sudah di-assign sebelumnya
- ✅ Masih bisa update progress shipment yang sedang berjalan
- ✅ Bisa toggle status sendiri menjadi aktif kembali

### 2. Untuk Admin/User (is_active = false)
- ❌ **TIDAK BISA LOGIN** (dinonaktifkan oleh admin)
- ❌ Tidak muncul di daftar user aktif
- ⚠️ Hanya admin yang bisa mengaktifkan kembali

---

## Integration dengan Endpoint Lain

### Get Drivers (Hanya Aktif)
```
GET /api/v1/drivers
```
Otomatis hanya menampilkan driver dengan `is_active = true`

### Get All Users (Filter)
```
GET /api/v1/users?is_active=true   # Hanya user aktif
GET /api/v1/users?is_active=false  # Hanya user nonaktif
GET /api/v1/users                  # Semua user
```

---

## Cara Implementasi di Frontend

### 1. Toggle Switch di Profil Kurir
```javascript
// Component: KurirStatusToggle.vue
async function toggleStatus() {
  try {
    const response = await axios.post('/api/v1/my-status/toggle', {}, {
      headers: { Authorization: `Bearer ${token}` }
    });
    
    // Update UI
    isActive.value = response.data.data.is_active;
    showNotification(response.data.message);
    
  } catch (error) {
    showError(error.response.data.message);
  }
}
```

### 2. Status Badge di Dashboard Kurir
```javascript
// Get current status on mount
async function getMyStatus() {
  const response = await axios.get('/api/v1/my-status', {
    headers: { Authorization: `Bearer ${token}` }
  });
  
  return response.data.data;
}
```

### 3. Admin Toggle User Status
```javascript
// Component: UserManagement.vue
async function toggleUserStatus(userId) {
  try {
    const response = await axios.post(`/api/v1/users/${userId}/toggle-active`, {}, {
      headers: { Authorization: `Bearer ${adminToken}` }
    });
    
    // Update user list
    updateUserInList(userId, response.data.data);
    
  } catch (error) {
    showError(error.response.data.message);
  }
}
```

---

## UI/UX Recommendations

### Untuk Kurir:
1. **Toggle Switch** di halaman profil/dashboard
   - Label: "Status Ketersediaan"
   - ON: "Aktif - Siap Menerima Tugas"
   - OFF: "Nonaktif - Tidak Tersedia"

2. **Konfirmasi Dialog** saat menonaktifkan:
   ```
   "Anda yakin ingin menonaktifkan status?
   Anda tidak akan menerima tugas pengiriman baru."
   ```

3. **Status Badge** yang selalu terlihat:
   - 🟢 Aktif (hijau)
   - 🔴 Nonaktif (merah)

### Untuk Admin:
1. **Filter** di user list: Aktif / Nonaktif / Semua
2. **Toggle Button** di setiap row user
3. **Indicator** visual status user (badge/icon)

---

## Database Schema

Field `is_active` sudah ada di tabel `users`:
```sql
- is_active (boolean, default: true)
```

---

## Authorization Summary

| Endpoint | Role | Deskripsi |
|----------|------|-----------|
| `POST /my-status/toggle` | Kurir | Toggle status sendiri |
| `GET /my-status` | All | Lihat status sendiri |
| `POST /users/{id}/toggle-active` | Admin | Toggle status user lain |

---

## Testing

### Test sebagai Kurir:
```bash
# Login sebagai kurir
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"kurir@example.com","password":"password"}'

# Toggle status
curl -X POST http://localhost:8000/api/v1/my-status/toggle \
  -H "Authorization: Bearer {kurir_token}"

# Cek status
curl -X GET http://localhost:8000/api/v1/my-status \
  -H "Authorization: Bearer {kurir_token}"
```

### Test sebagai Admin:
```bash
# Toggle status user lain
curl -X POST http://localhost:8000/api/v1/users/5/toggle-active \
  -H "Authorization: Bearer {admin_token}"
```
