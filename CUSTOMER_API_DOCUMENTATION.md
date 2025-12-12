# Customer API Documentation

## Overview
API untuk mengelola data master customer yang dapat digunakan untuk mempercepat input data penerima pada shipment destinations.

## Endpoints

### 1. Get All Customers
**GET** `/api/v1/customers`

Mendapatkan daftar semua customer dengan fitur pencarian dan filter.

**Query Parameters:**
- `search` (optional) - Pencarian berdasarkan company_name, customer_name, atau phone
- `is_active` (optional) - Filter berdasarkan status aktif (true/false)
- `per_page` (optional) - Jumlah data per halaman (default: 15)

**Response:**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "company_name": "PT Maju Jaya",
      "customer_name": "Budi Santoso",
      "phone": "081234567890",
      "address": "Jl. Sudirman No. 123, Jakarta Pusat",
      "is_active": true,
      "created_at": "2025-12-06T08:00:00.000000Z",
      "updated_at": "2025-12-06T08:00:00.000000Z"
    }
  ],
  "total": 5,
  "per_page": 15
}
```

### 2. Get Customer Detail
**GET** `/api/v1/customers/{id}`

Mendapatkan detail customer beserta riwayat shipment destinations.

**Response:**
```json
{
  "data": {
    "id": 1,
    "company_name": "PT Maju Jaya",
    "customer_name": "Budi Santoso",
    "phone": "081234567890",
    "address": "Jl. Sudirman No. 123, Jakarta Pusat",
    "is_active": true,
    "shipment_destinations": [
      {
        "id": 1,
        "shipment_id": 1,
        "receiver_company": "PT Maju Jaya",
        "receiver_name": "Budi Santoso",
        "receiver_contact": "081234567890",
        "delivery_address": "Jl. Sudirman No. 123, Jakarta Pusat"
      }
    ]
  }
}
```

### 3. Create Customer (Admin Only)
**POST** `/api/v1/customers`

Membuat data customer baru.

**Request Body:**
```json
{
  "company_name": "PT Maju Jaya",
  "customer_name": "Budi Santoso",
  "phone": "081234567890",
  "address": "Jl. Sudirman No. 123, Jakarta Pusat",
  "is_active": true
}
```

**Validation Rules:**
- `company_name`: required, string, max 255
- `customer_name`: required, string, max 255
- `phone`: required, string, max 20
- `address`: required, string
- `is_active`: optional, boolean (default: true)

**Response:**
```json
{
  "message": "Customer berhasil ditambahkan",
  "data": {
    "id": 1,
    "company_name": "PT Maju Jaya",
    "customer_name": "Budi Santoso",
    "phone": "081234567890",
    "address": "Jl. Sudirman No. 123, Jakarta Pusat",
    "is_active": true
  }
}
```

### 4. Update Customer (Admin Only)
**PUT** `/api/v1/customers/{id}`

Update data customer.

**Request Body:**
```json
{
  "company_name": "PT Maju Jaya Updated",
  "customer_name": "Budi Santoso",
  "phone": "081234567890",
  "address": "Jl. Sudirman No. 123, Jakarta Pusat",
  "is_active": true
}
```

**Validation Rules:**
- Semua field optional (sometimes)
- Validasi sama dengan create

**Response:**
```json
{
  "message": "Customer berhasil diupdate",
  "data": {
    "id": 1,
    "company_name": "PT Maju Jaya Updated",
    "customer_name": "Budi Santoso",
    "phone": "081234567890",
    "address": "Jl. Sudirman No. 123, Jakarta Pusat",
    "is_active": true
  }
}
```

### 5. Delete Customer (Admin Only)
**DELETE** `/api/v1/customers/{id}`

Menghapus data customer. Customer tidak dapat dihapus jika memiliki riwayat shipment destinations.

**Response Success:**
```json
{
  "message": "Customer berhasil dihapus"
}
```

**Response Error (Has Shipments):**
```json
{
  "message": "Customer tidak dapat dihapus karena memiliki riwayat pengiriman"
}
```

## Integration dengan Shipment Destinations

### Cara Penggunaan di Frontend

1. **Saat membuat shipment baru**, tampilkan dropdown/autocomplete customer:
   ```
   GET /api/v1/customers?is_active=true&search=PT
   ```

2. **Saat user memilih customer**, auto-fill form destination dengan data customer:
   ```javascript
   // Data dari customer yang dipilih
   {
     customer_id: 1,
     receiver_company: customer.company_name,
     receiver_name: customer.customer_name,
     receiver_contact: customer.phone,
     delivery_address: customer.address
   }
   ```

3. **Saat submit shipment**, kirim data destination dengan `customer_id`:
   ```json
   {
     "destinations": [
       {
         "customer_id": 1,
         "receiver_company": "PT Maju Jaya",
         "receiver_name": "Budi Santoso",
         "receiver_contact": "081234567890",
         "delivery_address": "Jl. Sudirman No. 123, Jakarta Pusat",
         "shipment_note": "Lantai 5",
         "sequence_order": 1
       }
     ]
   }
   ```

4. **Auto-save customer baru**: Jika user input manual (tidak pilih dari dropdown), sistem akan otomatis membuat customer baru saat shipment disimpan.

## Database Schema

### Table: customers
```sql
- id (bigint, primary key)
- company_name (varchar 255)
- customer_name (varchar 255)
- phone (varchar 20)
- address (text)
- is_active (boolean, default true)
- created_at (timestamp)
- updated_at (timestamp)
```

### Table: shipment_destinations (updated)
```sql
- id (bigint, primary key)
- shipment_id (bigint, foreign key)
- customer_id (bigint, foreign key, nullable) -- NEW FIELD
- receiver_company (varchar 255)
- receiver_name (varchar 255)
- receiver_contact (varchar 255)
- delivery_address (text)
- shipment_note (text, nullable)
- sequence_order (integer)
- status (enum)
- created_at (timestamp)
- updated_at (timestamp)
```

## Authorization

- **View customers** (GET): Semua authenticated users
- **Create/Update/Delete customers**: Admin only (role: Admin)

## Notes

- Customer dengan `is_active = false` tidak akan muncul di dropdown frontend (gunakan filter `?is_active=true`)
- Customer tidak dapat dihapus jika sudah digunakan di shipment destinations
- Field `customer_id` di shipment_destinations bersifat nullable untuk backward compatibility
- Data customer akan tersimpan redundan di shipment_destinations untuk menjaga integritas data historis
