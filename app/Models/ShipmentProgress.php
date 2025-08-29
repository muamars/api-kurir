<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'destination_id',
        'driver_id',
        'status',
        'progress_time',
        'photo_url',
        'photo_thumbnail',
        'note',
        'action_button',
        'receiver_name',
        'received_photo_url',
    ];

    protected $casts = [
        'progress_time' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(ShipmentDestination::class, 'destination_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
