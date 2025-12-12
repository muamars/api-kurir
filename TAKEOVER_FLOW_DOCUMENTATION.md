# Takeover Flow Documentation

## Overview
Takeover adalah fitur untuk kurir mengembalikan shipment ke admin ketika tidak bisa menyelesaikan pengiriman. Shipment akan kembali ke status `pending` dan bisa di-assign ke driver lain.

---

## Kapan Takeover Bisa Dilakukan?

Takeover dapat dilakukan ketika destination dalam status:
- ✅ **picked** - Setelah barang sudah diambil/pickup
- ✅ **in_progress** - Sedang dalam perjalanan pengiriman

Takeover **TIDAK** bisa dilakukan dari status:
- ❌ `pending` - Belum pickup
- ❌ `arrived` - Sudah sampai lokasi
- ❌ `delivered` - Sudah diserahkan
- ❌ `completed` - Sudah selesai
- ❌ `returning` - Sedang kembali
- ❌ `finished` - Sudah selesai semua
- ❌ `failed` - Sudah gagal

---

## Flow Diagram

### Normal Flow (Tanpa Takeover)
```
pending → picked → in_progress → arrived → delivered → completed → returning → finished
```

### Takeover Flow (Dari Picked)
```
pending → picked → TAKEOVER
                     ↓
                  (kembali ke pending, driver = null)
                     ↓
                  assign driver baru
                     ↓
                  picked → in_progress → ...
```

### Takeover Flow (Dari In Progress)
```
pending → picked → in_progress → TAKEOVER
                                    ↓
                                 (kembali ke pending, driver = null)
                                    ↓
                                 assign driver baru
                                    ↓
                                 picked → in_progress → ...
```

### Failed Flow (Alternatif)
```
pending → picked → in_progress → FAILED
                                    ↓
                                 (shipment tetap assigned ke driver)
                                    ↓
                                 bisa retry atau takeover
```

---

## API Endpoint

### Update Progress dengan Takeover
**POST** `/api/v1/shipments/{shipment_id}/destinations/{destination_id}/progress`

**Authorization:** Bearer Token (Role: Kurir yang di-assign)

**Request Body:**
```json
{
  "status": "takeover",
  "takeover_reason": "Alamat tidak ditemukan setelah 3x percobaan",
  "photo": "(optional) foto bukti lokasi",
  "note": "(optional) catatan tambahan"
}
```

**Validation Rules:**
- `status`: required, must be "takeover"
- `takeover_reason`: **required** when status is takeover
- `photo`: optional, image, max 4MB
- `note`: optional, string

**Response Success:**
```json
{
  "message": "Progress updated successfully",
  "data": {
    "progress": {
      "id": 123,
      "shipment_id": 45,
      "destination_id": 67,
      "driver_id": 5,
      "status": "takeover",
      "progress_time": "2025-12-08T10:30:00.000000Z",
      "note": "Alamat tidak ditemukan setelah 3x percobaan",
      "photo_url": "shipment-photos/1234567890_abc.jpg"
    },
    "shipment": {
      "id": 45,
      "shipment_id": "SPJ-2025-001",
      "status": "pending",
      "assigned_driver_id": null
    },
    "destination": {
      "id": 67,
      "status": "takeover",
      "receiver_company": "PT Example",
      "delivery_address": "Jl. Example No. 123"
    }
  }
}
```

**Response Error (Status Tidak Valid):**
```json
{
  "message": "Takeover hanya bisa dilakukan saat status picked atau in_progress",
  "current_status": "delivered"
}
```
Status: 400 Bad Request

**Response Error (Alasan Kosong):**
```json
{
  "message": "Alasan takeover wajib diisi"
}
```
Status: 400 Bad Request

---

## Dampak Takeover

### 1. Shipment
- Status berubah menjadi `pending`
- `assigned_driver_id` menjadi `null`
- Siap untuk di-assign ke driver baru

### 2. Destination
- Status berubah menjadi `takeover`
- Data destination tetap tersimpan

### 3. Progress History
- Progress dengan status `takeover` tersimpan
- Include `takeover_reason` di field `note`
- Photo (jika ada) tersimpan sebagai bukti

### 4. Notifications
- Notifikasi dikirim ke:
  - ✉️ Admin/Creator shipment
  - ✉️ Driver yang melakukan takeover
- Isi notifikasi: alasan takeover

---

## Use Cases

### 1. Alamat Tidak Ditemukan
```json
{
  "status": "takeover",
  "takeover_reason": "Alamat tidak ditemukan setelah 3x percobaan pencarian",
  "photo": "(foto lokasi terakhir)"
}
```

### 2. Penerima Tidak Ada/Tidak Bisa Dihubungi
```json
{
  "status": "takeover",
  "takeover_reason": "Penerima tidak bisa dihubungi, nomor tidak aktif",
  "note": "Sudah mencoba call 5x dan WA"
}
```

### 3. Kendala Kendaraan
```json
{
  "status": "takeover",
  "takeover_reason": "Motor mogok, tidak bisa melanjutkan pengiriman",
  "photo": "(foto motor)"
}
```

