# API Endpoints - Driver Flow Documentation

## Base URL
```
/api/v1
```

## Authentication
Semua endpoint memerlukan Bearer Token (Laravel Sanctum):
```
Authorization: Bearer {token}
```

---

## 📋 COMPLETE DRIVER FLOW

### Flow Overview
```
1. Admin Bulk Assign → Driver
2. Driver Lihat Assigned Tickets
3. Driver PICKUP Barang (WAJIB)
4. Driver START DELIVERY
5. Driver ARRIVED di Lokasi
6. Driver DELIVERED Barang
7. System Auto-set RETURNING
8. Driver FINISHED (Tiba di Kantor)
```

---

## 1. ADMIN - BULK ASSIGN SHIPMENTS

### Endpoint
```http
POST /api/v1/shipments/bulk-assign-driver
```

### Headers
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

### Request Body
```json
{
  "shipment_ids": [1, 2, 3],
  "driver_id": 10,
  "vehicle_type_id": 2
}
```

### Validation Rules
- `shipment_ids`: required, array, min:1
- `shipment_ids.*`: required, exists:shipments,id
- `driver_id`: required, exists:users,id
- `vehicle_type_id`: required, exists:vehicle_types,id, must be active

### Response Success (200)
```json
{
  "message": "3 shipments assigned to driver successfully",
  "assigned_count": 3,
  "shipments": [
    {
      "id": 1,
      "shipment_id": "SPJ-20251127-ABC123",
      "status": "assigned",
      "deadline": "2025-11-27 10:00:00",
      "priority": "urgent",
      "driver": {
        "id": 10,
        "name": "Driver A",
        "phone": "08123456789"
      },
      "vehicle_type": {
        "id": 2,
        "name": "Mobil Pick Up",
        "code": "PICKUP",
        "description": "Mobil pick up untuk barang berukuran sedang hingga besar"
      },
      "category": {
        "id": 1,
        "name": "Dokumen",
        "description": "Pengiriman dokumen penting"
      },
      "destinations": [
        {
          "id": 1,
          "receiver_company": "PT ABC",
          "receiver_name": "John Doe",
          "receiver_contact": "08123456789",
          "delivery_address": "Jl. Sudirman No. 123",
          "shipment_note": "Lantai 5",
          "status": "pending",
          "sequence_order": 1
        }
      ],
      "items": [
        {
          "id": 1,
          "item_name": "Dokumen Kontrak",
          "quantity": 1,
          "description": "Kontrak kerjasama"
        }
      ]
    }
  ]
}
```

### Response Error (400)
```json
{
  "message": "Some shipments are not in created or pending status"
}
```

---

## 2. DRIVER - LIHAT ASSIGNED TICKETS

### Endpoint
```http
GET /api/v1/shipments?status=assigned
```

### Headers
```
Authorization: Bearer {driver_token}
```

### Query Parameters
- `status`: assigned (untuk lihat ticket yang di-assign)
- `sort_by`: created_at, deadline, priority (optional)
- `sort_order`: asc, desc (optional)

### Response Success (200)
```json
{
  "data": [
    {
      "id": 1,
      "shipment_id": "SPJ-20251127-ABC123",
      "status": "assigned",
      "priority": "urgent",
      "deadline": "2025-11-27 10:00:00",
      "scheduled_delivery_datetime": "2025-11-27 09:00:00",
      "notes": "Pengiriman dokumen penting",
      "courier_notes": "Hati-hati, dokumen rahasia",
      "vehicle_type": {
        "id": 2,
        "name": "Mobil Pick Up",
        "code": "PICKUP"
      },
      "category": {
        "id": 1,
        "name": "Dokumen"
      },
      "creator": {
        "id": 5,
        "name": "Admin User",
        "division": "Logistik"
      },
      "destinations": [
        {
          "id": 1,
          "receiver_company": "PT ABC",
          "receiver_name": "John Doe",
          "receiver_contact": "08123456789",
          "delivery_address": "Jl. Sudirman No. 123, Jakarta",
          "shipment_note": "Lantai 5, Ruang Meeting",
          "status": "pending",
          "sequence_order": 1
        }
      ],
      "items": [
        {
          "id": 1,
          "item_name": "Dokumen Kontrak",
          "quantity": 1,
          "description": "Kontrak kerjasama tahun 2025"
        }
      ],
      "progress_count": 0,
      "completed_destinations": 0,
      "total_destinations": 1
    }
  ]
}
```

---

## 3. DRIVER - PICKUP BARANG (WAJIB DULU)

### Endpoint
```http
POST /api/v1/shipments/{shipment_id}/destinations/{destination_id}/progress
```

### Headers
```
Authorization: Bearer {driver_token}
Content-Type: multipart/form-data
```

