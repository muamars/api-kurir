# Fixed Status Flow - Documentation

## ✅ MASALAH YANG SUDAH DIPERBAIKI

### Masalah Sebelumnya:
1. ❌ Status `delivered` langsung jadi `returning` (skip `completed`)
2. ❌ Tidak ada status `completed` di destination
3. ❌ Tidak ada status `takeover` untuk driver bermasalah
4. ❌ Flow tidak sesuai kebutuhan bisnis

### Solusi:
1. ✅ Tambah status `delivered` di destination
2. ✅ Tambah status `completed` di destination & progress
3. ✅ Tambah status `takeover` untuk driver bermasalah
4. ✅ Fix flow logic: delivered → completed → returning → finished

---

## 📊 STATUS FLOW YANG BENAR

### Destination Status (Lengkap)

| Status | Kode | Deskripsi | Siapa yang Set |
|--------|------|-----------|----------------|
| **pending** | `pending` | Belum dikirim | System (default) |
| **picked** | `picked` | Sudah dipickup driver | Driver (pickup) |
| **in_progress** | `in_progress` | Sedang dalam perjalanan | Driver (start delivery) |
| **delivered** | `delivered` | Barang sudah diserahkan ke penerima | Driver (serah terima) |
| **completed** | `completed` | Pengiriman selesai dikonfirmasi | Driver (konfirmasi selesai) |
| **returning** | `returning` | Driver balik ke kantor | System (auto, setelah semua completed) |
| **finished** | `finished` | Driver sudah tiba di kantor | Driver (tiba kantor) |
| **takeover** | `takeover` | Driver bermasalah, perlu driver pengganti | Driver (kendala) |
| **failed** | `failed` | Gagal dikirim | Driver (gagal) |

### Progress Status (Lengkap)

| Status | Maps to Destination | Deskripsi |
|--------|---------------------|-----------|
| `picked` | `picked` | Pickup barang |
| `arrived` | `in_progress` | Sampai lokasi |
| `delivered` | `delivered` | Serah terima barang |
| `completed` | `completed` | Konfirmasi selesai |
| `returning` | `returning` | Balik ke kantor |
| `finished` | `finished` | Tiba di kantor |
| `takeover` | `takeover` | Minta takeover |
| `failed` | `failed` | Gagal kirim |

---

## 🔄 FLOW LENGKAP (FIXED)

### Normal Flow (Sukses)

```
1. ADMIN ASSIGN
   Shipment: assigned
   Destination: pending

2. DRIVER PICKUP
   POST /shipments/1/destinations/1/progress
   Body: status=picked
   → Destination: pending → picked

3. DRIVER START DELIVERY
   POST /shipments/1/start-delivery
   → Destination: picked → in_progress

4. DRIVER ARRIVED
   POST /shipments/1/destinations/1/progress
   Body: status=arrived
   → Destination: in_progress (no change)

5. DRIVER SERAH TERIMA BARANG
   POST /shipments/1/destinations/1/progress
   Body: status=delivered, receiver_name, received_photo
   → Destination: in_progress → delivered ✅ FIXED

6. DRIVER KONFIRMASI SELESAI
   POST /shipments/1/destinations/1/progress
   Body: status=completed
   → Destination: delivered → completed ✅ FIXED
   → If all completed: Auto-set all to returning

7. SYSTEM AUTO-SET RETURNING
   → All destinations: completed → returning

8. DRIVER TIBA DI KANTOR
   POST /shipments/1/destinations/1/progress
   Body: status=finished
   → Destination: returning → finished
   → If all finished: Shipment = completed
```

### Takeover Flow (Driver Bermasalah)

```
1-3. Same as normal flow (assign → pickup → start delivery)

4. DRIVER BERMASALAH (Ban bocor, kecelakaan, dll)
   POST /shipments/1/destinations/1/progress
   Body: status=takeover, takeover_reason="Ban bocor"
   
   Effects:
   → Destination: in_progress → takeover ✅ NEW
   → Shipment: in_progress → pending
   → assigned_driver_id: null (unassign driver)
   → Notification to admin: "Driver bermasalah, perlu driver baru"

5. ADMIN ASSIGN DRIVER BARU
   POST /shipments/bulk-assign-driver
   Body: {shipment_ids: [1], driver_id: 11}
   
   → Shipment: pending → assigned
   → assigned_driver_id: 11 (driver baru)

6. DRIVER BARU LANJUTKAN
   → Continue from step 2 (pickup lagi jika perlu)
```

