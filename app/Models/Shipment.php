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
        'vehicle_type_id',
        'approved_at',
        'status',
        'notes',
        'courier_notes',
        'priority',
        'deadline',
        'scheduled_delivery_datetime',
        'surat_pengantar_kerja',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'deadline' => 'datetime',
        'scheduled_delivery_datetime' => 'datetime',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'shipment_id';
    }

    // tambahan baru

    // batas

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
}
