# Security Review — ApiTrackKurir (api-kurir)

> **Tanggal:** 2026-05-30
> **Sistem:** `api-kurir` — REST API Laravel 12 (Sanctum + spatie/laravel-permission), frontend `fe_kurir` (Next.js).
> **Sifat:** review statis (defensive). Beberapa item (mis. isi `.env`) perlu **audit manual** karena tidak dapat diperiksa otomatis dengan andal.
> **Catatan positif:** RBAC sudah ada lewat `spatie/laravel-permission` — banyak route memakai `middleware('role:Admin|Super Admin')` / `permission:...` dan controller memakai `$this->authorize(...)`. Ini fondasi yang baik; temuan di bawah adalah celah pada penerapannya.

---

## Ringkasan risiko

| ID | Tingkat | Temuan | Kategori (OWASP) |
|----|---------|--------|------------------|
| S1 | TINGGI | Otorisasi `uploadAdminPhotos` di-comment → siapa pun bisa unggah foto ke shipment mana pun | Broken Access Control |
| S2 | TINGGI | `update()` shipment tanpa otorisasi & bisa ubah `status` apa saja | Broken Access Control |
| S3 | SEDANG | `show()` shipment tanpa cek kepemilikan/role → IDOR (kurir bisa lihat shipment milik orang lain) | IDOR |
| S4 | SEDANG | Tidak ada rate limit/throttle pada endpoint login → brute force | Auth / Identification Failures |
| S5 | SEDANG | Token API user **non-Kurir tidak ada expiry** (hanya Kurir 24 jam) | Auth |
| S6 | SEDANG | `driver_id` tidak dibatasi role `Kurir`/aktif → tugas bisa diberikan ke user salah | Broken Access Control |
| S7 | RENDAH | `generateTestToken` mengeluarkan token ability `['*']` tanpa password; aman hanya jika `APP_ENV` benar | Misconfiguration (kondisional) |
| S8 | RENDAH | Audit aksi sensitif (takeover) tidak tersimpan permanen | Logging & Monitoring |
| S9 | RENDAH | Perlu audit manual `.env` (`APP_DEBUG`, `APP_ENV`, `APP_KEY`, kredensial DB) | Misconfiguration |

---

## Detail