---

## 📝 API ENDPOINTS (UPDATED)

### 1. Driver Pickup

```http
POST /api/v1/shipments/{shipment_id}/destinations/{destination_id}/progress
Content-Type: multipart/form-data

Form Data:
- status: "picked"
- photo: (file)
- note: "Barang sudah diambil"
```

**Response:**
```json
{
  "message": "Progress updated successfully",
  "data": {
    "status": "picked",
    "destination": {
      "status": "picked"
    }
  }
}
```

---

### 2. Driver Start Delivery

```http
POST /api/v1/shipments/{shipment_id}/start-delivery
```

**Response:**
```json
{
  "message": "Delivery started successfully",
  "data": {
    "status": "in_progress",
    "destinations": [
      {"status": "in_progress"}
    ]
  }
}
```

---

### 3. Driver Arrived

```http
POST /api/v1/shipments/{shipment_id}/destinations/{destination_id}/progress
Content-Type: multipart/form-data

Form Data:
- status: "arrived"
- photo: (file)
- note: "Sudah sampai lokasi"
```

---

### 4. Driver Serah Terima Barang ✅ FIXED

```http
POST /api/v1/shipments/{shipment_id}/destinations/{destination_id}/progress
Content-Type: multipart/form-data

Form Data:
- status: "delivered"
- photo: (file)
- receiver_name: "John Doe"
- received_photo: (file)
- note: "Barang diterima dengan baik"
```

**Response:**
```json
{
  "message": "Progress updated successfully",
  "data": {
    "status": "delivered",
    "receiver_name": "John Doe",
    "destination": {
      "status": "delivered"
    }
  }
}
```

**Effect:**
- Destination: `in_progress` → `delivered` ✅
- Notification sent to creator

---

### 5. Driver Konfirmasi Selesai ✅ NEW

```http
POST /api/v1/shipments/{shipment_id}/destinations/{destination_id}/progress
Content-Type: multipart/form-data

Form Data:
- status: "completed"
- photo: (file)
- note: "Pengiriman selesai"
```

**Response:**
```json
{
  "message": "Progress updated successfully",
  "data": {
    "status": "completed",
    "destination": {
      "status": "completed"
    }
  }
}
```

**Effect:**
- Destination: `delivered` → `completed` ✅
- **If all destinations completed**: Auto-set all to `returning`

---

### 6. Driver Takeover (Bermasalah) ✅ NEW

```http
POST /api/v1/shipments/{shipment_id}/destinations/{destination_id}/progress
Content-Type: multipart/form-data

Form Data:
- status: "takeover"
- photo: (file foto bukti masalah)
- takeover_reason: "Ban bocor, tidak bisa lanjut"
- note: "Butuh driver pengganti"
```

**Response:**
```json
{
  "message": "Progress updated successfully",
  "data": {
    "status": "takeover",
    "destination": {
      "status": "takeover"
    }
  }
}
```

**Effect:**
- Destination: `in_progress` → `takeover` ✅
- Shipment: `in_progress` → `pending`
- `assigned_driver_id`: null (unassign)
- Notification to admin: "Driver bermasalah, perlu driver baru"

---

### 7. Driver Returning (Auto-set)

Otomatis terjadi setelah semua destination `completed`.

**Check Status:**
```http
GET /api/v1/shipments/{shipment_id}
```

**Response:**
```json
{
  "data": {
    "status": "in_progress",
    "destinations": [
      {"status": "returning"}
    ]
  }
}
```

---

### 8. Driver Finished

```http
POST /api/v1/shipments/{shipment_id}/destinations/{destination_id}/progress
Content-Type: multipart/form-data

Form Data:
- status: "finished"
- photo: (file)
- note: "Sudah kembali ke kantor"
```

**Response:**
```json
{
  "message": "Progress updated successfully",
  "data": {
    "status": "finished",
    "destination": {
      "status": "finished"
    }
  }
}
```

**Effect:**
- Destination: `returning` → `finished`
- **If all destinations finished**: Shipment status → `completed`

---

## 🎯 COMPARISON: BEFORE vs AFTER

### BEFORE (SALAH ❌)

```
delivered → returning → finished
```

**Masalah:**
- Skip status `completed`
- Tidak ada konfirmasi selesai
- Tidak ada status `takeover`

