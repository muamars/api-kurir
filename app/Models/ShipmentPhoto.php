<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentPhoto extends Model
{
    use HasFactory;

    const TYPE_ADMIN_UPLOAD = 'admin_upload';
    const TYPE_PICKUP = 'pickup';
    const TYPE_DELIVERY = 'delivery';

    protected $fillable = [
        'shipment_id',
        'type',
        'photo_url',
        'photo_thumbnail',
        'uploaded_by',
        'notes',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForShipment($query, $shipmentId)
    {
        return $query->where('shipment_id', $shipmentId);
    }
}
