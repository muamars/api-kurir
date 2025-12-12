# Dokumentasi API Laporan Rute Driver

## Ringkasan

Sistem Laporan Rute Driver menyediakan laporan berdasarkan **bulk assignment** (rute). Ketika admin melakukan bulk assign 3 shipment ke 1 driver, itu menjadi **1 rute** dengan 3 kiriman yang memiliki jarak berbeda-beda (dekat, sedang, jauh).

## Konsep Rute

- **1 Bulk Assignment = 1 Rute**
- **1 Rute** berisi beberapa **shipment_id** dengan kategori jarak:
  - **Jarak Dekat**: Jakarta Pusat, Sudirman, Thamrin
  - **Jarak Sedang**: Jakarta Selatan/Utara, Kemang
  - **Jarak Jauh**: Bekasi, Tangerang, Depok, Bogor

## Endpoint

### GET /api/v1/driver/route-report

**Deskripsi**: Mendapatkan laporan rute driver berdasarkan bulk assignment dengan analisis per rute dan per shipment dalam rute tersebut.

**Autentikasi**: Diperlukan (Bearer token)

**Parameter**:
- `driver_id` (opsional): Filter laporan untuk ID driver tertentu
- `bulk_assignment_id` (opsional): Filter laporan untuk rute tertentu
- `date_from` (opsional): Tanggal mulai periode laporan (format YYYY-MM-DD)
- `date_to` (opsional): Tanggal akhir periode laporan (format YYYY-MM-DD)

**Contoh Request**:

```bash
# Dapatkan semua laporan rute
GET /api/v1/driver/route-report

# Dapatkan rute tertentu
GET /api/v1/driver/route-report?bulk_assignment_id=5

# Dapatkan rute driver tertentu
GET /api/v1/driver/route-report?driver_id=2

# Dapatkan rute untuk rentang tanggal
GET /api/v1/driver/route-report?date_from=2025-12-01&date_to=2025-12-12
```

## Format Response

```json
{
  "data": {
    "route_reports": [
      {
        "route_info": {
          "bulk_assignment_id": 5,
          "route_name": "Rute #5",
          "assigned_at": "2025-12-10 08:00:00",
          "admin_name": "Admin User",
          "driver_name": "Kurir Budi",
          "vehicle_type": "Motor",
          "total_shipments": 3
        },
        "shipments": [
          {
            "shipment_id": "SPJ-20251210-ABC123",
            "creator": "PT. Maju Jaya",
            "current_status": "completed",
            "destinations": [
              {
                "destination_id": 15,
                "delivery_address": "Jl. Sudirman No. 123, Jakarta Pusat",
                "receiver_name": "Budi Santoso",
                "current_status": "delivered",
                "distance_category": "Jarak Dekat",
                "timing": {
                  "pickup_time": "2025-12-10T09:00:00.000000Z",
                  "delivered_at": "2025-12-10T10:30:00.000000Z",
                  "delivery_time_minutes": 90,
                  "delivery_time_human": "1 jam 30 menit"
                }
              }
            ],
            "shipment_timing": {
              "start_time": "2025-12-10 09:00:00",
              "end_time": "2025-12-10 10:30:00",
              "total_time_minutes": 90,
              "total_time_human": "1 jam 30 menit"
            }
          },
          {
            "shipment_id": "SPJ-20251210-DEF456",
            "creator": "CV. Berkah",
            "current_status": "completed",
            "destinations": [
              {
                "destination_id": 16,
                "delivery_address": "Jl. Kemang Raya No. 456, Jakarta Selatan",
                "receiver_name": "Sari Dewi",
                "current_status": "delivered",
                "distance_category": "Jarak Sedang",
                "timing": {
                  "pickup_time": "2025-12-10T11:00:00.000000Z",
                  "delivered_at": "2025-12-10T13:15:00.000000Z",
                  "delivery_time_minutes": 135,
                  "delivery_time_human": "2 jam 15 menit"
                }
              }
            ],
            "shipment_timing": {
              "start_time": "2025-12-10 11:00:00",
              "end_time": "2025-12-10 13:15:00",
              "total_time_minutes": 135,
              "total_time_human": "2 jam 15 menit"
            }
          },
          {
            "shipment_id": "SPJ-20251210-GHI789",
            "creator": "Toko Sejahtera",
            "current_status": "completed",
            "destinations": [
              {
                "destination_id": 17,
                "delivery_address": "Jl. Raya Bekasi No. 789, Bekasi",
                "receiver_name": "Ahmad Yani",
                "current_status": "delivered",
                "distance_category": "Jarak Jauh",
                "timing": {
                  "pickup_time": "2025-12-10T14:00:00.000000Z",
                  "delivered_at": "2025-12-10T17:30:00.000000Z",
                  "delivery_time_minutes": 210,
                  "delivery_time_human": "3 jam 30 menit"
                }
              }
            ],
            "shipment_timing": {
              "start_time": "2025-12-10 14:00:00",
              "end_time": "2025-12-10 17:30:00",
              "total_time_minutes": 210,
              "total_time_human": "3 jam 30 menit"
            }
          }
        ],
        "route_summary": {
          "completed_shipments": 3,
          "total_destinations": 3,
          "completed_destinations": 3,
          "route_start_time": "2025-12-10 09:00:00",
          "route_end_time": "2025-12-10 17:30:00",
          "total_route_time": {
            "minutes": 510,
            "human": "8 jam 30 menit"
          },
          "avg_delivery_time_per_shipment": 145.0
        }
      }
    ],
    "overall_stats": {
      "total_routes": 5,
      "total_shipments": 15,
      "completed_routes": 4,
      "avg_route_completion_time": 480.5
    },
    "report_period": {
      "from": "2025-12-01",
      "to": "2025-12-12"
    }
  }
}
```

