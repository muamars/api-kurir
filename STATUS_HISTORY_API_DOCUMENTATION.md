# Status History API Documentation

## Overview
API untuk melihat riwayat perubahan status destination dengan deskripsi yang jelas dan mudah dipahami.

---

## Endpoint

### Get Destination Status History
**GET** `/api/v1/shipments/{shipment_id}/destinations/{destination_id}/status-history`

Mendapatkan riwayat perubahan status untuk destination tertentu.

**Authorization:** Bearer Token (Semua authenticated users)

**Request:**
```
GET http://localhost:8000/api/v1/shipments/1/destinations/1/status-history
Authorization: Bearer {token}
```

**Response:**
```json
{
  "data": [
    {
      "id": 5,
      "old_status": "returning",
      "new_status": "finished",
      "old_status_label": "Perjalanan Pulang",
      "new_status_label": "Sampai di Kantor",
      "status_description": "Sudah sampai di kantor finish",
      "note": "Sampai kantor pukul 17:30",
      "changed_at": "2025-12-08 17:30:00",
      "changed_by": {
        "id": 5,
        "name": "Agus Kurir"
      }
    },
    {
      "id": 4,
      "old_status": "completed",
      "new_status": "returning",
      "old_status_label": "Selesai",
      "new_status_label": "Perjalanan Pulang",
      "status_description": "Arah pulang ke kantor",
      "note": "Mulai perjalanan pulang",
      "changed_at": "2025-12-08 16:45:00",
      "changed_by": {
        "id": 5,
        "name": "Agus Kurir"
      }
    },
    {
      "id": 3,
      "old_status": "delivered",
      "new_status": "completed",
      "old_status_label": "Sudah Diterima",
      "new_status_label": "Selesai",
      "status_description": "Barang completed semua",
      "note": "Semua barang sudah diserahkan",
      "changed_at": "2025-12-08 16:30:00",
      "changed_by": {
        "id": 5,
        "name": "Agus Kurir"
      }
    },
    {
      "id": 2,
      "old_status": "arrived",
      "new_status": "delivered",
      "old_status_label": "Sampai di Lokasi",
      "new_status_label": "Sudah Diterima",
      "status_description": "Sudah diterima",
      "note": "Diterima oleh Budi Santoso",
      "changed_at": "2025-12-08 15:45:00",
      "changed_by": {
        "id": 5,
        "name": "Agus Kurir"
      }
    },
    {
      "id": 1,
      "old_status": "in_progress",
      "new_status": "arrived",
      "old_status_label": "Dalam Perjalanan",
      "new_status_label": "Sampai di Lokasi",
      "status_description": "Sampai di lokasi",
      "note": "Sampai di lokasi tujuan",
      "changed_at": "2025-12-08 15:30:00",
      "changed_by": {
        "id": 5,
        "name": "Agus Kurir"
      }
    }
  ]
}
```

---

## Status Labels & Descriptions

### Status Labels
| Status Code | Label | Deskripsi |
|-------------|-------|-----------|
| `pending` | Menunggu Pickup | Belum diambil driver |
| `picked` | Sudah Dipickup | Barang sudah diambil |
| `in_progress` | Dalam Perjalanan | Sedang dalam perjalanan |
| `arrived` | Sampai di Lokasi | Sudah sampai di tujuan |
| `delivered` | Sudah Diterima | Barang sudah diserahkan |
| `completed` | Selesai | Pengiriman selesai |
| `returning` | Perjalanan Pulang | Kembali ke kantor |
| `finished` | Sampai di Kantor | Sudah kembali ke kantor |
| `takeover` | Takeover | Driver melakukan takeover |
| `failed` | Gagal | Pengiriman gagal |

### Status Descriptions (Berdasarkan Transisi)

#### Normal Flow
| Transisi | Description |
|----------|-------------|
| `pending → picked` | "Barang sudah di pickup" |
| `picked → in_progress` | "Proses pengiriman" |
| `in_progress → arrived` | "Sampai di lokasi" |
| `arrived → delivered` | "Sudah diterima" |
| `delivered → completed` | "Barang completed semua" |
| `completed → returning` | "Arah pulang ke kantor" |
| `returning → finished` | "Sudah sampai di kantor finish" |

#### Takeover Flow
| Transisi | Description |
|----------|-------------|
| `picked → takeover` | "Driver melakukan takeover setelah pickup" |
| `in_progress → takeover` | "Driver melakukan takeover saat dalam perjalanan" |

#### Failed Flow
| Transisi | Description |
|----------|-------------|
| `in_progress → failed` | "Pengiriman gagal" |
| `arrived → failed` | "Gagal menyerahkan barang" |

#### Default
Jika tidak ada mapping khusus:
```
"Status berubah dari {old_status_label} ke {new_status_label}"
```

---

## Response Fields

### History Object
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | ID history record |
| `old_status` | string | Status sebelumnya (kode) |
| `new_status` | string | Status baru (kode) |
| `old_status_label` | string | Label status sebelumnya |
| `new_status_label` | string | Label status baru |
| `status_description` | string | Deskripsi perubahan status |
| `note` | string | Catatan tambahan |
| `changed_at` | string | Waktu perubahan (Y-m-d H:i:s) |
| `changed_by` | object | User yang melakukan perubahan |

