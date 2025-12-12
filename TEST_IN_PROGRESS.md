# Test Status In Progress

## Status Constraint Database
✅ Database constraint sudah include `in_progress`:
```
'picked', 'in_progress', 'arrived', 'delivered', 'completed', 'returning', 'finished', 'takeover', 'failed'
```

## Cara Test

### 1. Test POST Progress dengan Status in_progress

**Endpoint:**
```
POST http://127.0.0.1:8000/api/v1/shipments/{shipment_id}/destinations/{destination_id}/progress
```

**Headers:**
```
Authorization: Bearer {kurir_token}
Content-Type: multipart/form-data
```

**Body (Form Data):**
```
status: in_progress
note: Sedang dalam perjalanan ke lokasi tujuan
photo: (optional) upload foto
```

**Contoh dengan cURL:**
```bash
curl -X POST http://127.0.0.1:8000/api/v1/shipments/24/destinations/24/progress \
  -H "Authorization: Bearer {your_kurir_token}" \
  -F "status=in_progress" \
  -F "note=Sedang dalam perjalanan ke lokasi"
```

**Expected Response:**
```json
{
  "message": "Progress updated successfully",
  "data": {
    "progress": {
      "id": 128,
      "shipment_id": 24,
      "destination_id": 24,
      "driver_id": 2,
      "status": "in_progress",
      "progress_time": "2025-12-09T10:30:00.000000Z",
      "note": "Sedang dalam perjalanan ke lokasi",
      "photo_url": null
    }
  }
}
```

### 2. Verify - GET Progress

**Endpoint:**
```
GET http://127.0.0.1:8000/api/v1/shipments/24/progress
```

**Expected Response:**
Harus ada entry dengan `"status": "in_progress"`

```json
{
  "data": [
    {
      "id": 128,
      "status": "in_progress",
      "progress_time": "2025-12-09T10:30:00.000000Z",
      "note": "Sedang dalam perjalanan ke lokasi"
    },
    {
      "id": 127,
      "status": "picked",
      ...
    }
  ]
}
```

## Troubleshooting

### Jika Error "status must be one of: picked, arrived, ..."
❌ Validation belum update
✅ **Solusi:** Sudah diperbaiki di controller

### Jika Error "violates check constraint"
❌ Database constraint belum update
✅ **Solusi:** Migration sudah dijalankan, constraint sudah benar

### Jika POST berhasil tapi tidak muncul di GET
❌ Kemungkinan:
1. POST ke shipment/destination yang berbeda
2. Filter di GET progress
3. Cache issue

✅ **Solusi:** 
- Pastikan shipment_id dan destination_id sama
- Clear cache: `php artisan cache:clear`
- Cek langsung di database:
  ```sql
  SELECT * FROM shipment_progress 
  WHERE status = 'in_progress' 
  ORDER BY created_at DESC 
  LIMIT 5;
  ```

## Validation Rules (Updated)

File: `app/Http/Controllers/Api/ShipmentProgressController.php`

```php
$request->validate([
    'status' => 'required|in:picked,in_progress,arrived,delivered,completed,returning,finished,takeover,failed',
    'photo' => 'nullable|image|max:4096',
    'note' => 'nullable|string',
    'receiver_name' => 'required_if:status,delivered|string',
    'received_photo' => 'nullable|image|max:4096',
    'takeover_reason' => 'required_if:status,takeover|string',
]);
```

## Status Flow dengan in_progress

### Normal Flow:
```
pending → picked → in_progress → arrived → delivered → completed → returning → finished
```

### Use Case in_progress:
- Kurir sudah pickup barang (picked)
- Kurir update: "Sedang dalam perjalanan" (in_progress)
- Kurir bisa update beberapa kali dengan status in_progress untuk update lokasi/kondisi
- Kurir sampai lokasi (arrived)
- Kurir serahkan barang (delivered)

### Contoh Timeline:
```
07:00 - picked: "Barang sudah diambil"
07:15 - in_progress: "Sedang menuju lokasi A"
07:45 - in_progress: "Terjebak macet di Jl. Sudirman"
08:30 - in_progress: "Sudah melewati macet, melanjutkan perjalanan"
09:00 - arrived: "Sudah sampai di lokasi"
09:15 - delivered: "Barang sudah diserahkan"
```

## Frontend Implementation

### Button untuk Update In Progress
```vue
<button 
  @click="updateInProgress"
  v-if="canUpdateInProgress"
  class="btn-primary"
>
  Update Perjalanan
</button>

<script setup>
const canUpdateInProgress = computed(() => {
  // Bisa update in_progress setelah picked dan sebelum arrived
  return ['picked', 'in_progress'].includes(destination.status);
});

async function updateInProgress() {
  const note = prompt('Update kondisi perjalanan:');
  if (!note) return;
  
  const formData = new FormData();
  formData.append('status', 'in_progress');
  formData.append('note', note);
  
  try {
    await axios.post(
      `/api/v1/shipments/${shipmentId}/destinations/${destinationId}/progress`,
      formData
    );
    
    alert('Progress berhasil diupdate');
    refreshProgress();
  } catch (error) {
    alert(error.response?.data?.message || 'Gagal update progress');
  }
}
</script>
```