## Field Response

### Route Info
- `bulk_assignment_id`: ID bulk assignment (ID rute)
- `route_name`: Nama rute (Rute #ID)
- `assigned_at`: Waktu rute ditugaskan
- `admin_name`: Nama admin yang menugaskan
- `driver_name`: Nama driver yang ditugaskan
- `vehicle_type`: Jenis kendaraan
- `total_shipments`: Total shipment dalam rute

### Shipments (per rute)
- `shipment_id`: ID shipment
- `creator`: Pembuat shipment
- `current_status`: Status shipment saat ini
- `destinations`: Array destinasi dalam shipment
  - `distance_category`: Kategori jarak (Dekat/Sedang/Jauh)
  - `timing`: Waktu pickup sampai delivered
- `shipment_timing`: Total waktu untuk menyelesaikan shipment

### Route Summary
- `completed_shipments`: Shipment yang selesai dalam rute
- `total_destinations`: Total destinasi dalam rute
- `completed_destinations`: Destinasi yang selesai
- `route_start_time`: Waktu mulai rute (pickup pertama)
- `route_end_time`: Waktu selesai rute (delivery terakhir)
- `total_route_time`: Total waktu menyelesaikan seluruh rute
- `avg_delivery_time_per_shipment`: Rata-rata waktu per shipment

### Overall Stats
- `total_routes`: Total rute dalam laporan
- `total_shipments`: Total shipment di semua rute
- `completed_routes`: Rute yang selesai 100%
- `avg_route_completion_time`: Rata-rata waktu penyelesaian rute

## Fitur Utama

1. **Laporan Berdasarkan Rute**: Setiap bulk assignment menjadi 1 rute dengan beberapa shipment

2. **Kategorisasi Jarak**: Otomatis mengkategorikan jarak berdasarkan alamat:
   - **Jarak Dekat**: Area Jakarta Pusat
   - **Jarak Sedang**: Area Jakarta lainnya
   - **Jarak Jauh**: Area luar Jakarta (Bekasi, Tangerang, dll)

3. **Analisis Per Rute**: 
   - Waktu total rute (dari pickup pertama sampai delivery terakhir)
   - Rata-rata waktu per shipment dalam rute
   - Tingkat penyelesaian rute

4. **Analisis Per Shipment dalam Rute**:
   - Waktu pengiriman per shipment
   - Kategori jarak setiap destinasi
   - Status penyelesaian

## Kasus Penggunaan

- **Analisis Efisiensi Rute**: Melihat berapa lama driver menyelesaikan 1 rute lengkap
- **Optimasi Penugasan**: Menentukan kombinasi shipment terbaik untuk 1 rute
- **Perbandingan Jarak**: Melihat perbedaan waktu pengiriman berdasarkan kategori jarak
- **Evaluasi Performa**: Membandingkan waktu penyelesaian antar rute
- **Perencanaan Rute**: Data untuk merencanakan rute yang lebih efisien

## Contoh Skenario

**Admin bulk assign 3 shipment ke Driver Budi:**
- Shipment A → Sudirman (Jarak Dekat) → 90 menit
- Shipment B → Kemang (Jarak Sedang) → 135 menit  
- Shipment C → Bekasi (Jarak Jauh) → 210 menit

**Total Rute**: 8 jam 30 menit (dari pickup pertama sampai delivery terakhir)
**Rata-rata per Shipment**: 145 menit

Laporan ini membantu admin memahami efisiensi rute dan merencanakan penugasan yang lebih optimal berdasarkan jarak dan waktu tempuh.