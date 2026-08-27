<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_ASSIGNED = 'assigned';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'shipment_id',
        'created_by',
        'approved_by',
        'assigned_driver_id',
        'category_id',
        'division_id',
        'tugas_pengiriman_id',
        'vehicle_type_id',
        'approved_at',
        'status',
        'notes',
        'courier_notes',
        'priority',
        'deadline',
        'deadline_locked',
        'scheduled_delivery_datetime',
        'surat_pengantar_kerja',
        'attachment_path',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
        'shipping_cost',
        'vehicle_used',
        'online_tracking_url',
        'completion_photo',
        'completed_at',
        'completed_by',
        'is_archived',
        'archived_at',
        'takeover_count',
        'needs_review',
        'last_takeover_at',
    ];

    protected $casts = [
        'approved_at'                 => 'datetime',
        'deadline'                    => 'datetime',
        'scheduled_delivery_datetime' => 'datetime',
        'completed_at'                => 'datetime',
        'cancelled_at'                => 'datetime',
        'archived_at'                 => 'datetime',
        'last_takeover_at'            => 'datetime',
        'deadline_locked'             => 'boolean',
        'is_archived'                 => 'boolean',
        'needs_review'                => 'boolean',
        'takeover_count'              => 'integer',
    ];

    protected $appends = [
        'proof_photo_url',
    ];

    public function getBulkAssignmentIdAttribute(): ?int
    {
        if (!$this->id) return null;
        $bulkId = \Illuminate\Support\Facades\DB::table('bulk_assignments')
            ->whereJsonContains('shipment_ids', (int) $this->id)
            ->orWhereJsonContains('shipment_ids', (string) $this->id)
            ->value('id');

        return $bulkId ? (int) $bulkId : null;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'shipment_id';
    }

    public function getProofPhotoUrlAttribute(): ?string
    {
        if ($this->relationLoaded('photos') && $this->photos->isNotEmpty()) {
            $photo = $this->photos->first();
            if ($photo && $photo->photo_url) {
                return \Illuminate\Support\Str::startsWith($photo->photo_url, ['http://', 'https://'])
                    ? $photo->photo_url
                    : asset('storage/' . ltrim($photo->photo_url, '/'));
            }
        } else {
            $photo = $this->photos()->first();
            if ($photo && $photo->photo_url) {
                return \Illuminate\Support\Str::startsWith($photo->photo_url, ['http://', 'https://'])
                    ? $photo->photo_url
                    : asset('storage/' . ltrim($photo->photo_url, '/'));
            }
        }

        if ($this->surat_pengantar_kerja) {
            return \Illuminate\Support\Str::startsWith($this->surat_pengantar_kerja, ['http://', 'https://'])
                ? $this->surat_pengantar_kerja
                : asset('storage/' . ltrim($this->surat_pengantar_kerja, '/'));
        }

        if ($this->attachment_path) {
            return \Illuminate\Support\Str::startsWith($this->attachment_path, ['http://', 'https://'])
                ? $this->attachment_path
                : asset('storage/' . ltrim($this->attachment_path, '/'));
        }

        if ($this->completion_photo) {
            return \Illuminate\Support\Str::startsWith($this->completion_photo, ['http://', 'https://'])
                ? $this->completion_photo
                : asset('storage/' . ltrim($this->completion_photo, '/'));
        }

        return null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_driver_id');
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(ShipmentDestination::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(ShipmentProgress::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ShipmentPhoto::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ShipmentCategory::class, 'category_id');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function tugasPengiriman(): BelongsTo
    {
        return $this->belongsTo(TugasPengiriman::class, 'tugas_pengiriman_id');
    }

    // Hanya shipment aktif (belum diarsip)
    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForDriver($query, $driverId)
    {
        return $query->where('assigned_driver_id', $driverId);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
