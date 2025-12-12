# Dokumentasi API Laporan Performa Driver (Individual)

## Ringkasan

Sistem Laporan Performa Driver menyediakan laporan otomatis performa pengiriman driver **per shipment individual** di berbagai lokasi. Berbeda dengan laporan rute yang fokus pada bulk assignment, laporan ini menganalisis performa driver secara keseluruhan tanpa mempertimbangkan pengelompokan rute.

## Perbedaan dengan Laporan Rute

- **Laporan Performa Driver**: Analisis **semua shipment individual** yang pernah dikerjakan driver
- **Laporan Rute Driver**: Analisis **per rute (bulk assignment)** dengan beberapa shipment sekaligus

## Endpoint

### GET /api/v1/driver/performance-report

**Deskripsi**: Mendapatkan laporan performa driver otomatis yang menunjukkan waktu pengiriman ke berbagai lokasi untuk setiap driver.

**Autentikasi**: Diperlukan (Bearer token)

**Parameter**:
- `driver_id` (opsional): Filter laporan untuk ID driver tertentu
- `date_from` (opsional): Tanggal mulai periode laporan (format YYYY-MM-DD)
- `date_to` (opsional): Tanggal akhir periode laporan (format YYYY-MM-DD)

**Contoh Request**:

```bash
# Dapatkan laporan performa semua driver
GET /api/v1/driver/performance-report

# Dapatkan performa driver tertentu
GET /api/v1/driver/performance-report?driver_id=2

# Dapatkan laporan performa untuk rentang tanggal
GET /api/v1/driver/performance-report?date_from=2025-12-01&date_to=2025-12-12

# Dapatkan driver tertentu untuk rentang tanggal
GET /api/v1/driver/performance-report?driver_id=2&date_from=2025-12-01&date_to=2025-12-12
```

## Format Response

```json
{
  "data": {
    "driver_reports": [
      {
        "driver": {
          "id": 2,
          "name": "Kurir User"
        },
        "deliveries": [
          {
            "shipment_id": "SPJ-20251210-ABC123",
            "location": "Jl. Sudirman No. 123, Jakarta Pusat",
            "receiver_name": "Budi Santoso",
            "delivery_time": "2 jam 15 menit",
            "delivery_time_minutes": 135,
            "status": "delivered",
            "delivered_at": "2025-12-10 14:30:00"
          },
          {
            "shipment_id": "SPJ-20251210-DEF456",
            "location": "Jl. Thamrin No. 456, Jakarta Pusat",
            "receiver_name": "Sari Dewi",
            "delivery_time": "3 jam 45 menit",
            "delivery_time_minutes": 225,
            "status": "delivered",
            "delivered_at": "2025-12-10 16:45:00"
          }
        ],
        "summary": {
          "total_shipments": 5,
          "total_destinations": 8,
          "completed_destinations": 6,
          "avg_delivery_time": 180.5,
          "fastest_delivery": "135 menit",
          "slowest_delivery": "225 menit"
        }
      }
    ],
    "overall_stats": {
      "total_drivers": 3,
      "total_deliveries": 15,
      "total_locations": 20,
      "avg_delivery_time": 165.2
    },
    "report_period": {
      "from": "2025-12-01",
      "to": "2025-12-12"
    }
  }
}
```

## Field Response

### Laporan Driver
- `driver`: Informasi driver (id, nama)
- `deliveries`: Array catatan pengiriman diurutkan berdasarkan waktu pengiriman (tercepat dulu)
  - `shipment_id`: Identifier shipment
  - `location`: Alamat pengiriman
  - `receiver_name`: Nama penerima
  - `delivery_time`: Durasi pengiriman yang mudah dibaca
  - `delivery_time_minutes`: Waktu pengiriman dalam menit (dari pickup sampai delivered)
  - `status`: Status destinasi saat ini
  - `delivered_at`: Timestamp ketika dikirim
- `summary`: Ringkasan performa driver
  - `total_shipments`: Total shipment yang ditugaskan ke driver
  - `total_destinations`: Total destinasi untuk driver ini
  - `completed_destinations`: Destinasi yang berhasil dikirim
  - `avg_delivery_time`: Rata-rata waktu pengiriman dalam menit
  - `fastest_delivery`: Waktu pengiriman tercepat
  - `slowest_delivery`: Waktu pengiriman terlama

### Statistik Keseluruhan
- `total_drivers`: Jumlah driver dalam laporan
- `total_deliveries`: Total pengiriman yang selesai
- `total_locations`: Total destinasi di semua driver
- `avg_delivery_time`: Rata-rata waktu pengiriman keseluruhan dalam menit

### Periode Laporan
- `from`: Tanggal mulai periode laporan
- `to`: Tanggal akhir periode laporan

## Fitur Utama

1. **Generasi Laporan Otomatis**: Tidak perlu input manual - laporan dibuat otomatis berdasarkan penugasan driver dan data pengiriman

2. **Pelacakan Multi-Lokasi**: Menunjukkan waktu pengiriman ke berbagai lokasi untuk setiap driver: "Driver mengirim ke lokasi A dalam X menit, lokasi B dalam Y menit, lokasi C dalam Z menit"

3. **Metrik Performa**: Menyediakan waktu pengiriman tercepat, terlama, dan rata-rata per driver

4. **Filter Fleksibel**: Filter berdasarkan driver tertentu, rentang tanggal, atau keduanya

5. **Hasil Terurut**: Pengiriman diurutkan berdasarkan waktu pengiriman (tercepat dulu) untuk analisis performa yang mudah

## Kasus Penggunaan

- **Monitoring Performa**: Melacak berapa lama setiap driver mengirim ke berbagai lokasi
- **Optimasi Rute**: Mengidentifikasi lokasi mana yang membutuhkan waktu lebih lama untuk driver tertentu
- **Perbandingan Driver**: Membandingkan performa pengiriman antar driver
- **Analisis Waktu**: Menganalisis pola pengiriman dalam periode waktu tertentu
- **Laporan Efisiensi**: Membuat laporan untuk manajemen tentang efisiensi driver

## Catatan

- Waktu pengiriman dihitung dari status "picked" sampai status "delivered"
- Hanya pengiriman yang selesai (dengan timestamp pickup dan delivery) yang disertakan dalam laporan
- Waktu ditampilkan dalam format yang mudah dibaca dan menit untuk pemrosesan yang mudah
- Laporan otomatis menyertakan semua driver dengan shipment yang ditugaskan kecuali difilter berdasarkan driver_id