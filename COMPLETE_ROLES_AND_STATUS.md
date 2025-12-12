# Complete Roles & Status Documentation

## 📋 ROLES & PERMISSIONS

### 1. **ADMIN** (Full Access)

**Deskripsi:** Administrator dengan akses penuh ke semua fitur sistem

**Permissions:**
- ✅ **Dashboard**
  - `view-dashboard` - Lihat dashboard
  - `view-analytics` - Lihat analytics & statistik

- ✅ **Shipment Management**
  - `view-shipments` - Lihat semua shipments
  - `create-shipments` - Buat shipment baru
  - `edit-shipments` - Edit shipment
  - `delete-shipments` - Hapus shipment
  - `approve-shipments` - Approve/reject shipment
  - `assign-drivers` - Assign driver ke shipment

- ✅ **Progress Tracking**
  - `view-progress` - Lihat progress pengiriman

- ✅ **User Management**
  - `view-users` - Lihat daftar users
  - `create-users` - Buat user baru
  - `edit-users` - Edit user
  - `delete-users` - Hapus user

- ✅ **Role Management**
  - `view-roles` - Lihat roles
  - `create-roles` - Buat role baru
  - `edit-roles` - Edit role
  - `delete-roles` - Hapus role

- ✅ **Permission Management**
  - `view-permissions` - Lihat permissions
  - `create-permissions` - Buat permission baru
  - `edit-permissions` - Edit permission
  - `delete-permissions` - Hapus permission

- ✅ **Division Management**
  - `view-divisions` - Lihat divisions
  - `create-divisions` - Buat division baru
  - `edit-divisions` - Edit division
  - `delete-divisions` - Hapus division

- ✅ **Notification**
  - `view-notifications` - Lihat notifikasi
  - `manage-notifications` - Manage notifikasi

- ✅ **File Management**
  - `upload-files` - Upload file
  - `download-files` - Download file
  - `manage-files` - Manage file

**Akses Endpoint:**
- ✅ Semua endpoint shipment (CRUD)
- ✅ Bulk assign driver
- ✅ Approve/reject shipment
- ✅ Cancel shipment
- ✅ User management
- ✅ Role & permission management
- ✅ Division management
- ✅ Category & vehicle type management

---

### 2. **KURIR** (Driver)

**Deskripsi:** Driver yang bertugas mengambil dan mengirim barang

**Permissions:**
- ✅ **Dashboard**
  - `view-dashboard` - Lihat dashboard driver

- ✅ **Shipment**
  - `view-shipments` - Lihat shipments yang di-assign ke dia

- ✅ **Progress Tracking**
  - `view-progress` - Lihat progress
  - `update-progress` - Update progress (pickup, arrived, delivered, dll)
  - `view-driver-history` - Lihat history pengiriman

- ✅ **Notification**
  - `view-notifications` - Lihat notifikasi

- ✅ **File**
  - `upload-files` - Upload foto bukti
  - `download-files` - Download file

**Akses Endpoint:**
- ✅ GET `/shipments` (hanya yang di-assign ke dia)
- ✅ GET `/shipments/{id}` (hanya yang di-assign ke dia)
- ✅ POST `/shipments/{id}/start-delivery`
- ✅ POST `/shipments/{id}/destinations/{destination_id}/progress`
- ✅ GET `/shipments/{id}/progress`
- ✅ GET `/driver/history`
- ✅ GET `/notifications`

**Tidak Bisa:**
- ❌ Create shipment
- ❌ Approve shipment
- ❌ Assign driver
- ❌ Delete shipment
- ❌ Manage users/roles

---

### 3. **USER** (Regular User / Requester)

**Deskripsi:** User biasa yang bisa request pengiriman

**Permissions:**
- ✅ **Dashboard**
  - `view-dashboard` - Lihat dashboard

- ✅ **Shipment**
  - `view-shipments` - Lihat shipments yang dia buat
  - `create-shipments` - Buat request pengiriman baru

- ✅ **Progress**
  - `view-progress` - Lihat progress pengiriman

- ✅ **Notification**
  - `view-notifications` - Lihat notifikasi

- ✅ **File**
  - `upload-files` - Upload file SPJ
  - `download-files` - Download file

**Akses Endpoint:**
- ✅ GET `/shipments` (hanya yang dia buat)
- ✅ POST `/shipments` (create request)
- ✅ GET `/shipments/{id}` (hanya yang dia buat)
- ✅ GET `/shipments/{id}/progress`
- ✅ GET `/notifications`

**Tidak Bisa:**
- ❌ Approve shipment
- ❌ Assign driver
- ❌ Update progress
- ❌ Delete shipment
- ❌ Manage users/roles

