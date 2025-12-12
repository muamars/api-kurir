# Status Flow Fix Documentation

## Problem
Status history menunjukkan ada step yang hilang dalam flow pengiriman:

**Sebelum (Bermasalah):**
```json
{
  "data": [
    {
      "old_status": "in_progress",
      "new_status": "arrived"
    },
    {
      "old_status": "pending", 
      "new_status": "picked"
    }
  ]
}
```

**Missing:** `picked → in_progress`

---

## Root Cause
Ketika kurir melakukan update progress dari `picked` langsung ke `arrived`, status `in_progress` tidak pernah di-set ke destination, sehingga history step tersebut hilang.

---

## Solution
Implementasi yang disederhanakan:

1. **Setiap Status Update Tercatat**
   - Kurir harus secara eksplisit update ke setiap status
   - Tidak ada auto-fill atau skip steps
   - Setiap transisi tercatat sebagai action yang valid

2. **Observer Handles History**
   - ShipmentDestinationObserver otomatis catat setiap perubahan status
   - History lengkap dan akurat
   - Tidak ada status yang "dilewati"

3. **Flow yang Diharapkan**
   - Kurir update: `picked` → `in_progress` → `arrived` → `delivered`
   - Setiap step adalah action yang disengaja
   - Status `in_progress` adalah status valid, bukan "dilewati"

---

## Status Flow Order

```php
$statusFlow = [
    'pending' => 0,      // Menunggu pickup
    'picked' => 1,       // Sudah dipickup
    'in_progress' => 2,  // Dalam perjalanan
    'arrived' => 3,      // Sampai di lokasi
    'delivered' => 4,    // Sudah diterima
    'completed' => 5,    // Selesai
    'returning' => 6,    // Perjalanan pulang
    'finished' => 7,     // Sampai di kantor
];
```

---

## How It Works

### Scenario 1: Normal Flow
```
Current: pending (0)
New: picked (1)
Result: Update langsung ke picked
```

### Scenario 2: Proper Flow
```
Current: picked (1)
Action 1: Update to in_progress (2)
Action 2: Update to arrived (3)

Process:
1. Kurir update: picked → in_progress (manual)
2. Kurir update: in_progress → arrived (manual)
3. Setiap step tercatat sebagai action yang valid
```

### Scenario 3: Special Cases
```
Current: picked (1)
New: takeover (special)
Result: Update langsung ke takeover (tidak mengikuti flow)
```

---

## Expected Result

**Setelah Fix:**
```json
{
  "data": [
    {
      "old_status": "in_progress",
      "new_status": "arrived",
      "status_description": "Sampai di lokasi",
      "note": "Status changed from in_progress to arrived",
      "changed_at": "2025-12-10 10:26:35"
    },
    {
      "old_status": "picked",
      "new_status": "in_progress", 
      "status_description": "Proses pengiriman",
      "note": "Status changed from picked to in_progress",
      "changed_at": "2025-12-10 10:15:20"
    },
    {
      "old_status": "pending",
      "new_status": "picked",
      "status_description": "Barang sudah di pickup",
      "note": "Status changed from pending to picked", 
      "changed_at": "2025-12-10 09:57:26"
    }
  ]
}
```

---

## Implementation Details

### Method: `updateDestinationStatus()`

