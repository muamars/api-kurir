<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TugasPengiriman extends Model
{
    protected $table = 'tugas_pengiriman';

    protected $fillable = ['tugas', 'deskripsi', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'tugas_pengiriman_id');
    }
}
