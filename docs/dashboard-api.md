# API Documentation: Dashboard Endpoint

## Endpoint

| Property | Value |
|----------|-------|
| **Method** | `GET` |
| **URL** | `/api/v1/dashboard` |
| **Middleware** | `auth:sanctum` |
| **Controller** | `DashboardController@index` |
| **Auth** | Bearer token (Sanctum) |

---

## Response Structure

Response selalu berisi 3 section universal + 1 section role-specific:

```json
{
  "data": {
    "shipments": { ... },      // selalu ada
    "deliveries": { ... },     // selalu ada
    "performance": { ... },    // selalu ada
    "admin": { ... },          // hanya jika role Admin/Super Admin
    "driver": { ... },         // hanya jika role Kurir
    "user": { ... }            // hanya jika role User biasa
  }
}
```

---

## Role-Based Data Filtering

Semua section universal (`shipments`, `deliveries`, `performance`) menerapkan filter yang sama:

| Role | Filter Query | Deskripsi |
|------|-------------|-----------|
| **Admin / Super Admin** | `Shipment::query()` | Melihat semua shipment di sistem |
| **Kurir** | `WHERE assigned_driver_id = {user.id}` | Hanya shipment yang di-assign ke kurir ini |
| **User** | `WHERE created_by = {user.id}` | Hanya shipment yang dibuat oleh user ini |

---

## Section Detail

### 1. `shipments` — Statistik Status

Menghitung jumlah shipment berdasarkan status dan prioritas.

| Field | Type | Deskripsi |
|-------|------|-----------|
| `total` | `integer` | Total semua shipment (sesuai role filter) |
| `today` | `integer` | Shipment yang dibuat hari ini (`created_at = today`) |
| `pending` | `integer` | Status `pending` — menunggu approval |
| `approved` | `integer` | Status `approved` — sudah disetujui, belum di-assign |
| `assigned` | `integer` | Status `assigned` — sudah ditugaskan ke kurir |
| `in_progress` | `integer` | Status `in_progress` — sedang dikirim |
| `completed` | `integer` | Status `completed` — pengiriman selesai |
| `cancelled` | `integer` | Status `cancelled` — dibatalkan |
| `urgent` | `integer` | Prioritas `urgent` (semua status) |

**Contoh:**
```json
{
  "total": 30,
  "today": 4,
  "pending": 0,
  "approved": 0,
  "assigned": 1,
  "in_progress": 1,
  "completed": 28,
  "cancelled": 0,
  "urgent": 18
}
```

---

### 2. `deliveries` — Pengiriman Selesai per Periode

Menghitung shipment yang sudah **completed**, berdasarkan `updated_at` (kapan statusnya berubah ke completed).

| Field | Type | Periode | Filter |
|-------|------|---------|--------|
| `today` | `integer` | Hari ini | `status = completed AND updated_at >= startOfDay` |
| `this_week` | `integer` | Minggu ini (dari Senin) | `status = completed AND updated_at >= startOfWeek` |
| `this_month` | `integer` | Bulan ini | `status = completed AND updated_at >= startOfMonth` |

**Contoh:**
```json
{
  "today": 2,
  "this_week": 5,
  "this_month": 18
}
```

---

### 3. `performance` — Performa Pengiriman

Kalkulasi performa **hanya untuk bulan berjalan** (`updated_at >= startOfMonth`).

| Field | Type | Formula | Deskripsi |
|-------|------|---------|-----------|
| `completion_rate` | `float` | `(completed / total) * 100` | Persentase shipment bulan ini yang sudah selesai. `total` = semua shipment bulan ini (semua status), `completed` = yang berstatus completed |
| `on_time_rate` | `float` | `(onTime / completed) * 100` | Persentase dari yang completed yang selesai tepat waktu. `onTime` = completed di mana `updated_at <= deadline`. Return `0` jika tidak ada completed |

**Penting:**
- `completion_rate` bisa berbeda dari `completed / shipments.total` karena performance hanya menghitung **bulan ini**, sedangkan `shipments.total` menghitung **semua waktu**
- `on_time_rate` menggunakan kolom `deadline` di tabel shipments. Shipment tanpa deadline tidak termasuk dalam hitungan `onTime`

**Contoh:**
```json
{
  "completion_rate": 90.0,
  "on_time_rate": 94.44
}
```

---

### 4. `driver` — Khusus Role Kurir

Hanya muncul jika user login ber-role **Kurir**. Semua data difilter ke `assigned_driver_id = user.id`.

| Field | Type | Deskripsi |
|-------|------|-----------|
| `assigned_today` | `integer` | Shipment yang di-assign ke kurir ini DAN `created_at = today` |
| `in_progress` | `integer` | Shipment milik kurir ini yang sedang `in_progress` |
| `completed_today` | `integer` | Shipment milik kurir ini yang `completed` DAN `updated_at = today` |
| `pending_destinations` | `integer` | Jumlah destination (dari tabel `shipment_destinations`) yang `status = 'pending'` pada shipment milik kurir ini. Menggunakan JOIN ke tabel `shipment_destinations` |

