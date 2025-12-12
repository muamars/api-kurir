# Destination Status History - Documentation

## Overview
Modul ini mencatat setiap perubahan status pada destination secara otomatis. Setiap kali status destination berubah, status lama akan disimpan ke tabel `destination_status_histories` sebelum status baru di-update.

---

## Database Schema

### Table: `destination_status_histories`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| destination_id | bigint | Foreign key ke `shipment_destinations` |
| shipment_id | bigint | Foreign key ke `shipments` |
| old_status | varchar | Status sebelum perubahan |
| new_status | varchar | Status setelah perubahan |
| changed_by | bigint | User ID yang melakukan perubahan (nullable) |
| note | text | Catatan perubahan (nullable) |
| changed_at | timestamp | Waktu perubahan terjadi |
| created_at | timestamp | Timestamp record dibuat |
| updated_at | timestamp | Timestamp record di-update |

### Indexes
- `destination_id, changed_at` - Untuk query history per destination
- `shipment_id` - Untuk query history per shipment

---

## How It Works

### 1. Automatic Logging via Observer

Setiap kali `ShipmentDestination` model di-update dan field `status` berubah, **Observer** akan otomatis:

1. Deteksi perubahan status
2. Ambil status lama (`old_status`)
3. Ambil status baru (`new_status`)
4. Insert record baru ke `destination_status_histories`
5. Log perubahan ke Laravel log

**Kode Observer:**
```php
public function updating(ShipmentDestination $shipmentDestination): void
{
    if ($shipmentDestination->isDirty('status')) {
        $oldStatus = $shipmentDestination->getOriginal('status');
        $newStatus = $shipmentDestination->status;

        DestinationStatusHistory::create([
            'destination_id' => $shipmentDestination->id,
            'shipment_id' => $shipmentDestination->shipment_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => auth()->id(),
            'note' => "Status changed from {$oldStatus} to {$newStatus}",
            'changed_at' => now(),
        ]);
    }
}
```

### 2. Triggered Automatically

Observer akan triggered pada:
- ✅ Update status via `ShipmentProgressController::updateProgress()`
- ✅ Update status via `ShipmentController::startDelivery()`
- ✅ Bulk update status (contoh: auto-set returning)
- ✅ Manual update via Eloquent: `$destination->update(['status' => 'completed'])`

---

## API Endpoints

### Get Destination Status History

**Endpoint:**
```http
GET /api/v1/shipments/{shipment_id}/destinations/{destination_id}/status-history
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 5,
      "old_status": "in_progress",
      "new_status": "completed",
      "note": "Status changed from in_progress to completed",
      "changed_at": "2025-11-27 15:30:00",
      "changed_by": {
        "id": 10,
        "name": "Driver A"
      }
    },
    {
      "id": 4,
      "old_status": "picked",
      "new_status": "in_progress",
      "note": "Status changed from picked to in_progress",
      "changed_at": "2025-11-27 14:00:00",
      "changed_by": {
        "id": 1,
        "name": "Admin User"
      }
    },
    {
      "id": 3,
      "old_status": "pending",
      "new_status": "picked",
      "note": "Status changed from pending to picked",
      "changed_at": "2025-11-27 09:00:00",
      "changed_by": {
        "id": 10,
        "name": "Driver A"
      }
    }
  ]
}
```

**Use Case:**
- Tracking perubahan status per destination
- Audit trail untuk compliance
- Debugging masalah status
- Menampilkan timeline di frontend

---

## Example Flow

### Scenario: Driver Pickup → Deliver → Return → Finish

```
1. Initial Status: pending
   → No history yet

2. Driver Pickup:
   POST /shipments/1/destinations/1/progress
   Body: status=picked
   
   History Created:
   {
     old_status: "pending",
     new_status: "picked",
     changed_by: 10 (Driver A),
     changed_at: "2025-11-27 09:00:00"
   }

3. Driver Start Delivery:
   POST /shipments/1/start-delivery
   
   History Created:
   {
     old_status: "picked",
     new_status: "in_progress",
     changed_by: 10 (Driver A),
     changed_at: "2025-11-27 09:30:00"
   }

4. Driver Delivered:
   POST /shipments/1/destinations/1/progress
   Body: status=delivered
   
   History Created:
   {
     old_status: "in_progress",
     new_status: "completed",
     changed_by: 10 (Driver A),
     changed_at: "2025-11-27 15:00:00"
   }

5. System Auto-set Returning:
   (Triggered when all destinations completed)
   
   History Created:
   {
     old_status: "completed",
     new_status: "returning",
     changed_by: null (System),
     changed_at: "2025-11-27 15:00:01"
   }

6. Driver Finished:
   POST /shipments/1/destinations/1/progress
   Body: status=finished
   
   History Created:
   {
     old_status: "returning",
     new_status: "finished",
     changed_by: 10 (Driver A),
     changed_at: "2025-11-27 18:00:00"
   }
```

**Total History Records: 5**

---

## Query Examples

### Get All Status Changes for a Destination