### Form Data
```
status: "picked"
photo: (file) [foto barang yang dipickup]
note: "Barang sudah diambil dari warehouse"
```

### Validation Rules
- `status`: required, in:picked,arrived,delivered,returning,finished,failed
- `photo`: required, image, max:4096 (4MB)
- `note`: nullable, string
- `receiver_name`: required_if:status,delivered
- `received_photo`: nullable, image, max:4096

### Response Success (200)
```json
{
  "message": "Progress updated successfully",
  "data": {
    "id": 1,
    "shipment_id": 1,
    "destination_id": 1,
    "driver_id": 10,
    "status": "picked",
    "progress_time": "2025-11-27 08:30:00",
    "photo_url": "shipment-photos/1732689000_abc123.jpg",
    "photo_thumbnail": "shipment-photos/thumb_1732689000_abc123.jpg",
    "note": "Barang sudah diambil dari warehouse",
    "receiver_name": null,
    "received_photo_url": null,
    "destination": {
      "id": 1,
      "receiver_company": "PT ABC",
      "receiver_name": "John Doe",
      "status": "picked"
    },
    "driver": {
      "id": 10,
      "name": "Driver A"
    }
  }
}
```

### Response Error (400)
```json
{
  "message": "Pickup can only be done when shipment is assigned or in progress"
}
```

### Notes
- Bisa pickup **multiple destinations** sebelum start delivery
- Status shipment tetap `assigned` setelah pickup
- Destination status berubah dari `pending` → `picked`

### Example: Pickup 3 Destinations
```bash
# Pickup destination 1
POST /api/v1/shipments/1/destinations/1/progress
Form Data: status=picked, photo=(file), note="Barang 1 diambil"

# Pickup destination 2
POST /api/v1/shipments/1/destinations/2/progress
Form Data: status=picked, photo=(file), note="Barang 2 diambil"

# Pickup destination 3
POST /api/v1/shipments/1/destinations/3/progress
Form Data: status=picked, photo=(file), note="Barang 3 diambil"
```

---

## 4. DRIVER - START DELIVERY (SETELAH PICKUP)

### Endpoint
```http
POST /api/v1/shipments/{shipment_id}/start-delivery
```

### Headers
```
Authorization: Bearer {driver_token}
```

### Request Body
```
(No body required)
```

### Response Success (200)
```json
{
  "message": "Delivery started successfully",
  "data": {
    "id": 1,
    "shipment_id": "SPJ-20251127-ABC123",
    "status": "in_progress",
    "destinations": [
      {
        "id": 1,
        "status": "in_progress"
      },
      {
        "id": 2,
        "status": "in_progress"
      },
      {
        "id": 3,
        "status": "in_progress"
      }
    ]
  }
}
```

### Response Error (400) - Belum Pickup
```json
{
  "message": "Please pickup at least one item before starting delivery",
  "hint": "Use POST /shipments/{id}/destinations/{destination_id}/progress with status=picked"
}
```

### Response Error (403)
```json
{
  "message": "You are not assigned to this shipment"
}
```

### Effects
- Shipment status: `assigned` → `in_progress`
- All destinations with status `picked` → `in_progress`
- Destinations still `pending` remain `pending`
- Notification sent to creator

---

## 5. DRIVER - ARRIVED DI LOKASI

### Endpoint
```http
POST /api/v1/shipments/{shipment_id}/destinations/{destination_id}/progress
```

### Headers
```
Authorization: Bearer {driver_token}
Content-Type: multipart/form-data
```

### Form Data
```
status: "arrived"
photo: (file) [foto lokasi/gedung]
note: "Sudah sampai di PT ABC, Lantai 5"
```

### Response Success (200)
```json
{
  "message": "Progress updated successfully",
  "data": {
    "id": 2,
    "status": "arrived",
    "progress_time": "2025-11-27 09:45:00",
    "photo_url": "shipment-photos/arrived_123.jpg",
    "note": "Sudah sampai di PT ABC, Lantai 5",
    "destination": {
      "id": 1,
      "status": "in_progress"
    }
  }
}
```

### Effects
- Destination status tetap `in_progress`
- Progress record tersimpan untuk tracking

---

## 6. DRIVER - DELIVERED BARANG

### Endpoint
```http
POST /api/v1/shipments/{shipment_id}/destinations/{destination_id}/progress
```

### Headers
```
Authorization: Bearer {driver_token}
Content-Type: multipart/form-data
```

### Form Data
```
status: "delivered"
photo: (file) [foto barang yang dikirim]
receiver_name: "John Doe"
received_photo: (file) [foto penerima/tanda tangan]
note: "Barang diterima dengan baik oleh John Doe"
```

