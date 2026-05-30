# Code Review — ApiTrackKurir (api-kurir)

> **Tanggal:** 2026-05-30
> **Sistem:** `api-kurir` — REST API pelacakan pengiriman (Laravel 12, Sanctum, spatie/laravel-permission). Frontend `fe_kurir` (Next.js/TypeScript) mengonsumsi API ini.
> **Metode:** review statis kode (tanpa menjalankan aplikasi).
> **Pemetaan istilah** (untuk menjawab pertanyaan "tiket/kurir/takeover"):
> - **Tiket** = `Shipment` (pengiriman)
> - **Kurir** = `User` dengan role `Kurir`, dirujuk lewat kolom `assigned_driver_id`
> - **Assign** = `assignDriver` (ganti driver saat sudah assigned) / `bulkAssignDriver` (pending → assigned) / `approve` (pending → assigned + approval)
> - **Takeover** = admin menarik shipment yang sudah di-assign kembali ke `pending`

---

## 1. Lifecycle status Shipment

Enum status (dari [api-kurir/database/migrations/2026_05_27_000012_create_shipments_table.php:22](api-kurir/database/migrations/2026_05_27_000012_create_shipments_table.php#L22)):

```
pending → assigned → in_progress → completed
   ↑          │
   └──────────┘  (takeover: assigned → pending)
        cancelled (dari berbagai state)
```

Aksi utama (semua di [api-kurir/app/Http/Controllers/Api/ShipmentController.php](api-kurir/app/Http/Controllers/Api/ShipmentController.php)):

| Aksi | Method | Syarat status | Hasil |
|------|--------|---------------|-------|
| Approve + assign | `approve` (L225) | `pending` | `assigned` |
| Bulk assign | `bulkAssignDriver` (L284) | `pending` | `assigned` |
| Reassign | `assignDriver` (L256) | `assigned` | `assigned` (ganti driver) |
| Takeover | `takeover` (L649) | `assigned` | `pending`, driver dilepas |

---

## 2. Ringkasan temuan

| ID | Tingkat | Temuan |
|----|---------|--------|
| C1 | TINGGI | Takeover & reassign **tidak dibatasi** (loop tak terhingga) — lihat Bagian 4 |
| C2 | TINGGI | `takeover()` & `assignDriver()` **tanpa transaction + `lockForUpdate`** (race/TOCTOU), padahal `bulkAssignDriver()` sudah pakai |
| C3 | TINGGI | `update()` shipment tanpa otorisasi & bisa mengubah `status` langsung (bypass state machine) |
| C4 | SEDANG | `driver_id` divalidasi hanya `exists:users` — tidak dicek harus role `Kurir` & `is_active` |
| C5 | SEDANG | `takeover()` tidak menulis jejak audit (tidak ada catatan siapa admin yang takeover) |
| C6 | SEDANG | `takeover()` mereset `approved_by`/`approved_at` → jejak approval hilang tiap siklus |
| C7 | RENDAH | Notifikasi & baris `bulk_assignments` membengkak bila assign/takeover diulang |
| C8 | RENDAH | Inkonsistensi: `assignDriver` tidak set `approved_by`, `approve`/`bulkAssignDriver` set |
| C9 | RENDAH | Sisa komentar/placeholder di model (`// tambahan baru` / `// batas`) di [api-kurir/app/Models/Shipment.php:75](api-kurir/app/Models/Shipment.php#L75) |
| C10 | RENDAH | Hotspot kualitas (N+1 query & fat method) terdokumentasi di [api-kurir/CLAUDE.md](api-kurir/CLAUDE.md) — `ShipmentController`, `ShipmentProgressController`, `DashboardController` |

> Temuan keamanan terpisah di **[SECURITY_REVIEW.md](SECURITY_REVIEW.md)**.

---

## 3. Detail temuan

### C2 — Race condition pada takeover & reassign
**Lokasi:** [api-kurir/app/Http/Controllers/Api/ShipmentController.php:649](api-kurir/app/Http/Controllers/Api/ShipmentController.php#L649) (`takeover`), [:256](api-kurir/app/Http/Controllers/Api/ShipmentController.php#L256) (`assignDriver`)

Kedua method membaca `$shipment->status` lalu meng-`update()` **tanpa kunci baris**:

```php
// takeover()
if ($shipment->status !== 'assigned') { return ... 400; }   // (cek)
$shipment->update(['status' => 'pending', 'assigned_driver_id' => null, ...]); // (tulis)
```

Antara "cek" dan "tulis" tidak ada penguncian. Skenario berbahaya (TOCTOU / lost update):
- Kurir memanggil `startDelivery` (`assigned → in_progress`) **bersamaan** dengan admin `takeover`. Takeover sudah lolos cek `status==='assigned'`, lalu menimpa state menjadi `pending` → progres `in_progress` hilang.
- Dua admin menekan takeover bersamaan → dobel proses & notifikasi ganda.

Bandingkan dengan `bulkAssignDriver()` ([:307](api-kurir/app/Http/Controllers/Api/ShipmentController.php#L307)) yang **sudah benar**: `DB::beginTransaction()` + `->lockForUpdate()`.

**Rekomendasi:** samakan pola — bungkus `takeover()` dan `assignDriver()` dengan transaction + `lockForUpdate` (lihat kode di Bagian 4).

---

### C3 — `update()` tanpa otorisasi & bisa ubah status
**Lokasi:** [api-kurir/app/Http/Controllers/Api/ShipmentController.php:215](api-kurir/app/Http/Controllers/Api/ShipmentController.php#L215)

```php
public function update(UpdateShipmentRequest $request, Shipment $shipment): JsonResponse
{
    $shipment->update($request->only(['category_id','vehicle_type_id','notes','priority','deadline','status']));
    ...
}
```

Tidak ada `$this->authorize(...)` dan tidak ada cek kepemilikan. Field `status` ikut bisa di-set langsung → siapa pun yang lolos `auth:sanctum` (termasuk Kurir) dapat memaksa status sembarang dan **melewati seluruh state machine** (mis. langsung `completed`). Lihat juga sisi keamanan di SECURITY_REVIEW (S2/S3).

**Rekomendasi:** tambahkan gate (`$this->authorize('edit-shipments')`), dan **keluarkan `status`** dari `update()` umum — perubahan status harus lewat aksi khusus (`approve`/`assign`/`takeover`/`startDelivery`/`cancel`) yang punya guard masing-masing.

---

### C4 — `driver_id` tidak dipastikan seorang Kurir aktif
**Lokasi:** `assignDriver` ([:260](api-kurir/app/Http/Controllers/Api/ShipmentController.php#L260)), `bulkAssignDriver` ([:291](api-kurir/app/Http/Controllers/Api/ShipmentController.php#L291)), `approve` ([:236](api-kurir/app/Http/Controllers/Api/ShipmentController.php#L236))

Semua hanya `'driver_id' => 'required|exists:users,id'`. Akibatnya shipment bisa di-assign ke user yang **bukan** kurir (mis. admin) atau kurir yang `is_active = false`.

**Rekomendasi:** validasi peran + status aktif:
```php
'driver_id' => [
    'required',
    Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_active', true)),
    function ($attr, $value, $fail) {
        if (! \App\Models\User::find($value)?->hasRole('Kurir')) {
            $fail('Driver yang dipilih bukan kurir.');
        }
    },
],
```

---

### C5 & C6 — Audit takeover hilang
**Lokasi:** [api-kurir/app/Http/Controllers/Api/ShipmentController.php:649](api-kurir/app/Http/Controllers/Api/ShipmentController.php#L649)

`takeover()` hanya mengirim notifikasi ([api-kurir/app/Services/NotificationService.php:297](api-kurir/app/Services/NotificationService.php#L297) `shipmentAdminTakeover`) tetapi **tidak menyimpan** catatan permanen: siapa admin yang takeover, kapan, alasan apa. Sekaligus me-`null`-kan `approved_by`/`approved_at`, sehingga jejak approval sebelumnya hilang setiap siklus.

**Rekomendasi:** catat ke tabel history (mis. `shipment_histories`) setiap takeover: `actor_id`, `from_driver_id`, `reason`, `at`. Pertahankan kolom `takeover_count` (Bagian 4) sebagai ringkasan cepat.

---

### C7 — Pembengkakan data & spam notifikasi
Setiap siklus assign/takeover membuat beberapa baris `notifications` (lihat `shipmentPending`, `shipmentAdminTakeover`, `shipmentAssigned` di [NotificationService.php](api-kurir/app/Services/NotificationService.php)) dan, bila lewat bulk, satu baris `bulk_assignments`. Diulang banyak → tabel membengkak & kurir terus dibanjiri notifikasi. Pembatasan di Bagian 4 menutup ini.

---

## 4. Analisis Alur Berbahaya: Takeover & Reassign Berulang

> Jawaban langsung untuk: *"tiket sudah di-assign ke kurir, di-takeover admin, lalu di-assign lagi ke kurir yang sama — berbahaya bila berulang? bagaimana memperbaiki & berapa kali takeover yang boleh?"*

### 4.1 Alur saat ini
```
pending --approve/bulkAssign(Kurir A)--> assigned --takeover--> pending
   ^                                                               |
   └──────────── approve/bulkAssign(Kurir A lagi) ◄───────────────┘   (tak terbatas)
```

### 4.2 Apakah berbahaya? Ya — terutama karena tidak dibatasi
| # | Risiko | Bukti di kode |
|---|--------|---------------|
| 1 | **Tak ada batas takeover** → loop tak terhingga | `takeover()` tidak punya penghitung/limit; tabel `shipments` tidak punya kolom takeover |
| 2 | **Race condition** (lost update) | `takeover()`/`assignDriver()` tanpa `lockForUpdate` (C2) |
| 3 | **Bloat data & spam** | notifikasi + `bulk_assignments` bertambah tiap siklus (C7) |
| 4 | **Audit lemah** | takeover tidak tercatat permanen (C5) |
| 5 | **State bisa dipaksa** | `update()` bisa set `status` langsung (C3) |

Satu–dua kali takeover itu wajar dan aman (mis. kurir tidak responsif). Yang berbahaya adalah **pengulangan tanpa batas, tanpa kunci baris, tanpa audit**.

### 4.3 Perbaikan — langkah konkret

**Langkah 0 — tambah kolom pembatas (migration baru)**
```php
// database/migrations/xxxx_add_takeover_fields_to_shipments.php
Schema::table('shipments', function (Blueprint $table) {
    $table->unsignedSmallInteger('takeover_count')->default(0)->after('status');
    $table->boolean('needs_review')->default(false)->after('takeover_count');
    $table->timestamp('last_takeover_at')->nullable()->after('needs_review');
});
```
Tambahkan ke `$fillable` di [api-kurir/app/Models/Shipment.php:24](api-kurir/app/Models/Shipment.php#L24): `'takeover_count', 'needs_review', 'last_takeover_at'` (dan cast `needs_review` => `boolean`, `last_takeover_at` => `datetime`).

**Langkah 1 — ambang batas yang bisa dikonfigurasi**
```php
// config/shipment.php
return [
    'max_takeover' => (int) env('SHIPMENT_MAX_TAKEOVER', 3),
];
```

**Langkah 2 — `takeover()` dengan batas + kunci baris + audit**
```php
public function takeover(Request $request, Shipment $shipment): JsonResponse
{
    $this->authorize('assign-drivers');
    $data = $request->validate(['reason' => 'required|string|max:500']); // wajibkan alasan

    $max = config('shipment.max_takeover');

    $result = DB::transaction(function () use ($shipment, $data, $max) {
        $s = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

        if ($s->status !== 'assigned') {
            return ['error' => 'Hanya pengiriman berstatus assigned yang bisa di-takeover.', 'code' => 400];
        }
        if ($s->takeover_count >= $max) {
            $s->update(['needs_review' => true]); // eskalasi, jangan didaur ulang lagi
            return ['error' => "Sudah mencapai batas {$max}x takeover. Pengiriman ditandai perlu peninjauan.", 'code' => 422];
        }

        $previousDriverId = $s->assigned_driver_id;

        $s->update([
            'status'           => 'pending',
            'assigned_driver_id' => null,
            'approved_by'      => null,
            'approved_at'      => null,
            'takeover_count'   => $s->takeover_count + 1,
            'last_takeover_at' => now(),
        ]);

        // audit permanen
        ShipmentHistory::create([
            'shipment_id'        => $s->id,
            'assigned_driver_id' => $previousDriverId,
            'action'             => 'takeover',
            'actor_id'           => auth()->id(),
            'reason'             => $data['reason'],
        ]);

        return ['ok' => true, 'previous' => $previousDriverId, 'shipment' => $s];
    });

    if (isset($result['error'])) {
        return response()->json(['message' => $result['error']], $result['code']);
    }

    $fresh = $result['shipment']->fresh(['creator']);
    app(NotificationService::class)->shipmentPending($fresh);
    if ($result['previous']) {
        app(NotificationService::class)->shipmentAdminTakeover($fresh, $result['previous']);
    }

    return response()->json(['message' => 'Pengiriman di-takeover dan dikembalikan ke pending', 'data' => $fresh->load('driver')]);
}
```

**Langkah 3 — `assignDriver()` juga dikunci + validasi kurir** (lihat C2, C4):
```php
public function assignDriver(Request $request, Shipment $shipment): JsonResponse
{
    $this->authorize('assign-drivers');
    $request->validate([/* aturan driver_id dari C4 */]);

    return DB::transaction(function () use ($request, $shipment) {
        $s = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
        if ($s->status !== 'assigned') {
            return response()->json(['message' => 'Hanya pengiriman assigned yang bisa di-reassign'], 400);
        }
        $s->update(['assigned_driver_id' => $request->driver_id, 'status' => 'assigned']);
        app(NotificationService::class)->shipmentAssigned($s->fresh(['driver', 'creator']));
        return response()->json(['message' => 'Driver assigned successfully', 'data' => $s->fresh(['driver'])]);
    });
}
```

### 4.4 Aturan yang direkomendasikan — "berapa kali takeover?"

**Default: maksimum 3 takeover per pengiriman**, dapat diatur lewat `SHIPMENT_MAX_TAKEOVER` di `.env`.

| `takeover_count` | Aksi takeover | Hasil |
|---|---|---|
| 0 → 1 | Diizinkan | Takeover normal (kurir tidak responsif) |
| 1 → 2 | Diizinkan | Koreksi kedua |
| 2 → 3 | Diizinkan | Koreksi ketiga (terakhir) |
| ≥ 3 (batas) | **Diblokir** | `needs_review = true`, eskalasi ke supervisor; tidak dilempar ke antrean otomatis |

**Alasan 3:** memberi ruang koreksi yang wajar, tetapi takeover berkali-kali menandakan masalah sistemik (alamat salah, paket bermasalah, kurang kurir di area) yang lebih baik ditinjau manusia, bukan terus didaur ulang otomatis. Karena disimpan di konfigurasi, tim ops bisa menyetel (mis. 2–5) tanpa ubah kode. Tampilkan badge "Perlu Peninjauan" untuk shipment `needs_review` di panel admin ([fe_kurir](fe_kurir/)).

---

## 5. Checklist prioritas

**Tinggi**
- [ ] Batasi takeover (kolom + config + guard) — Bagian 4 (C1)
- [ ] Tambah transaction + `lockForUpdate` pada `takeover()` & `assignDriver()` (C2)
- [ ] Beri otorisasi pada `update()` & keluarkan `status` dari mass-update (C3)

**Sedang**
- [ ] Validasi `driver_id` harus role `Kurir` & aktif (C4)
- [ ] Catat audit takeover ke history (C5/C6)

**Rendah**
- [ ] Konsistenkan `approved_by` antar method assign (C8)
- [ ] Bersihkan placeholder model (C9)
- [ ] Tangani N+1 / fat method sesuai [CLAUDE.md](api-kurir/CLAUDE.md) (C10)