---

## 📊 SHIPMENT STATUS (Status Utama)

| Status | Kode | Deskripsi | Siapa yang Set | Role |
|--------|------|-----------|----------------|------|
| **created** | `created` | Shipment baru dibuat | System | User |
| **pending** | `pending` | Menunggu jadwal/approval | Admin | Admin |
| **assigned** | `assigned` | Sudah di-assign ke driver | Admin | Admin |
| **in_progress** | `in_progress` | Driver sedang dalam perjalanan | Driver | Kurir |
| **completed** | `completed` | Semua destinasi selesai | System | Auto |
| **cancelled** | `cancelled` | Dibatalkan | Admin | Admin |

### Flow Shipment Status:

```
created → pending → assigned → in_progress → completed
   ↓         ↓         ↓
   └─────────┴─────────┴──→ cancelled (Admin)
```

---

## 📦 DESTINATION STATUS (Status per Tujuan)

| Status | Kode | Deskripsi | Siapa yang Set | Role |
|--------|------|-----------|----------------|------|
| **pending** | `pending` | Belum dikirim | System | Auto |
| **picked** | `picked` | Sudah dipickup | Driver | Kurir |
| **in_progress** | `in_progress` | Sedang dikirim | Driver | Kurir |
| **delivered** | `delivered` | Barang diserahkan | Driver | Kurir |
| **completed** | `completed` | Pengiriman selesai | Driver | Kurir |
| **returning** | `returning` | Driver balik kantor | System | Auto |
| **finished** | `finished` | Driver tiba kantor | Driver | Kurir |
| **takeover** | `takeover` | Driver bermasalah | Driver | Kurir |
| **failed** | `failed` | Gagal dikirim | Driver | Kurir |

### Flow Destination Status:

```
Normal Flow:
pending → picked → in_progress → delivered → completed → returning → finished

Takeover Flow:
in_progress → takeover → (back to pending, assign driver baru)

Failed Flow:
in_progress → failed
```

---

## 🎯 ACTIONS BY ROLE

### **ADMIN Actions:**

1. **Create Shipment** (bisa, tapi biasanya User yang create)
   - POST `/shipments`

2. **Approve Shipment**
   - POST `/shipments/{id}/approve`
   - Body: `{driver_id: 10}`

3. **Bulk Assign Driver**
   - POST `/shipments/bulk-assign-driver`
   - Body: `{shipment_ids: [1,2,3], driver_id: 10, vehicle_type_id: 2}`

4. **Set Pending**
   - POST `/shipments/{id}/pending`
   - Body: `{scheduled_delivery_datetime: "2025-12-03 10:00:00"}`

5. **Cancel Shipment**
   - POST `/shipments/{id}/cancel`

6. **Reassign Driver**
   - POST `/shipments/{id}/assign-driver`
   - Body: `{driver_id: 11}`

7. **View All Shipments**
   - GET `/shipments`

8. **Manage Users/Roles/Permissions**
   - CRUD operations on users, roles, permissions

---

### **KURIR (Driver) Actions:**

1. **View Assigned Shipments**
   - GET `/shipments?status=assigned`

2. **Pickup Barang**
   - POST `/shipments/{id}/destinations/{dest_id}/progress`
   - Body: `{status: "picked", photo: (file), note: "..."}`

3. **Start Delivery**
   - POST `/shipments/{id}/start-delivery`

4. **Arrived di Lokasi**
   - POST `/shipments/{id}/destinations/{dest_id}/progress`
   - Body: `{status: "arrived", photo: (file), note: "..."}`

5. **Serah Terima Barang**
   - POST `/shipments/{id}/destinations/{dest_id}/progress`
   - Body: `{status: "delivered", photo: (file), receiver_name: "...", received_photo: (file)}`

6. **Konfirmasi Selesai**
   - POST `/shipments/{id}/destinations/{dest_id}/progress`
   - Body: `{status: "completed", photo: (file), note: "..."}`

7. **Tiba di Kantor**
   - POST `/shipments/{id}/destinations/{dest_id}/progress`
   - Body: `{status: "finished", photo: (file), note: "..."}`

8. **Request Takeover (Bermasalah)**
   - POST `/shipments/{id}/destinations/{dest_id}/progress`
   - Body: `{status: "takeover", photo: (file), takeover_reason: "Ban bocor", note: "..."}`

9. **Mark as Failed**
   - POST `/shipments/{id}/destinations/{dest_id}/progress`
   - Body: `{status: "failed", photo: (file), note: "Penerima tidak ada"}`

10. **View History**
    - GET `/driver/history`