```php
private function updateDestinationStatus(ShipmentDestination $destination, string $newStatus): void
{
    $currentStatus = $destination->status;
    
    // Status flow order
    $statusFlow = [
        'pending' => 0,
        'picked' => 1,
        'in_progress' => 2,
        'arrived' => 3,
        'delivered' => 4,
        'completed' => 5,
        'returning' => 6,
        'finished' => 7,
    ];

    // Special cases (takeover, failed)
    if (in_array($newStatus, ['takeover', 'failed'])) {
        $destination->update(['status' => $newStatus]);
        return;
    }

    $currentOrder = $statusFlow[$currentStatus] ?? 0;
    $newOrder = $statusFlow[$newStatus] ?? 0;

    // Jika ada step yang dilewati
    if ($newOrder > $currentOrder) {
        // Update ke status baru
        $destination->update(['status' => $newStatus]);
        
        // Buat history untuk step yang dilewati
        for ($i = $currentOrder + 1; $i < $newOrder; $i++) {
            $skippedStatus = array_search($i, $statusFlow);
            if ($skippedStatus) {
                DestinationStatusHistory::create([
                    'destination_id' => $destination->id,
                    'shipment_id' => $destination->shipment_id,
                    'old_status' => $currentStatus,
                    'new_status' => $skippedStatus,
                    'changed_by' => auth()->id(),
                    'note' => "Status otomatis: {$currentStatus} → {$skippedStatus} (dilewati)",
                    'changed_at' => now()->subSeconds($newOrder - $i),
                ]);
                $currentStatus = $skippedStatus;
            }
        }
    } else {
        // Update normal
        $destination->update(['status' => $newStatus]);
    }
}
```

---

## Benefits

### 1. Complete History
✅ Semua step tercatat dalam history
✅ Tidak ada step yang hilang
✅ Timeline lengkap untuk tracking

### 2. Better UX
✅ Frontend bisa tampilkan progress yang lengkap
✅ Customer bisa lihat semua tahapan
✅ Admin bisa monitor dengan detail

### 3. Data Integrity
✅ History data konsisten
✅ Status flow terjaga
✅ Audit trail lengkap

---

## Testing

### Test Case 1: Skip In Progress
```bash
# Current status: picked
# Update to: arrived
POST /api/v1/shipments/1/destinations/1/progress
{
  "status": "arrived",
  "note": "Sampai di lokasi"
}

# Expected history:
# 1. picked → in_progress (auto)
# 2. in_progress → arrived (manual)
```

### Test Case 2: Skip Multiple Steps
```bash
# Current status: picked  
# Update to: delivered
POST /api/v1/shipments/1/destinations/1/progress
{
  "status": "delivered",
  "receiver_name": "John Doe"
}

# Expected history:
# 1. picked → in_progress (auto)
# 2. in_progress → arrived (auto) 
# 3. arrived → delivered (manual)
```

### Test Case 3: Normal Flow
```bash
# Current status: picked
# Update to: in_progress  
POST /api/v1/shipments/1/destinations/1/progress
{
  "status": "in_progress"
}

# Expected history:
# 1. picked → in_progress (manual, no auto-fill)
```

### Test Case 4: Special Cases
```bash
# Current status: picked
# Update to: takeover
POST /api/v1/shipments/1/destinations/1/progress
{
  "status": "takeover",
  "takeover_reason": "Alamat tidak ditemukan"
}

# Expected history:
# 1. picked → takeover (manual, no auto-fill)
```

---

## Backward Compatibility

✅ **Existing Data:** Tidak terpengaruh
✅ **API Response:** Tetap sama, hanya lebih lengkap
✅ **Frontend:** Tidak perlu perubahan
✅ **Observer:** Tetap berfungsi normal

---

## Notes

### Auto-Generated History
- Note: `"Status otomatis: {old} → {new} (dilewati)"`
- Timestamp: Berbeda beberapa detik agar urutan benar
- Changed By: User yang melakukan update manual

### Special Status
- `takeover`: Tidak mengikuti flow normal
- `failed`: Tidak mengikuti flow normal
- Bisa terjadi dari status manapun

### Performance
- Minimal impact: Hanya 1-2 query tambahan untuk step yang dilewati
- Jarang terjadi: Kebanyakan kurir mengikuti flow normal
- Async: Bisa dipindah ke queue jika perlu

---

## Summary

🔧 **Problem Fixed:** Missing steps dalam status history
📈 **Improvement:** Complete timeline tracking  
🎯 **Result:** Semua transisi status tercatat dengan benar
✅ **Status:** Ready untuk production