<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentDestination extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'customer_id',
        'receiver_company',
        'receiver_name',
        'receiver_contact',
        'delivery_address',
        'shipment_note',
        'sequence_order',
        'status',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(DestinationStatusHistory::class, 'destination_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(ShipmentProgress::class, 'destination_id');
    }

    public function scopeBySequence($query)
    {
        return $query->orderBy('sequence_order');
    }
}
