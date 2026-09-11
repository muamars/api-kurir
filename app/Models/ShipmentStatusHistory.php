<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'shipment_status_histories';

    protected $fillable = [
        'shipment_id',
        'old_status',
        'new_status',
        'action',
        'actor_id',
        'driver_id',
        'previous_driver_id',
        'reason',
        'metadata',
        'changed_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'changed_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function previousDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_driver_id');
    }

    /**
     * Helper to log status transition
     */
    public static function record(
        int $shipmentId,
        ?string $oldStatus,
        string $newStatus,
        string $action,
        ?int $actorId = null,
        ?int $driverId = null,
        ?int $previousDriverId = null,
        ?string $reason = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'shipment_id'        => $shipmentId,
            'old_status'         => $oldStatus,
            'new_status'         => $newStatus,
            'action'             => $action,
            'actor_id'           => $actorId ?? auth()->id(),
            'driver_id'          => $driverId,
            'previous_driver_id' => $previousDriverId,
            'reason'             => $reason,
            'metadata'           => $metadata,
            'changed_at'         => now(),
        ]);
    }
}
