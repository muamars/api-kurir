<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentHistory extends Model
{
    protected $table = 'shipment_history';

    protected $fillable = [
        'shipment_row_id',
        'shipment_id',
        'created_by',
        'approved_by',
        'assigned_driver_id',
        'category_id',
        'vehicle_type_id',
        'division_id',
        'tugas_pengiriman_id',
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
        'shipping_cost',
        'vehicle_used',
        'online_tracking_url',
        'completion_photo',
        'completed_at',
        'completed_by',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
        'destinations_snapshot',
        'items_snapshot',
        'archived_at',
        'archive_reason',
    ];

    protected $casts = [
        'approved_at'                 => 'datetime',
        'deadline'                    => 'datetime',
        'scheduled_delivery_datetime' => 'datetime',
        'completed_at'                => 'datetime',
        'cancelled_at'                => 'datetime',
        'archived_at'                 => 'datetime',
        'destinations_snapshot'       => 'array',
        'items_snapshot'              => 'array',
        'deadline_locked'             => 'boolean',
    ];

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeByDriver($query, int $driverId)
    {
        return $query->where('assigned_driver_id', $driverId);
    }

    public function scopeByYear($query, int $year)
    {
        return $query->whereYear('archived_at', $year);
    }
}
