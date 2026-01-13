# Shipping Service Report API Documentation

## Endpoint: Shipping Service Analysis Report

**URL:** `GET /api/v1/dashboard/shipping-service-report`

**Description:** Endpoint untuk analisa pengiriman berdasarkan jenis layanan (kurir online vs kurir internal). 

**✅ LOGIKA BARU:**
- **Internal Courier**: Shipment yang di-assign via `bulk-assign-driver` endpoint (memiliki `assigned_driver_id`)
- **Online Courier**: Shipment yang di-complete via `complete-shipments` endpoint (memiliki `vehicle_used` & `shipping_cost`)

### Authentication
- **Required:** Yes
- **Type:** Bearer Token
- **Role:** All authenticated users (Admin, Kurir, User)

### Request Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date_from` | date | No | Tanggal mulai filter (Y-m-d) |
| `date_to` | date | No | Tanggal akhir filter (Y-m-d) |
| `period` | string | No | Periode preset: `today`, `week`, `month`, `quarter`, `year`, `custom` |
| `group_by` | string | No | Grouping data: `day`, `week`, `month`, `year` |

### Example Requests

#### 1. Laporan Bulan Ini
```bash
GET /api/v1/dashboard/shipping-service-report?period=month
```

#### 2. Laporan Custom dengan Grouping Harian
```bash
GET /api/v1/dashboard/shipping-service-report?date_from=2026-01-01&date_to=2026-01-31&group_by=day
```

#### 3. Laporan Tahun Ini dengan Grouping Bulanan
```bash
GET /api/v1/dashboard/shipping-service-report?period=year&group_by=month
```

### Response Success (200)

```json
{
  "data": {
    "summary": {
      "total_shipments": 150,
      "total_cost": 25000000,
      "average_cost_per_shipment": 166666.67
    },
    "service_comparison": {
      "online_courier": {
        "count": 90,
        "percentage": 60.0,
        "total_cost": 18000000,
        "average_cost": 200000.0,
        "cost_percentage": 72.0
      },
      "internal_courier": {
        "count": 60,
        "percentage": 40.0,
        "total_cost": 7000000,
        "average_cost": 116666.67,
        "cost_percentage": 28.0
      }
    },
    "vehicle_analysis": [
      {
        "vehicle": "GoSend",
        "count": 45,
        "total_cost": 9000000,
        "average_cost": 200000.0,
        "service_type": "Online Courier"
      },
      {
        "vehicle": "Mobil Pick Up Toyota Hilux",
        "count": 30,
        "total_cost": 4500000,
        "average_cost": 150000.0,
        "service_type": "Internal Courier"
      },
      {
        "vehicle": "JNE Express",
        "count": 25,
        "total_cost": 5000000,
        "average_cost": 200000.0,
        "service_type": "Online Courier"
      }
    ],
    "time_breakdown": [
      {
        "period": "2026-01",
        "period_label": "Jan 2026",
        "online_courier": {
          "count": 35,
          "cost": 7000000
        },
        "internal_courier": {
          "count": 25,
          "cost": 3750000
        },
        "total": {
          "count": 60,
          "cost": 10750000
        }
      },
      {
        "period": "2026-02",
        "period_label": "Feb 2026",
        "online_courier": {
          "count": 40,
          "cost": 8000000
        },
        "internal_courier": {
          "count": 20,
          "cost": 3000000
        },
        "total": {
          "count": 60,
          "cost": 11000000
        }
      }
    ],
    "detailed_breakdown": {
      "online_services": [
        {
          "vehicle": "GoSend",
          "count": 45,
          "total_cost": 9000000,
          "average_cost": 200000.0,
          "shipments": [
            {
              "shipment_id": "SPJ-20260112-ABC123",
              "cost": 200000,
              "driver": "No Driver",
              "completed_at": "2026-01-12 10:30:00"
            }
          ]
        }
      ],
      "internal_services": [
        {
          "vehicle": "Mobil Pick Up Toyota Hilux",
          "count": 30,
          "total_cost": 4500000,
          "average_cost": 150000.0,
          "shipments": [
            {
              "shipment_id": "SPJ-20260112-DEF456",
              "cost": 150000,
              "driver": "Driver Internal",
              "completed_at": "2026-01-12 11:00:00"
            }
          ]
        }
      ]
    }
  },
  "meta": {
    "period": "month",
    "date_range": {
      "from": "2026-01-01",
      "to": "2026-01-31"
    },
    "group_by": "month",
    "user_scope": "all_data",
    "generated_at": "2026-01-12 12:30:00"
  }
}
```

