<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'created_by',
        'approved_by',
        'assigned_driver_id',
        'approved_at',
        'status',
        'notes',
        'priority',
        'deadline',
        'surat_pengantar_kerja',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'deadline' => 'datetime',
    ];

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

    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent');
    }

    public function scopeRegular($query)
    {
        return $query->where('priority', 'regular');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForDriver($query, $driverId)
    {
        return $query->where('assigned_driver_id', $driverId);
    }
}
