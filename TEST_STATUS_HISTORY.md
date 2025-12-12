# Test Status History - Troubleshooting Guide

## ✅ CHECKLIST YANG SUDAH DIKONFIRMASI

1. ✅ **Observer exists** - `ShipmentDestinationObserver.php`
2. ✅ **Observer registered** - Di `AppServiceProvider.php`
3. ✅ **Permission exists** - `update-progress` untuk role Kurir
4. ✅ **Route exists** - POST `/shipments/{id}/destinations/{dest_id}/progress`
5. ✅ **History table exists** - `destination_status_histories`

---

## 🧪 MANUAL TEST

### Test 1: Cek Apakah Observer Berfungsi

```bash
# Via Tinker
php artisan tinker

# Test update status
$dest = \App\Models\ShipmentDestination::find(1);
$dest->status = 'picked';
$dest->save();

# Cek history
\App\Models\DestinationStatusHistory::where('destination_id', 1)->get();
```

**Expected:** Harus ada record baru di history.

---

### Test 2: Cek Permission Kurir

```bash
php artisan tinker

# Get kurir user
$kurir = \App\Models\User::where('email', 'kurir@example.com')->first();

# Cek permission
$kurir->can('update-progress'); // Should return true
$kurir->hasRole('Kurir'); // Should return true
```

---

### Test 3: Test Endpoint via Postman/Curl

```bash
# 1. Login sebagai Kurir
POST http://127.0.0.1:8000/api/v1/auth/login
Body: {
  "email": "kurir@example.com",
  "password": "password"
}

# Copy token dari response

# 2. Update Progress
POST http://127.0.0.1:8000/api/v1/shipments/1/destinations/1/progress
Headers:
  Authorization: Bearer {token}
  Content-Type: multipart/form-data
Body:
  status: picked
  photo: (file)
  note: Test pickup

# 3. Check History
GET http://127.0.0.1:8000/api/v1/shipments/1/destinations/1/status-history
Headers:
  Authorization: Bearer {token}
```

---

## 🔍 DEBUGGING STEPS

### Step 1: Check Laravel Log

```bash
# Monitor log real-time
php artisan pail

# Or check log file
tail -f storage/logs/laravel.log
```

**Look for:**
- `Destination status changed` - Dari observer
- `Update Progress Request` - Dari controller
- Any error messages

---

### Step 2: Check Database

```sql
-- Check if history table exists
SELECT * FROM destination_status_histories LIMIT 5;

-- Check destination current status
SELECT id, shipment_id, status FROM shipment_destinations WHERE id = 1;

-- Check history for specific destination
SELECT * FROM destination_status_histories 
WHERE destination_id = 1 
ORDER BY changed_at DESC;
```

---

### Step 3: Check Observer Registration

```bash
php artisan tinker

# Check if observer is registered
\App\Models\ShipmentDestination::getObservableEvents();
```

**Expected:** Should include 'updating', 'updated', etc.

---

## 🐛 COMMON ISSUES & SOLUTIONS

### Issue 1: History Tidak Tercatat

**Possible Causes:**
1. Observer tidak terdaftar
2. Update menggunakan raw query (bypass Eloquent)
3. Status tidak berubah (update dengan nilai yang sama)

**Solution:**
```bash
# Clear cache
php artisan optimize:clear

# Re-register observer
php artisan config:clear
php artisan cache:clear
```

---

### Issue 2: Permission Denied

**Possible Causes:**
1. User tidak punya permission `update-progress`
2. User tidak punya role `Kurir`
3. Token expired

**Solution:**
```bash
# Re-seed permissions
php artisan db:seed --class=RolePermissionSeeder

# Check user role
php artisan tinker
$user = \App\Models\User::find(2);
$user->roles; // Should show 'Kurir'
$user->permissions; // Should include 'update-progress'
```

---

### Issue 3: Observer Tidak Trigger

**Possible Causes:**
1. Update menggunakan `DB::table()` instead of Eloquent
2. Update menggunakan `update()` query builder
3. Observer event tidak registered

**Solution:**

**WRONG (Tidak trigger observer):**
```php
DB::table('shipment_destinations')->where('id', 1)->update(['status' => 'picked']);
ShipmentDestination::where('id', 1)->update(['status' => 'picked']);
```

**CORRECT (Trigger observer):**
```php
$dest = ShipmentDestination::find(1);
$dest->status = 'picked';
$dest->save();

// Or
$dest = ShipmentDestination::find(1);
$dest->update(['status' => 'picked']);
```

---

## 📊 VERIFICATION CHECKLIST

Setelah update status, cek:

- [ ] Response API success (200)
- [ ] Destination status berubah di database
- [ ] History record baru di `destination_status_histories`
- [ ] Log muncul di Laravel log
- [ ] Frontend dapat response yang benar

---

## 🔧 QUICK FIX COMMANDS

```bash
# 1. Clear all cache
php artisan optimize:clear

# 2. Re-run migrations (if needed)
php artisan migrate:fresh --seed

# 3. Check routes
php artisan route:list --path=progress

# 4. Check permissions
php artisan permission:cache-reset

# 5. Test observer manually
php artisan tinker
$dest = \App\Models\ShipmentDestination::find(1);
$dest->status = 'test';
$dest->save();
\App\Models\DestinationStatusHistory::latest()->first();
```

---

## 📱 FRONTEND DEBUGGING

### Check Request

```javascript
// Log request before sending
console.log('Sending request:', {
  url: `/shipments/${shipmentId}/destinations/${destId}/progress`,
  status: 'picked',
  hasPhoto: !!photo
});

// Check response
const response = await api.post(url, formData);
console.log('Response:', response.data);

// Check history after update
const history = await api.get(`/shipments/${shipmentId}/destinations/${destId}/status-history`);
console.log('History:', history.data);
```

### Check Network Tab

1. Open DevTools → Network
2. Filter: XHR/Fetch
3. Look for `/progress` request
4. Check:
   - Request payload
   - Response status (should be 200)
   - Response body
   - Any error messages

---

## 🎯 EXPECTED BEHAVIOR

### When Driver Updates Status:

1. **Request sent** to `/shipments/1/destinations/1/progress`
2. **Controller receives** request
3. **Validation passes**
4. **Progress record created** in `shipment_progress`
5. **Destination status updated** via Eloquent
6. **Observer triggered** (updating event)
7. **History record created** in `destination_status_histories`
8. **Response returned** with success message
9. **Frontend receives** response
10. **UI updates** to show new status

### Logs You Should See:

```
[2025-12-03 15:00:00] local.INFO: Update Progress Request
{
  "shipment_id": 1,
  "destination_id": 1,
  "status": "picked",
  "has_photo": true,
  "user_id": 2
}

[2025-12-03 15:00:00] local.INFO: Destination status changed
{
  "destination_id": 1,
  "old_status": "pending",
  "new_status": "picked",
  "changed_by": 2
}
```

---

## 🚨 IF STILL NOT WORKING

### Share These Info:

1. **Laravel Log** (last 50 lines)
   ```bash
   tail -n 50 storage/logs/laravel.log
   ```

2. **Request/Response** from Network tab
   - Request URL
   - Request payload
   - Response status
   - Response body

3. **Database Check**
   ```sql
   SELECT * FROM destination_status_histories 
   WHERE destination_id = {your_dest_id} 
   ORDER BY id DESC LIMIT 5;
   ```

4. **User Info**
   ```bash
   php artisan tinker
   $user = \App\Models\User::find({your_user_id});
   echo $user->roles->pluck('name');
   echo $user->getAllPermissions()->pluck('name');
   ```

---

Generated: 2025-12-03
Version: 1.0