### AFTER (BENAR ✅)

```
delivered → completed → returning → finished
                ↓
            takeover (jika bermasalah)
```

**Keuntungan:**
- Ada konfirmasi selesai (`completed`)
- Ada status `takeover` untuk driver bermasalah
- Flow lebih jelas dan sesuai bisnis proses
- History tracking lebih lengkap

---

## 📊 STATUS HISTORY EXAMPLE

### Normal Flow History

```json
[
  {
    "old_status": "returning",
    "new_status": "finished",
    "changed_at": "2025-12-02 18:00:00",
    "changed_by": "Driver A"
  },
  {
    "old_status": "completed",
    "new_status": "returning",
    "changed_at": "2025-12-02 15:00:01",
    "changed_by": null
  },
  {
    "old_status": "delivered",
    "new_status": "completed",
    "changed_at": "2025-12-02 15:00:00",
    "changed_by": "Driver A"
  },
  {
    "old_status": "in_progress",
    "new_status": "delivered",
    "changed_at": "2025-12-02 14:30:00",
    "changed_by": "Driver A"
  },
  {
    "old_status": "picked",
    "new_status": "in_progress",
    "changed_at": "2025-12-02 09:30:00",
    "changed_by": "Driver A"
  },
  {
    "old_status": "pending",
    "new_status": "picked",
    "changed_at": "2025-12-02 09:00:00",
    "changed_by": "Driver A"
  }
]
```

### Takeover Flow History

```json
[
  {
    "old_status": "in_progress",
    "new_status": "takeover",
    "changed_at": "2025-12-02 10:30:00",
    "changed_by": "Driver A",
    "note": "Ban bocor, tidak bisa lanjut"
  },
  {
    "old_status": "picked",
    "new_status": "in_progress",
    "changed_at": "2025-12-02 09:30:00",
    "changed_by": "Driver A"
  },
  {
    "old_status": "pending",
    "new_status": "picked",
    "changed_at": "2025-12-02 09:00:00",
    "changed_by": "Driver A"
  }
]
```

---

## ✅ SUMMARY PERUBAHAN

### Database:
1. ✅ Tambah status `delivered` di `shipment_destinations`
2. ✅ Tambah status `completed` di `shipment_destinations` & `shipment_progress`
3. ✅ Tambah status `takeover` di `shipment_destinations` & `shipment_progress`

### Controller Logic:
1. ✅ Fix `mapStatus()` - delivered → delivered (bukan completed)
2. ✅ Tambah logic untuk status `completed`
3. ✅ Tambah logic untuk status `takeover`
4. ✅ Fix auto-set returning (trigger saat `completed`, bukan `delivered`)

### Validation:
1. ✅ Update validation: tambah `delivered`, `completed`, `takeover`
2. ✅ Tambah `takeover_reason` required jika status = takeover

### Flow:
1. ✅ **BEFORE**: delivered → returning → finished
2. ✅ **AFTER**: delivered → completed → returning → finished
3. ✅ **NEW**: takeover flow untuk driver bermasalah

---

## 🧪 TESTING

### Test Normal Flow

```bash
# 1. Pickup
POST /api/v1/shipments/1/destinations/1/progress
Body: status=picked

# 2. Start Delivery
POST /api/v1/shipments/1/start-delivery

# 3. Arrived
POST /api/v1/shipments/1/destinations/1/progress
Body: status=arrived

# 4. Delivered
POST /api/v1/shipments/1/destinations/1/progress
Body: status=delivered, receiver_name="John"

# 5. Completed
POST /api/v1/shipments/1/destinations/1/progress
Body: status=completed

# 6. Check status (should be returning)
GET /api/v1/shipments/1

# 7. Finished
POST /api/v1/shipments/1/destinations/1/progress
Body: status=finished
```

### Test Takeover Flow

```bash
# 1-2. Same as normal (pickup → start delivery)

# 3. Takeover
POST /api/v1/shipments/1/destinations/1/progress
Body: status=takeover, takeover_reason="Ban bocor"

# 4. Check shipment (should be pending, driver unassigned)
GET /api/v1/shipments/1

# 5. Admin assign driver baru
POST /api/v1/shipments/bulk-assign-driver
Body: {shipment_ids: [1], driver_id: 11}
```

---

Generated: 2025-12-02
Version: 2.0 (FIXED)