### Response Success (200)
```json
{
  "message": "Progress updated successfully",
  "data": {
    "id": 3,
    "status": "delivered",
    "progress_time": "2025-11-27 10:00:00",
    "photo_url": "shipment-photos/delivered_123.jpg",
    "receiver_name": "John Doe",
    "received_photo_url": "shipment-photos/received_123.jpg",
    "note": "Barang diterima dengan baik oleh John Doe",
    "destination": {
      "id": 1,
      "status": "completed"
    }
  }
}
```

### Effects
- Destination status: `in_progress` → `completed`
- Notification sent to creator
- **If all destinations completed**: Auto-set all destinations to `returning`

---

## 7. SYSTEM - AUTO-SET RETURNING

### Trigger
Otomatis terjadi ketika **semua destinations** status = `completed`

### Effects
- All destinations: `completed` → `returning`
- Shipment status tetap `in_progress`
- Notification sent to creator: "All deliveries completed, driver returning to office"

### Check Status
```http
GET /api/v1/shipments/{shipment_id}
```

Response:
```json
{
  "data": {
    "id": 1,
    "status": "in_progress",
    "destinations": [
      {
        "id": 1,
        "status": "returning"
      },
      {
        "id": 2,
        "status": "returning"
      }
    ]
  }
}
```

---

## 8. DRIVER - FINISHED (TIBA DI KANTOR)

### Endpoint
```http
POST /api/v1/shipments/{shipment_id}/destinations/{destination_id}/progress
```

### Headers
```
Authorization: Bearer {driver_token}
Content-Type: multipart/form-data
```

### Form Data
```
status: "finished"
photo: (file) [foto di kantor]
note: "Sudah kembali ke kantor, semua pengiriman selesai"
```

### Response Success (200)
```json
{
  "message": "Progress updated successfully",
  "data": {
    "id": 4,
    "status": "finished",
    "progress_time": "2025-11-27 11:30:00",
    "photo_url": "shipment-photos/finished_123.jpg",
    "note": "Sudah kembali ke kantor, semua pengiriman selesai",
    "destination": {
      "id": 1,
      "status": "finished"
    }
  }
}
```

### Effects
- Destination status: `returning` → `finished`
- **If all destinations finished**: Shipment status → `completed`
- Final notification sent to creator

---

## 9. DRIVER - FAILED DELIVERY

### Endpoint
```http
POST /api/v1/shipments/{shipment_id}/destinations/{destination_id}/progress
```

### Headers
```
Authorization: Bearer {driver_token}
Content-Type: multipart/form-data
```

### Form Data
```
status: "failed"
photo: (file) [foto bukti]
note: "Penerima tidak ada di tempat, sudah menghubungi 3x"
```

### Response Success (200)
```json
{
  "message": "Progress updated successfully",
  "data": {
    "id": 5,
    "status": "failed",
    "progress_time": "2025-11-27 10:15:00",
    "photo_url": "shipment-photos/failed_123.jpg",
    "note": "Penerima tidak ada di tempat, sudah menghubungi 3x",
    "destination": {
      "id": 1,
      "status": "failed"
    }
  }
}
```

### Effects
- Destination status: `in_progress` → `failed`
- Shipment status tetap `in_progress` (tidak auto-complete)
- Notification sent to creator

---

## 10. GET SHIPMENT PROGRESS HISTORY

### Endpoint
```http
GET /api/v1/shipments/{shipment_id}/progress
```

### Headers
```
Authorization: Bearer {token}
```

### Response Success (200)
```json
{
  "data": [
    {
      "id": 4,
      "status": "finished",
      "progress_time": "2025-11-27 11:30:00",
      "photo_url": "shipment-photos/finished_123.jpg",
      "photo_thumbnail": "shipment-photos/thumb_finished_123.jpg",
      "note": "Sudah kembali ke kantor",
      "receiver_name": null,
      "received_photo_url": null,
      "driver": {
        "id": 10,
        "name": "Driver A"
      },
      "destination": {
        "id": 1,
        "receiver_company": "PT ABC",
        "receiver_name": "John Doe"
      }
    },
    {
      "id": 3,
      "status": "delivered",
      "progress_time": "2025-11-27 10:00:00",
      "receiver_name": "John Doe",
      "received_photo_url": "shipment-photos/received_123.jpg"
    },
    {
      "id": 2,
      "status": "arrived",
      "progress_time": "2025-11-27 09:45:00"
    },
    {
      "id": 1,
      "status": "picked",
      "progress_time": "2025-11-27 08:30:00"
    }
  ]
}
```

---

## 11. GET DRIVER HISTORY

### Endpoint
```http
GET /api/v1/driver/history
```

### Headers
```
Authorization: Bearer {driver_token}
```

### Query Parameters
- `page`: page number (optional, default: 1)
- `per_page`: items per page (optional, default: 20)