**Contoh:**
```json
{
  "assigned_today": 4,
  "in_progress": 1,
  "completed_today": 2,
  "pending_destinations": 1
}
```

---

### 5. `admin` — Khusus Role Admin / Super Admin

Hanya muncul jika user ber-role **Admin** atau **Super Admin**. Data tidak difilter (melihat semua).

| Field | Type | Deskripsi |
|-------|------|-----------|
| `pending_approvals` | `integer` | Shipment `status = 'pending'` |
| `unassigned_shipments` | `integer` | `status = 'approved'` DAN `assigned_driver_id IS NULL` |
| `active_drivers` | `integer` | User dengan role Kurir DAN `is_active = true` |
| `standby_drivers` | `integer` | Kurir aktif yang TIDAK punya shipment `assigned` / `in_progress` |
| `total_users` | `integer` | Semua user dengan `is_active = true` |
| `recent_shipments` | `array` | 5 shipment terbaru (with `creator` dan `driver` relation) |

**Contoh:**
```json
{
  "pending_approvals": 3,
  "unassigned_shipments": 2,
  "active_drivers": 5,
  "standby_drivers": 2,
  "total_users": 15,
  "recent_shipments": [
    {
      "id": 30,
      "shipment_id": "SPJ-20250526-XYZ",
      "status": "pending",
      "priority": "urgent",
      "created_by": 1,
      "assigned_driver_id": null,
      "created_at": "2025-05-26T10:00:00.000Z",
      "creator": { "id": 1, "name": "Admin" },
      "driver": null
    }
  ]
}
```

---

### 6. `user` — Khusus Role User (Requester)

Hanya muncul jika user bukan Admin dan bukan Kurir. Data difilter ke `created_by = user.id`.

| Field | Type | Deskripsi |
|-------|------|-----------|
| `my_shipments` | `integer` | Total semua shipment milik user |
| `pending_approval` | `integer` | `status = 'pending'` — menunggu approval |
| `in_delivery` | `integer` | `status IN ('assigned', 'in_progress')` — sedang dikirim |
| `completed_this_month` | `integer` | `status = 'completed'` DAN `created_at >= startOfMonth` |

**Contoh:**
```json
{
  "my_shipments": 12,
  "pending_approval": 1,
  "in_delivery": 2,
  "completed_this_month": 8
}
```

---

## Contoh Full Response per Role

### Login sebagai Kurir
```json
{
  "data": {
    "shipments": {
      "total": 30, "today": 4, "pending": 0, "approved": 0,
      "assigned": 1, "in_progress": 1, "completed": 28,
      "cancelled": 0, "urgent": 18
    },
    "deliveries": { "today": 2, "this_week": 5, "this_month": 18 },
    "performance": { "completion_rate": 90.0, "on_time_rate": 94.44 },
    "driver": {
      "assigned_today": 4, "in_progress": 1,
      "completed_today": 2, "pending_destinations": 1
    }
  }
}
```

### Login sebagai Admin
```json
{
  "data": {
    "shipments": {
      "total": 150, "today": 12, "pending": 5, "approved": 3,
      "assigned": 8, "in_progress": 6, "completed": 120,
      "cancelled": 8, "urgent": 45
    },
    "deliveries": { "today": 8, "this_week": 25, "this_month": 80 },
    "performance": { "completion_rate": 85.5, "on_time_rate": 91.2 },
    "admin": {
      "pending_approvals": 5,
      "unassigned_shipments": 3,
      "active_drivers": 5,
      "standby_drivers": 2,
      "total_users": 15,
      "recent_shipments": []
    }
  }
}
```

### Login sebagai User
```json
{
  "data": {
    "shipments": {
      "total": 12, "today": 1, "pending": 1, "approved": 0,
      "assigned": 1, "in_progress": 1, "completed": 8,
      "cancelled": 1, "urgent": 3
    },
    "deliveries": { "today": 0, "this_week": 2, "this_month": 5 },
    "performance": { "completion_rate": 80.0, "on_time_rate": 87.5 },
    "user": {
      "my_shipments": 12,
      "pending_approval": 1,
      "in_delivery": 2,
      "completed_this_month": 5
    }
  }
}
```

---

## Error Responses

| Status | Kondisi |
|--------|---------|
| `401` | Token tidak valid atau expired |
| `500` | Server error (database down, dll) |

---

## Source Code

- Controller: `app/Http/Controllers/Api/DashboardController.php`
- Route: `routes/api.php` (line ~117, di dalam group `auth:sanctum`)
- Model: `app/Models/Shipment.php`
- Tabel: `shipments`, `shipment_destinations`, `users`
