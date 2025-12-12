<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'customer_name',
        'phone',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function shipmentDestinations(): HasMany
    {
        return $this->hasMany(ShipmentDestination::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