### Changed By Object
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | User ID |
| `name` | string | Nama user |

---

## Use Cases

### 1. Timeline Pengiriman
Tampilkan riwayat status sebagai timeline di frontend:

```vue
<template>
  <div class="timeline">
    <div 
      v-for="history in statusHistory" 
      :key="history.id"
      class="timeline-item"
    >
      <div class="status-badge" :class="getStatusClass(history.new_status)">
        {{ history.new_status_label }}
      </div>
      
      <div class="status-info">
        <h4>{{ history.status_description }}</h4>
        <p v-if="history.note">{{ history.note }}</p>
        <small>
          {{ formatDate(history.changed_at) }} 
          oleh {{ history.changed_by?.name }}
        </small>
      </div>
    </div>
  </div>
</template>
```

### 2. Status Tracking untuk Customer
```javascript
// Ambil status history untuk ditampilkan ke customer
async function getTrackingHistory(shipmentId, destinationId) {
  const response = await axios.get(
    `/api/v1/shipments/${shipmentId}/destinations/${destinationId}/status-history`
  );
  
  return response.data.data.map(history => ({
    time: history.changed_at,
    status: history.new_status_label,
    description: history.status_description,
    note: history.note
  }));
}
```

### 3. Admin Monitoring
```javascript
// Untuk admin melihat detail perubahan status
function formatStatusChange(history) {
  return {
    timestamp: history.changed_at,
    change: `${history.old_status_label} → ${history.new_status_label}`,
    description: history.status_description,
    actor: history.changed_by?.name || 'System',
    note: history.note
  };
}
```

---

## Error Responses

### Shipment Not Found
```json
{
  "message": "No query results for model [App\\Models\\Shipment] 999"
}
```
Status: 404 Not Found

### Destination Not Found
```json
{
  "message": "No query results for model [App\\Models\\ShipmentDestination] 999"
}
```
Status: 404 Not Found

### Unauthorized
```json
{
  "message": "Unauthenticated."
}
```
Status: 401 Unauthorized

---

## Frontend Implementation Example

### Vue.js Component
```vue
<template>
  <div class="status-history">
    <h3>Riwayat Status Pengiriman</h3>
    
    <div v-if="loading" class="loading">
      Memuat riwayat...
    </div>
    
    <div v-else-if="histories.length === 0" class="empty">
      Belum ada riwayat status
    </div>
    
    <div v-else class="history-list">
      <div 
        v-for="history in histories" 
        :key="history.id"
        class="history-item"
      >
        <div class="history-time">
          {{ formatDateTime(history.changed_at) }}
        </div>
        
        <div class="history-content">
          <div class="status-change">
            <span class="old-status">{{ history.old_status_label }}</span>
            <span class="arrow">→</span>
            <span class="new-status">{{ history.new_status_label }}</span>
          </div>
          
          <div class="description">
            {{ history.status_description }}
          </div>
          
          <div v-if="history.note" class="note">
            📝 {{ history.note }}
          </div>
          
          <div class="actor">
            oleh {{ history.changed_by?.name || 'System' }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps(['shipmentId', 'destinationId']);

const histories = ref([]);
const loading = ref(true);

async function fetchStatusHistory() {
  try {
    const response = await axios.get(
      `/api/v1/shipments/${props.shipmentId}/destinations/${props.destinationId}/status-history`
    );
    histories.value = response.data.data;
  } catch (error) {
    console.error('Error fetching status history:', error);
  } finally {
    loading.value = false;
  }
}

function formatDateTime(dateString) {
  return new Date(dateString).toLocaleString('id-ID');
}

onMounted(() => {
  fetchStatusHistory();
});
</script>
```

---

## Testing

### Test Normal Flow
```bash
curl -X GET "http://localhost:8000/api/v1/shipments/1/destinations/1/status-history" \
  -H "Authorization: Bearer {token}"
```

### Expected Response Structure
```json
{
  "data": [
    {
      "old_status": "pending",
      "new_status": "picked",
      "old_status_label": "Menunggu Pickup",
      "new_status_label": "Sudah Dipickup",
      "status_description": "Barang sudah di pickup",
      "changed_at": "2025-12-08 10:00:00",
      "changed_by": {
        "name": "Agus Kurir"
      }
    }
  ]
}
```

---

## Summary

✅ **Fitur yang sudah ditambahkan:**
- Status labels dalam bahasa Indonesia
- Deskripsi perubahan status yang jelas
- Mapping untuk semua transisi status
- Response yang lebih informatif

✅ **Response sekarang include:**
- `old_status_label` & `new_status_label`
- `status_description` yang deskriptif
- Tetap backward compatible dengan field lama

✅ **Deskripsi status sesuai permintaan:**
- pending → picked: "Barang sudah di pickup"
- picked → in_progress: "Proses pengiriman"
- in_progress → arrived: "Sampai di lokasi"
- arrived → delivered: "Sudah diterima"
- delivered → completed: "Barang completed semua"
- completed → returning: "Arah pulang ke kantor"
- returning → finished: "Sudah sampai di kantor finish"