### S1 — Otorisasi unggah foto admin dinonaktifkan (TINGGI)
**Lokasi:** [api-kurir/app/Http/Controllers/Api/ShipmentPhotoController.php:37](api-kurir/app/Http/Controllers/Api/ShipmentPhotoController.php#L37)

```php
public function uploadAdminPhotos(Request $request, Shipment $shipment): JsonResponse
{
    // $this->authorize('approve-shipments');   <-- DI-COMMENT
    $request->validate([
        'photos.*' => 'required|file|image|mimes:jpeg,png,jpg|max:5120',
        ...
    ]);
```

Gate-nya dimatikan, dan tidak ada cek kepemilikan. Akibatnya **setiap user terotentikasi** (termasuk Kurir) bisa mengunggah hingga 5 foto ke **shipment milik siapa pun** (`{shipment}` dari URL). Validasi tipe/ukuran file sudah baik (image, mimes, max 5MB), tetapi otorisasinya hilang.

**Rekomendasi:** aktifkan kembali `$this->authorize('approve-shipments');` (atau gate yang sesuai). Bandingkan dengan `uploadPickupPhoto`/`uploadDeliveryPhoto` ([:74](api-kurir/app/Http/Controllers/Api/ShipmentPhotoController.php#L74), [:126](api-kurir/app/Http/Controllers/Api/ShipmentPhotoController.php#L126)) yang **sudah benar** memeriksa `$shipment->assigned_driver_id !== auth()->id()`.

---

### S2 — `update()` shipment tanpa otorisasi (TINGGI)
**Lokasi:** [api-kurir/app/Http/Controllers/Api/ShipmentController.php:215](api-kurir/app/Http/Controllers/Api/ShipmentController.php#L215)

```php
public function update(UpdateShipmentRequest $request, Shipment $shipment): JsonResponse
{
    $shipment->update($request->only(['category_id','vehicle_type_id','notes','priority','deadline','status']));
    ...
}
```

Tidak ada `authorize()` dan tidak ada cek kepemilikan. `status` termasuk field yang bisa di-set, sehingga user non-admin (mis. Kurir) bisa **mengubah status pengiriman apa pun** (mis. memaksa `completed`/`cancelled`) lewat `PUT /v1/shipments/{shipment}`. Ini juga merusak integritas alur (lihat CODE_REVIEW C3).

**Rekomendasi:**
- Tambah `$this->authorize('edit-shipments')` (atau policy berbasis kepemilikan/role).
- Keluarkan `status` dari update umum; ubah status hanya lewat aksi khusus yang sudah ber-guard.
- Pertimbangkan otorisasi di `UpdateShipmentRequest::authorize()`.

---

### S3 — IDOR pada `show()` (SEDANG)
**Lokasi:** [api-kurir/app/Http/Controllers/Api/ShipmentController.php:208](api-kurir/app/Http/Controllers/Api/ShipmentController.php#L208)

`show()` memuat dan mengembalikan shipment apa pun berdasarkan id, tanpa memfilter berdasarkan role/kepemilikan. Sementara `index()` & dashboard sudah memfilter untuk Kurir (`where('assigned_driver_id', $user->id)`, lihat [DashboardController.php:82](api-kurir/app/Http/Controllers/Api/DashboardController.php#L82)), `show()` tidak. Seorang Kurir bisa menebak/iterasi `shipment_id` dan melihat detail (alamat, pelanggan, dsb.) pengiriman yang bukan miliknya.

**Rekomendasi:** terapkan policy:
```php
public function show(Shipment $shipment): JsonResponse
{
    $this->authorize('view', $shipment); // ShipmentPolicy: admin/manager bebas; Kurir hanya miliknya
    ...
}
```

---

### S4 — Tidak ada throttle pada login (SEDANG)
**Lokasi:** [api-kurir/routes/api.php:19](api-kurir/routes/api.php#L19), [:23](api-kurir/routes/api.php#L23) — `/login`, `/v1/auth/login`

Endpoint login ([AuthController@apiLogin](api-kurir/app/Http/Controllers/AuthController.php#L41), [Api\AuthController@login](api-kurir/app/Http/Controllers/Api/AuthController.php#L13)) memakai `Auth::attempt`/`Hash::check` dengan benar, tetapi route-nya tidak memakai middleware `throttle`. Tanpa rate limit, brute-force kredensial leluasa.

**Rekomendasi:** `Route::post('/v1/auth/login', ...)->middleware('throttle:5,1');` dan pertimbangkan throttle per-email + per-IP.

---

### S5 — Token admin tanpa kedaluwarsa (SEDANG)
**Lokasi:** [api-kurir/app/Http/Controllers/Api/AuthController.php:31-37](api-kurir/app/Http/Controllers/Api/AuthController.php#L31)

```php
$tokenResult = $user->createToken('api-token');
if ($user->hasRole('Kurir')) {
    $tokenResult->accessToken->update(['expires_at' => now()->addHours(24)]);
}
```

Hanya token **Kurir** yang diberi expiry 24 jam. Token Admin/Super Admin **tidak pernah kedaluwarsa** — bila bocor, akses berlaku selamanya. (Catatan: `AuthController@apiLogin` versi web bahkan membuat token tanpa expiry untuk semua role.)

**Rekomendasi:** set `expires_at` untuk semua token (mis. via `config/sanctum.php` `'expiration'`), dan batasi abilities token sesuai peran.

---

### S6 — `driver_id` tidak dibatasi kurir (SEDANG)
Sama dengan CODE_REVIEW C4: `assignDriver`/`bulkAssignDriver`/`approve` hanya `exists:users,id`. Selain bug data, ini celah otorisasi — pekerjaan bisa ditugaskan ke akun non-kurir/nonaktif. **Rekomendasi:** validasi role `Kurir` + `is_active` (kode di CODE_REVIEW C4).

---

### S7 — `generateTestToken` (RENDAH, kondisional)
**Lokasi:** [api-kurir/app/Http/Controllers/Api/AuthController.php:89](api-kurir/app/Http/Controllers/Api/AuthController.php#L89), route publik [api.php:26](api-kurir/routes/api.php#L26)

Endpoint **publik** (tanpa auth) yang menerbitkan token ber-ability penuh `['*']` untuk email mana pun **tanpa password**. Sudah dijaga `if (! app()->environment(['local','testing']))` → 403 di produksi, jadi aman **selama** `APP_ENV` di server benar (`production`).

**Rekomendasi:** karena dampaknya total (bypass autentikasi) bila env salah, sebaiknya **hapus dari route produksi** (daftarkan hanya di blok `if (app()->environment(...))` seperti pola `/_brain-logic` di [web.php](api-kurir/routes/web.php#L53)), bukan hanya cek di dalam controller. Pastikan juga `APP_ENV=production` di deployment (S9).

---

### S8 — Audit aksi sensitif (RENDAH)
Takeover tidak menyimpan jejak permanen (siapa/kapan/alasan) — lihat CODE_REVIEW C5. Untuk forensik & akuntabilitas, catat aksi assign/takeover/cancel ke tabel history. **Rekomendasi:** tulis ke `shipment_histories` pada setiap perubahan kepemilikan/status.

---

### S9 — Audit manual konfigurasi (RENDAH)
Tidak dapat diverifikasi otomatis dari review ini — **periksa manual** pada server:
- `APP_ENV=production` dan `APP_DEBUG=false` (mencegah bocornya stack trace & mengaktifkan guard S7).
- `APP_KEY` ter-set & tidak dibagikan.
- Kredensial DB/`.env` tidak ter-commit (cek `.gitignore`).
- `SANCTUM`/CORS dan `config/sanctum.php` `expiration` sesuai (lihat S5).
- HTTPS dipaksa untuk semua endpoint API.

---

## Rekomendasi prioritas

**Segera (Tinggi):**
1. Aktifkan kembali otorisasi `uploadAdminPhotos` (S1).
2. Tambah otorisasi `update()` & keluarkan `status` dari mass-update (S2).

**Berikutnya (Sedang):**
3. Policy kepemilikan pada `show()` (S3).
4. Throttle login (S4).
5. Expiry token untuk semua role (S5).
6. Validasi `driver_id` = kurir aktif (S6).

**Pengerasan (Rendah):**
7. Pindahkan `generateTestToken` ke route khusus non-produksi (S7).
8. Audit takeover ke history (S8).
9. Audit `.env`/konfigurasi server (S9).

> Aspek correctness/desain (race condition, batas takeover, dll.) ada di **[CODE_REVIEW.md](CODE_REVIEW.md)**.