### Response Error (422) - Validation

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "date_to": ["The date to must be a date after or equal to date from."],
    "period": ["The selected period is invalid."],
    "group_by": ["The selected group by is invalid."]
  }
}
```

## Business Logic

### ✅ KATEGORISASI LAYANAN BARU (Updated)

#### **Internal Courier Logic:**
- Shipment yang memiliki `assigned_driver_id` (tidak null)
- Artinya shipment ini di-assign melalui endpoint `POST /api/v1/shipments/bulk-assign-driver`
- Menggunakan driver internal perusahaan

#### **Online Courier Logic:**
- Shipment yang memiliki `vehicle_used` dan `shipping_cost` (keduanya tidak null)
- Artinya shipment ini di-complete melalui endpoint `POST /api/v1/shipments/complete-shipments`
- Menggunakan layanan kurir online/eksternal

### Logika Kategorisasi

```php
// Internal Courier
if (!is_null($shipment->assigned_driver_id)) {
    return 'Internal Courier';
}

// Online Courier  
if (!is_null($shipment->vehicle_used) && !is_null($shipment->shipping_cost)) {
    return 'Online Courier';
}
```

### Role-Based Data Access

- **Admin**: Melihat semua data shipment completed
- **Kurir**: Hanya shipment yang assigned ke mereka
- **User**: Hanya shipment yang mereka buat

### Analisa yang Disediakan

1. **Summary**: Total shipment, biaya, rata-rata biaya
2. **Service Comparison**: Perbandingan online vs internal (count, percentage, cost)
3. **Vehicle Analysis**: Top 20 kendaraan dengan statistik
4. **Time Breakdown**: Breakdown berdasarkan periode (jika group_by diset)
5. **Detailed Breakdown**: Detail per kendaraan dengan list shipment

### Use Cases

1. **Analisa Efisiensi Biaya**: Membandingkan biaya online vs internal courier
2. **Trend Analysis**: Melihat pola penggunaan layanan per periode
3. **Vehicle Performance**: Menganalisa kendaraan yang paling sering digunakan
4. **Budget Planning**: Data untuk perencanaan budget pengiriman
5. **Service Optimization**: Menentukan layanan mana yang lebih cost-effective

### Integration Points

- **Dashboard Charts**: Data untuk visualisasi grafik
- **Financial Reports**: Export data untuk laporan keuangan
- **Management Reports**: Insight untuk decision making
- **Cost Analysis**: Breakdown biaya per jenis layanan

## Example Usage Scenarios

### 1. Monthly Cost Analysis
```bash
GET /api/v1/dashboard/shipping-service-report?period=month&group_by=week
```
Untuk melihat breakdown biaya mingguan dalam bulan ini.

### 2. Quarterly Trend Analysis
```bash
GET /api/v1/dashboard/shipping-service-report?period=quarter&group_by=month
```
Untuk melihat trend penggunaan layanan per bulan dalam quarter ini.

### 3. Custom Period Analysis
```bash
GET /api/v1/dashboard/shipping-service-report?date_from=2025-12-01&date_to=2026-01-31&group_by=month
```
Untuk analisa custom periode dengan grouping bulanan.

### 4. Daily Operations Report
```bash
GET /api/v1/dashboard/shipping-service-report?period=week&group_by=day
```
Untuk melihat operasional harian dalam seminggu terakhir.

## Notes

- Data hanya menampilkan shipment dengan status `completed`
- Kategorisasi berdasarkan keyword matching pada field `vehicle_used`
- Biaya dalam format integer (rupiah)
- Endpoint ini mendukung role-based access control
- Response time tergantung jumlah data dan kompleksitas grouping