```php
$destination = ShipmentDestination::find(1);
$histories = $destination->statusHistories()
    ->with('changedBy')
    ->orderBy('changed_at', 'desc')
    ->get();
```

### Get Status Changes by User

```php
$userChanges = DestinationStatusHistory::where('changed_by', 10)
    ->with(['destination', 'shipment'])
    ->orderBy('changed_at', 'desc')
    ->get();
```

### Get Status Changes for a Shipment

```php
$shipmentHistories = DestinationStatusHistory::where('shipment_id', 1)
    ->with(['destination', 'changedBy'])
    ->orderBy('changed_at', 'desc')
    ->get();
```

### Count Status Changes

```php
$totalChanges = DestinationStatusHistory::where('destination_id', 1)->count();
```

---

## Benefits

1. ✅ **Audit Trail** - Semua perubahan status tercatat
2. ✅ **Automatic** - Tidak perlu manual insert, observer handle semuanya
3. ✅ **Complete Data** - Tahu siapa, kapan, dan apa yang berubah
4. ✅ **Debugging** - Mudah trace masalah status
5. ✅ **Compliance** - Memenuhi requirement audit
6. ✅ **Timeline View** - Bisa tampilkan timeline di frontend
7. ✅ **Non-Intrusive** - Tidak mengubah existing code logic

---

## Frontend Integration Example

### Display Status Timeline

```javascript
// Fetch status history
const response = await axios.get(
  `/api/v1/shipments/${shipmentId}/destinations/${destinationId}/status-history`
);

const histories = response.data.data;

// Display as timeline
histories.forEach(history => {
  console.log(`
    ${history.changed_at}
    ${history.old_status} → ${history.new_status}
    Changed by: ${history.changed_by?.name || 'System'}
  `);
});
```

### Timeline Component (React Example)

```jsx
function StatusTimeline({ shipmentId, destinationId }) {
  const [histories, setHistories] = useState([]);

  useEffect(() => {
    fetchStatusHistory();
  }, []);

  const fetchStatusHistory = async () => {
    const res = await api.get(
      `/shipments/${shipmentId}/destinations/${destinationId}/status-history`
    );
    setHistories(res.data.data);
  };

  return (
    <div className="timeline">
      {histories.map(history => (
        <div key={history.id} className="timeline-item">
          <div className="time">{history.changed_at}</div>
          <div className="status-change">
            <span className="old-status">{history.old_status}</span>
            <span className="arrow">→</span>
            <span className="new-status">{history.new_status}</span>
          </div>
          <div className="changed-by">
            {history.changed_by?.name || 'System'}
          </div>
        </div>
      ))}
    </div>
  );
}
```

---

## Testing

### Manual Test

1. **Pickup barang:**
   ```bash
   POST /api/v1/shipments/1/destinations/1/progress
   Body: status=picked, photo=(file)
   ```

2. **Check history:**
   ```bash
   GET /api/v1/shipments/1/destinations/1/status-history
   ```
   
   Expected: 1 record (pending → picked)

3. **Start delivery:**
   ```bash
   POST /api/v1/shipments/1/start-delivery
   ```

4. **Check history again:**
   ```bash
   GET /api/v1/shipments/1/destinations/1/status-history
   ```
   
   Expected: 2 records (pending → picked, picked → in_progress)

---

## Troubleshooting

### History tidak tercatat

**Possible Causes:**
1. Observer tidak terdaftar di `AppServiceProvider`
2. Status tidak berubah (update dengan nilai yang sama)
3. Update menggunakan raw query (bypass Eloquent)

**Solution:**
- Pastikan observer registered: `ShipmentDestination::observe(ShipmentDestinationObserver::class)`
- Gunakan Eloquent untuk update: `$destination->update(['status' => 'new_status'])`
- Check Laravel log untuk error

### changed_by selalu null

**Cause:** User tidak authenticated saat update

**Solution:**
- Pastikan request memiliki Bearer token
- Check `auth()->id()` return value
- Untuk system update, `changed_by` memang null (expected)

---

## Database Maintenance

### Clean Old History (Optional)

Jika history terlalu banyak, bisa cleanup history lama:

```php
// Delete history older than 1 year
DestinationStatusHistory::where('changed_at', '<', now()->subYear())->delete();
```

### Archive History (Recommended)

Lebih baik archive daripada delete:

```php
// Move to archive table
DB::table('destination_status_histories_archive')
    ->insert(
        DestinationStatusHistory::where('changed_at', '<', now()->subYear())
            ->get()
            ->toArray()
    );
```

---

## Summary

✅ **Migration Created** - `destination_status_histories` table
✅ **Model Created** - `DestinationStatusHistory`
✅ **Observer Created** - Auto-log status changes
✅ **Observer Registered** - In `AppServiceProvider`
✅ **API Endpoint** - GET status history
✅ **Relationship Added** - `ShipmentDestination::statusHistories()`
✅ **Automatic Logging** - No manual code needed
✅ **Complete Audit Trail** - Who, when, what changed

Modul sudah siap digunakan! Setiap perubahan status akan otomatis tercatat. 🚀

---

Generated: 2025-12-01
Version: 1.0
