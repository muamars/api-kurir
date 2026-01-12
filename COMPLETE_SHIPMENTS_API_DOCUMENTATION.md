# Complete Shipments API Documentation

## Endpoint: Complete Shipments

**URL:** `POST /api/v1/shipments/complete-shipments`

**Description:** Admin dapat menyelesaikan satu atau beberapa shipment sekaligus dengan menginput biaya pengiriman, jenis kendaraan, dan foto bukti (opsional).

### Authentication
- **Required:** Yes
- **Type:** Bearer Token
- **Permission:** `approve-shipments`
- **Role:** Admin only

### Request Headers
```
Authorization: Bearer {admin_token}
Content-Type: multipart/form-data
```

### Request Body (Form Data)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `shipment_ids` | array | Yes | Array berisi ID shipment yang akan diselesaikan |
| `shipment_ids.*` | integer | Yes | ID shipment yang valid (harus ada di database) |
| `shipping_cost` | integer | Yes | Biaya pengiriman dalam rupiah (integer murni, tanpa desimal) |
| `vehicle_used` | string | Yes | Jenis kendaraan yang digunakan (input bebas, max 255 karakter) |
| `completion_photo` | file | No | Foto bukti pengiriman (jpeg/png/jpg, max 5MB) |

### Example Request

```bash
curl -X POST "http://127.0.0.1:8000/api/v1/shipments/complete-shipments" \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: multipart/form-data" \
  -F "shipment_ids[]=1" \
  -F "shipment_ids[]=2" \
  -F "shipment_ids[]=3" \
  -F "shipping_cost=150000" \
  -F "vehicle_used=Mobil Pick Up Toyota Hilux" \
  -F "completion_photo=@/path/to/photo.jpg"
```

### Validation Rules

1. **shipment_ids**: Minimal 1 shipment harus dipilih
2. **shipping_cost**: Harus berupa integer positif atau 0
3. **vehicle_used**: Wajib diisi, maksimal 255 karakter
4. **completion_photo**: Opsional, format gambar (jpeg/png/jpg), maksimal 5MB
5. **Shipment Status**: Hanya shipment dengan status `pending`, `assigned`, atau `in_progress` yang bisa diselesaikan

### Response Success (200)

```json
{
  "message": "3 shipments completed successfully",
  "completed_count": 3,
  "shipping_cost": 150000,
  "vehicle_used": "Mobil Pick Up Toyota Hilux",
  "completion_photo": "completion_photos/completion_1673512345_abc123def.jpg",
  "completed_at": "2026-01-12 11:30:45",
  "shipments": [
    {
      "id": 1,
      "shipment_id": "SPJ-20260112-ABC123",
      "status": "completed",
      "shipping_cost": 150000,
      "vehicle_used": "Mobil Pick Up Toyota Hilux",
      "completion_photo": "completion_photos/completion_1673512345_abc123def.jpg",
      "completed_at": "2026-01-12 11:30:45",
      "completed_by": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com"
      },
      "creator": {
        "id": 5,
        "name": "User Requester",
        "email": "user@example.com"
      },
      "driver": {
        "id": 10,
        "name": "Driver Name",
        "phone": "08123456789"
      },
      "destinations": [
        {
          "id": 1,
          "delivery_address": "Jl. Sudirman No. 123, Jakarta",
          "receiver_name": "PT. ABC Company",
          "status": "completed"
        }
      ],
      "items": [
        {
          "id": 1,
          "item_name": "Dokumen Kontrak",
          "quantity": 1
        }
      ]
    }
  ]
}
```

### Response Error (400)

```json
{
  "message": "Some shipments cannot be completed (invalid status or not found)"
}
```

### Response Error (422) - Validation

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "shipment_ids": ["Pilih minimal satu shipment"],
    "shipping_cost": ["Biaya pengiriman wajib diisi"],
    "vehicle_used": ["Jenis kendaraan wajib diisi"],
    "completion_photo": ["Ukuran gambar maksimal 5MB"]
  }
}
```

### Response Error (403) - Unauthorized

```json
{
  "message": "Forbidden"
}
```

### Response Error (500) - Server Error

```json
{
  "message": "Failed to complete shipments",
  "error": "Database connection error"
}
```

## Business Logic

### Proses yang Terjadi:

1. **Authorization Check**: Memverifikasi user memiliki permission `approve-shipments`
2. **Validation**: Validasi semua input sesuai rules
3. **Status Check**: Memastikan semua shipment dalam status yang valid (`pending`, `assigned`, `in_progress`)
4. **Photo Upload**: Jika ada foto, upload ke `storage/app/public/completion_photos/`
5. **Database Update**: 
   - Update shipment: status → `completed`, tambah data biaya, kendaraan, foto, waktu
   - Update destinations: semua status → `completed`
6. **Notifications**: Kirim notifikasi ke creator dan driver (jika ada)
7. **Response**: Return data shipment yang berhasil diselesaikan

### File Storage:
- **Path**: `storage/app/public/completion_photos/`
- **Naming**: `completion_{timestamp}_{random}.{extension}`
- **Access**: Via `/storage/completion_photos/{filename}`

### Use Cases:

1. **Bulk Completion**: Admin menyelesaikan beberapa shipment sekaligus
2. **Cost Recording**: Menyimpan biaya untuk laporan keuangan
3. **Vehicle Tracking**: Mencatat kendaraan yang digunakan
4. **Documentation**: Foto bukti untuk audit trail
5. **Workflow Completion**: Mengakhiri siklus shipment tanpa melibatkan driver

### Integration Points:

- **Dashboard**: Data biaya untuk chart dan statistik
- **Reports**: Export data untuk laporan keuangan
- **Notifications**: Real-time update ke semua stakeholder
- **Audit Trail**: Tracking siapa dan kapan shipment diselesaikan

## Notes

- Endpoint ini dirancang untuk skenario dimana Admin langsung menyelesaikan shipment tanpa proses delivery normal
- Biaya disimpan sebagai integer murni (dalam rupiah) untuk akurasi perhitungan
- Jenis kendaraan adalah input bebas, tidak terikat dengan master data vehicle_types
- Foto bersifat opsional tergantung kebijakan perusahaan
- Semua shipment dalam request akan memiliki data completion yang sama (biaya, kendaraan, foto)