### Response Success (200)
```json
{
  "data": [
    {
      "id": 10,
      "status": "delivered",
      "progress_time": "2025-11-27 10:00:00",
      "photo_url": "shipment-photos/delivered_123.jpg",
      "note": "Barang diterima dengan baik",
      "shipment": {
        "id": 1,
        "shipment_id": "SPJ-20251127-ABC123",
        "status": "completed"
      },
      "destination": {
        "id": 1,
        "receiver_company": "PT ABC",
        "receiver_name": "John Doe",
        "delivery_address": "Jl. Sudirman No. 123"
      }
    }
  ],
  "links": {
    "first": "http://api.example.com/api/v1/driver/history?page=1",
    "last": "http://api.example.com/api/v1/driver/history?page=5",
    "prev": null,
    "next": "http://api.example.com/api/v1/driver/history?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 20,
    "to": 20,
    "total": 95
  }
}
```

---

## 📊 STATUS REFERENCE

### Shipment Status
| Status | Description |
|--------|-------------|
| `created` | Shipment baru dibuat |
| `pending` | Menunggu jadwal |
| `assigned` | Sudah di-assign ke driver |
| `in_progress` | Driver sedang dalam perjalanan |
| `completed` | Semua destinasi selesai |
| `cancelled` | Dibatalkan |

### Destination Status
| Status | Description |
|--------|-------------|
| `pending` | Belum dikirim |
| `picked` | Sudah dipickup driver |
| `in_progress` | Sedang dalam proses pengiriman |
| `completed` | Sudah diterima |
| `returning` | Driver balik ke kantor |
| `finished` | Driver sudah tiba di kantor |
| `failed` | Gagal dikirim |

### Progress Status
| Status | Maps to Destination Status |
|--------|---------------------------|
| `picked` | `picked` |
| `arrived` | `in_progress` |
| `delivered` | `completed` |
| `returning` | `returning` |
| `finished` | `finished` |
| `failed` | `failed` |

---

## 🔄 COMPLETE FLOW DIAGRAM

```
┌─────────────────────────────────────────────────────────────┐
│ ADMIN: Bulk Assign                                          │
│ POST /shipments/bulk-assign-driver                          │
│ Body: {shipment_ids, driver_id, vehicle_type_id}           │
│ → Shipment: assigned                                        │
│ → Destinations: pending                                     │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ DRIVER: Lihat Tickets                                       │
│ GET /shipments?status=assigned                              │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ DRIVER: PICKUP Barang (WAJIB)                              │
│ POST /shipments/1/destinations/1/progress                   │
│ Body: status=picked, photo=(file)                          │
│ → Destination 1: picked                                     │
│                                                             │
│ POST /shipments/1/destinations/2/progress                   │
│ Body: status=picked, photo=(file)                          │
│ → Destination 2: picked                                     │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ DRIVER: START DELIVERY                                      │
│ POST /shipments/1/start-delivery                            │
│ → Shipment: in_progress                                     │
│ → Picked destinations: in_progress                          │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ DRIVER: ARRIVED                                             │
│ POST /shipments/1/destinations/1/progress                   │
│ Body: status=arrived, photo=(file)                         │
│ → Destination: in_progress (no change)                     │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ DRIVER: DELIVERED                                           │
│ POST /shipments/1/destinations/1/progress                   │
│ Body: status=delivered, photo, receiver_name               │
│ → Destination: completed                                    │
│ → If all completed: Auto-set to returning                  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ SYSTEM: Auto-set RETURNING                                  │
│ → All destinations: returning                               │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ DRIVER: FINISHED                                            │
│ POST /shipments/1/destinations/1/progress                   │
│ Body: status=finished, photo=(file)                        │
│ → Destination: finished                                     │
│ → If all finished: Shipment = completed                    │
└─────────────────────────────────────────────────────────────┘
```

---

## 🚨 ERROR CODES

| Code | Message | Solution |
|------|---------|----------|
| 400 | Please pickup at least one item before starting delivery | Lakukan pickup dulu sebelum start delivery |
| 400 | Pickup can only be done when shipment is assigned or in progress | Shipment harus status assigned atau in_progress |
| 400 | Some shipments are not in created or pending status | Pastikan semua shipment status created/pending |
| 403 | You are not assigned to this shipment | Hanya driver yang di-assign yang bisa akses |
| 404 | Shipment not found | Shipment ID tidak valid |
| 422 | Validation error | Cek field yang required |

---

## 📝 NOTES

1. **Pickup adalah WAJIB** sebelum start delivery
2. **Bisa pickup multiple destinations** sebelum start delivery
3. **Start delivery** akan error jika belum ada pickup
4. **Auto-returning** terjadi setelah semua destination completed
5. **Auto-complete shipment** terjadi setelah semua destination finished
6. **Photo required** untuk semua progress update
7. **Receiver name required** untuk status delivered
8. **File size limit**: 4MB untuk photo

---

Generated: 2025-11-28
Version: 1.0