---

### **USER (Requester) Actions:**

1. **Create Shipment Request**
   - POST `/shipments`
   - Body: `{category_id, notes, priority, deadline, destinations: [...], items: [...]}`

2. **View Own Shipments**
   - GET `/shipments` (filtered by created_by)

3. **View Shipment Detail**
   - GET `/shipments/{id}` (hanya yang dia buat)

4. **View Progress**
   - GET `/shipments/{id}/progress`

5. **View Notifications**
   - GET `/notifications`

---

## 🔄 COMPLETE WORKFLOW BY ROLE

### Scenario: Normal Delivery

```
1. USER creates shipment
   POST /shipments
   → Shipment status: created
   → Destination status: pending

2. ADMIN approves & assigns driver
   POST /shipments/bulk-assign-driver
   Body: {shipment_ids: [1], driver_id: 10, vehicle_type_id: 2}
   → Shipment status: assigned
   → Destination status: pending

3. KURIR pickup barang
   POST /shipments/1/destinations/1/progress
   Body: {status: "picked", photo: (file)}
   → Destination status: picked

4. KURIR start delivery
   POST /shipments/1/start-delivery
   → Shipment status: in_progress
   → Destination status: in_progress

5. KURIR arrived
   POST /shipments/1/destinations/1/progress
   Body: {status: "arrived", photo: (file)}
   → Destination status: in_progress (no change)

6. KURIR serah terima
   POST /shipments/1/destinations/1/progress
   Body: {status: "delivered", receiver_name: "John", received_photo: (file)}
   → Destination status: delivered

7. KURIR konfirmasi selesai
   POST /shipments/1/destinations/1/progress
   Body: {status: "completed", photo: (file)}
   → Destination status: completed
   → If all completed: Auto-set to returning

8. SYSTEM auto-set returning
   → Destination status: returning

9. KURIR tiba di kantor
   POST /shipments/1/destinations/1/progress
   Body: {status: "finished", photo: (file)}
   → Destination status: finished
   → If all finished: Shipment status = completed
```

### Scenario: Takeover (Driver Bermasalah)

```
1-4. Same as normal (USER create → ADMIN assign → KURIR pickup → start delivery)

5. KURIR bermasalah (ban bocor)
   POST /shipments/1/destinations/1/progress
   Body: {status: "takeover", takeover_reason: "Ban bocor", photo: (file)}
   → Destination status: takeover
   → Shipment status: pending
   → assigned_driver_id: null

6. ADMIN assign driver baru
   POST /shipments/bulk-assign-driver
   Body: {shipment_ids: [1], driver_id: 11, vehicle_type_id: 2}
   → Shipment status: assigned
   → assigned_driver_id: 11

7. KURIR BARU lanjutkan
   → Continue from step 3 (pickup lagi)
```

---

## 📱 NOTIFICATIONS BY ROLE

### ADMIN Receives:
- ✅ Shipment created (from User)
- ✅ Driver request takeover
- ✅ Delivery completed
- ✅ Delivery failed

### KURIR Receives:
- ✅ Shipment assigned to you
- ✅ Shipment reassigned to you

### USER Receives:
- ✅ Shipment approved
- ✅ Shipment assigned to driver
- ✅ Delivery started
- ✅ Destination delivered
- ✅ All deliveries completed
- ✅ Driver returning to office
- ✅ Shipment completed
- ✅ Shipment cancelled

---

## 🔐 AUTHORIZATION CHECKS

### Shipment Access:
- **Admin**: Lihat semua shipments
- **Kurir**: Hanya shipments yang di-assign ke dia (`assigned_driver_id = auth()->id()`)
- **User**: Hanya shipments yang dia buat (`created_by = auth()->id()`)

### Progress Update:
- **Hanya Kurir** yang di-assign bisa update progress
- Check: `$shipment->assigned_driver_id === auth()->id()`

### Approve/Assign:
- **Hanya Admin** bisa approve & assign
- Check: `auth()->user()->can('approve-shipments')`

---

## 📊 SUMMARY

### Total Roles: **3**
- Admin (Full Access)
- Kurir (Driver Operations)
- User (Create & View)

### Total Permissions: **30+**
- Dashboard: 2
- Shipment: 6
- Progress: 3
- User Management: 4
- Role Management: 4
- Permission Management: 4
- Division Management: 4
- Notification: 2
- File: 3

### Total Shipment Status: **6**
- created, pending, assigned, in_progress, completed, cancelled

### Total Destination Status: **9**
- pending, picked, in_progress, delivered, completed, returning, finished, takeover, failed

---

Generated: 2025-12-02
Version: 1.0
