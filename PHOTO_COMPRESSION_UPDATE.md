# Photo Compression Update

## Perubahan yang Dilakukan

### 1. Upload Size Limit
- **Tetap**: 5MB (5120KB) - untuk memberikan fleksibilitas upload
- **Benefit**: User bisa upload photo berkualitas tinggi, sistem akan auto-compress

### 2. Auto Compression System
Sistem sekarang otomatis mengkompress photo dengan:

#### Smart Quality Compression
- **> 2MP**: Quality 70%
- **> 1MP**: Quality 75% 
- **> 0.5MP**: Quality 80%
- **< 0.5MP**: Quality 85%

#### Auto Resize
- Maksimal resolusi: 1920x1920px
- Mempertahankan aspect ratio
- Thumbnail tetap 300x300px dengan quality 70%

#### Format Standardization
- Semua photo disimpan sebagai JPG untuk kompresi optimal
- Filename format: `timestamp_uniqid.jpg`

### 3. Controllers yang Diupdate

#### ShipmentPhotoController
- Admin upload photos (max 5 photos)
- Driver pickup photos
- Driver delivery photos
- Storage: `storage/app/public/shipments/{id}/{type}/`

#### ShipmentProgressController  
- Progress tracking photos
- Received photos (optional)
- Storage: `storage/app/public/shipment-photos/`

### 4. Struktur Penyimpanan

**ShipmentPhotoController:**
```
storage/app/public/shipments/{id}/
├── admin/originals/     # Compressed admin photos (70-85% quality)
├── admin/thumbnails/    # 300x300 thumbnails (70% quality)
├── pickup/originals/    # Compressed pickup photos
├── pickup/thumbnails/   # Pickup thumbnails
├── delivery/originals/  # Compressed delivery photos
└── delivery/thumbnails/ # Delivery thumbnails
```

**ShipmentProgressController:**
```
storage/app/public/shipment-photos/
├── {timestamp}_{uniqid}.jpg           # Compressed progress photos
├── thumb_{timestamp}_{uniqid}.jpg     # Progress thumbnails
└── received_{timestamp}_{uniqid}.jpg  # Compressed received photos
```

### 5. Benefits
- User bisa upload photo hingga 5MB (fleksibel)
- Hasil akhir tetap optimal size (hemat storage & bandwidth)
- Upload experience tetap smooth
- Kualitas visual tetap terjaga dengan smart compression
- Konsisten format JPG untuk semua photo
- Smart compression berdasarkan resolusi photo
- Unified compression system across all photo uploads

## Workflow
1. User upload photo (max 5MB)
2. Sistem auto-resize jika > 1920px
3. Sistem auto-compress berdasarkan resolusi
4. Simpan sebagai JPG dengan quality optimal
5. Generate thumbnail 300x300px

## Testing
Untuk test fitur ini:
1. Upload photo besar (2-5MB) di ShipmentPhotoController - akan diterima dan di-compress
2. Upload progress photo di ShipmentProgressController - akan di-compress
3. Upload received photo - akan di-compress
4. Check file size hasil compression di storage (jauh lebih kecil)

## Photo Types yang Didukung
- **Admin Photos**: Multiple photos per shipment (max 5)
- **Pickup Photos**: Single photo when driver picks up
- **Delivery Photos**: Single photo when delivery completed
- **Progress Photos**: Photos during status updates
- **Received Photos**: Optional photos when item received