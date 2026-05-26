# Dashboard Endpoint — Skema & Diagram

## A. ERD — Tabel yang Terlibat

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string email
        boolean is_active
        bigint division_id FK
        timestamps created_at
        timestamps updated_at
    }

    shipments {
        bigint id PK
        string shipment_id UK "SPJ-20250115-ABC"
        string status "pending|approved|assigned|in_progress|completed|cancelled"
        string priority "regular|urgent"
        bigint created_by FK "users.id — pembuat"
        bigint assigned_driver_id FK "users.id — kurir"
        bigint approved_by FK "users.id"
        bigint completed_by FK "users.id"
        bigint cancelled_by FK "users.id"
        datetime deadline "batas waktu pengiriman"
        datetime completed_at
        bigint category_id FK
        bigint vehicle_type_id FK
        string vehicle_used
        bigint shipping_cost
        timestamps created_at
        timestamps updated_at
    }

    shipment_destinations {
        bigint id PK
        bigint shipment_id FK
        bigint customer_id FK
        string receiver_company
        string receiver_name
        string receiver_contact
        text delivery_address
        integer sequence_order
        string status "pending|in_progress|not_delivered|completed|failed"
        timestamps created_at
        timestamps updated_at
    }

    users ||--o{ shipments : "created_by"
    users ||--o{ shipments : "assigned_driver_id"
    shipments ||--o{ shipment_destinations : "shipment_id"
```

---

## B. Flowchart — Alur Response per Role

```mermaid
flowchart TD
    A["GET /api/v1/dashboard"] --> B["auth:sanctum<br/>validasi Bearer token"]
    B --> C["Ambil user dari request"]
    C --> D["getPrivateShipmentStats()"]
    C --> E["getPrivateDeliveryStats()"]
    C --> F["getPrivatePerformanceStats()"]

    D --> G{Deteksi Role}
    E --> G
    F --> G

    G -->|Admin / Super Admin| H["getAdminStats()<br/>→ section: admin"]
    G -->|Kurir| I["getDriverStats()<br/>→ section: driver"]
    G -->|User| J["getUserStats()<br/>→ section: user"]

    H --> K["Return JSON response"]
    I --> K
    J --> K

    style A fill:#e0f2fe
    style B fill:#fef3c7
    style K fill:#dcfce7
```

---

## C. Role-Based Query Filter

```mermaid
flowchart LR
    subgraph "Query Scope per Role"
        Admin["Admin / Super Admin<br/>Shipment::query()<br/>→ SEMUA data"]
        Kurir["Kurir<br/>where assigned_driver_id = user.id<br/>→ shipment milik kurir"]
        User["User<br/>where created_by = user.id<br/>→ shipment yang dibuat sendiri"]
    end
```

---

## D. Field Mapping — Dari Mana Setiap Nilai Berasal

### Section: `shipments`

| Field | Kolom DB | Query |
|-------|----------|-------|
| `total` | `shipments.*` | `COUNT(*)` semua shipment (sesuai role) |
| `today` | `shipments.created_at` | `COUNT(*) WHERE created_at = TODAY` |
| `pending` | `shipments.status` | `COUNT(*) WHERE status = 'pending'` |
| `approved` | `shipments.status` | `COUNT(*) WHERE status = 'approved'` |
| `assigned` | `shipments.status` | `COUNT(*) WHERE status = 'assigned'` |
| `in_progress` | `shipments.status` | `COUNT(*) WHERE status = 'in_progress'` |
| `completed` | `shipments.status` | `COUNT(*) WHERE status = 'completed'` |
| `cancelled` | `shipments.status` | `COUNT(*) WHERE status = 'cancelled'` |
| `urgent` | `shipments.priority` | `COUNT(*) WHERE priority = 'urgent'` |

### Section: `deliveries`

> Hanya menghitung shipment yang sudah **completed**, berdasarkan `updated_at`.

| Field | Periode | Query |
|-------|---------|-------|
| `today` | Hari ini | `WHERE status = 'completed' AND updated_at >= startOfDay` |
| `this_week` | Minggu ini (Senin) | `WHERE status = 'completed' AND updated_at >= startOfWeek` |
| `this_month` | Bulan ini | `WHERE status = 'completed' AND updated_at >= startOfMonth` |

### Section: `performance`

> Scope: **bulan berjalan saja** (`updated_at >= startOfMonth`).

| Field | Formula | Penjelasan |
|-------|---------|------------|
| `completion_rate` | `(completed / total) * 100` | `total` = semua shipment bulan ini, `completed` = yang berstatus completed |
| `on_time_rate` | `(onTime / completed) * 100` | `onTime` = completed yang `updated_at <= deadline`. Return 0 jika tidak ada completed |

### Section: `driver` (khusus role Kurir)

| Field | Kolom DB | Query |
|-------|----------|-------|
| `assigned_today` | `shipments.created_at` | `WHERE assigned_driver_id = user.id AND created_at = TODAY` |
| `in_progress` | `shipments.status` | `WHERE assigned_driver_id = user.id AND status = 'in_progress'` |
| `completed_today` | `shipments.updated_at` | `WHERE assigned_driver_id = user.id AND status = 'completed' AND updated_at = TODAY` |
| `pending_destinations` | `shipment_destinations.status` | `JOIN shipments → WHERE driver_id = user.id AND destination.status = 'pending'` |

### Section: `admin` (khusus role Admin)

| Field | Kolom DB | Query |
|-------|----------|-------|
| `pending_approvals` | `shipments.status` | `WHERE status = 'pending'` |
| `unassigned_shipments` | `shipments.assigned_driver_id` | `WHERE status = 'approved' AND assigned_driver_id IS NULL` |
| `active_drivers` | `users.is_active` | `WHERE role = 'Kurir' AND is_active = true` |
| `standby_drivers` | `shipments.status` | Kurir aktif yang TIDAK punya shipment `assigned`/`in_progress` |
| `total_users` | `users.is_active` | `WHERE is_active = true` |
| `recent_shipments` | `shipments.*` | 5 terbaru, `ORDER BY created_at DESC LIMIT 5` |

### Section: `user` (khusus role User/Requester)

| Field | Kolom DB | Query |
|-------|----------|-------|
| `my_shipments` | `shipments.created_by` | `WHERE created_by = user.id` |
| `pending_approval` | `shipments.status` | `WHERE created_by = user.id AND status = 'pending'` |
| `in_delivery` | `shipments.status` | `WHERE created_by = user.id AND status IN ('assigned', 'in_progress')` |
| `completed_this_month` | `shipments.created_at` | `WHERE created_by = user.id AND status = 'completed' AND created_at >= startOfMonth` |