### 4. Barang Terlalu Besar/Berat
```json
{
  "status": "takeover",
  "takeover_reason": "Barang terlalu besar untuk motor, perlu mobil pickup",
  "photo": "(foto barang)"
}
```

### 5. Cuaca/Kondisi Jalan
```json
{
  "status": "takeover",
  "takeover_reason": "Jalan banjir, tidak bisa dilalui motor",
  "photo": "(foto banjir)"
}
```

---

## Perbedaan Takeover vs Failed

| Aspek | Takeover | Failed |
|-------|----------|--------|
| **Kapan** | Picked atau In Progress | In Progress |
| **Driver** | Di-unassign (null) | Tetap assigned |
| **Shipment Status** | Kembali ke Pending | Tetap In Progress |
| **Tujuan** | Assign driver baru | Retry oleh driver yang sama |
| **Use Case** | Driver tidak bisa lanjut | Gagal deliver, bisa retry |

---

## Admin Action Setelah Takeover

### 1. Review Alasan Takeover
```
GET /api/v1/shipments/{id}/progress
```
Lihat history progress untuk melihat alasan takeover

### 2. Assign Driver Baru
```
POST /api/v1/shipments/{id}/assign-driver
{
  "driver_id": 10
}
```

### 3. Atau Bulk Assign
```
POST /api/v1/shipments/bulk-assign-driver
{
  "shipment_ids": [45],
  "driver_id": 10
}
```

---

## Frontend Implementation

### 1. Tampilkan Button Takeover
Hanya tampilkan button takeover jika:
- User adalah driver yang di-assign
- Destination status = `picked` atau `in_progress`

```javascript
const canTakeover = computed(() => {
  return (
    destination.status === 'picked' || 
    destination.status === 'in_progress'
  ) && shipment.assigned_driver_id === currentUser.id;
});
```

### 2. Form Takeover
```vue
<template>
  <form @submit.prevent="submitTakeover">
    <textarea 
      v-model="takeoverReason" 
      placeholder="Alasan takeover (wajib diisi)"
      required
    />
    
    <input 
      type="file" 
      @change="handlePhotoUpload"
      accept="image/*"
    />
    
    <button type="submit" :disabled="!takeoverReason">
      Takeover Shipment
    </button>
  </form>
</template>

<script setup>
const takeoverReason = ref('');
const photo = ref(null);

async function submitTakeover() {
  const formData = new FormData();
  formData.append('status', 'takeover');
  formData.append('takeover_reason', takeoverReason.value);
  
  if (photo.value) {
    formData.append('photo', photo.value);
  }
  
  try {
    const response = await axios.post(
      `/api/v1/shipments/${shipmentId}/destinations/${destinationId}/progress`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
          'Authorization': `Bearer ${token}`
        }
      }
    );
    
    // Show success message
    alert('Shipment berhasil di-takeover dan dikembalikan ke admin');
    
    // Redirect to shipment list
    router.push('/shipments');
    
  } catch (error) {
    alert(error.response.data.message);
  }
}
</script>
```

### 3. Konfirmasi Dialog
```javascript
function confirmTakeover() {
  if (confirm(
    'Apakah Anda yakin ingin takeover shipment ini?\n\n' +
    'Shipment akan dikembalikan ke admin dan Anda tidak akan lagi assigned ke shipment ini.'
  )) {
    showTakeoverForm.value = true;
  }
}
```

---

## Database Schema

### shipment_progress
```sql
- status: enum(..., 'takeover', ...)
- note: text (berisi takeover_reason)
- photo_url: varchar (bukti foto)
```

### shipment_destinations
```sql
- status: enum(..., 'takeover', ...)
```

### shipments
```sql
- status: varchar (kembali ke 'pending')
- assigned_driver_id: bigint (menjadi null)
```

---

## Testing

### Test Takeover dari Picked
```bash
curl -X POST http://localhost:8000/api/v1/shipments/1/destinations/1/progress \
  -H "Authorization: Bearer {kurir_token}" \
  -F "status=takeover" \
  -F "takeover_reason=Alamat tidak ditemukan" \
  -F "photo=@/path/to/photo.jpg"
```

### Test Takeover dari In Progress
```bash
curl -X POST http://localhost:8000/api/v1/shipments/1/destinations/1/progress \
  -H "Authorization: Bearer {kurir_token}" \
  -F "status=takeover" \
  -F "takeover_reason=Motor mogok"
```

### Test Error (Status Tidak Valid)
```bash
# Destination status = delivered
curl -X POST http://localhost:8000/api/v1/shipments/1/destinations/1/progress \
  -H "Authorization: Bearer {kurir_token}" \
  -F "status=takeover" \
  -F "takeover_reason=Test"

# Expected: 400 Bad Request
```

---

## Summary

✅ **Takeover bisa dilakukan dari:**
- Status `picked` (setelah pickup)
- Status `in_progress` (sedang dalam perjalanan)

✅ **Wajib menyertakan:**
- `takeover_reason` (alasan takeover)

✅ **Optional:**
- `photo` (foto bukti)
- `note` (catatan tambahan)

✅ **Hasil:**
- Shipment kembali ke `pending`
- Driver di-unassign
- Notifikasi ke admin
- Siap assign driver baru
