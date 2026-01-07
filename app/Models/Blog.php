<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Blog extends Model
{
    protected $fillable = [
        'title',
        'content',
        'slug',
        'is_published',
        'target_audience',
        'image_url',
        'image_thumbnail',
        'user_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get target audience label
     */
    public function getTargetAudienceLabelAttribute(): string
    {
        return match ($this->target_audience) {
            'all' => 'Semua User',
            'user' => 'User Biasa',
            'kurir' => 'Driver/Kurir',
            default => ucfirst($this->target_audience),
        };
    }

    /**
     * Get full URL for image
     */
    public function getImageUrlFullAttribute(): ?string
    {
        return $this->image_url ? asset('storage/' . $this->image_url) : null;
    }

    /**
     * Get full URL for thumbnail
     */
    public function getImageThumbnailFullAttribute(): ?string
    {
        return $this->image_thumbnail ? asset('storage/' . $this->image_thumbnail) : null;
    }

    /**
     * Scope untuk filter berdasarkan role user
     */
    public function scopeForRole($query, $userRole)
    {
        if ($userRole === 'Admin') {
            return $query; // Admin bisa melihat semua
        }
        
        $allowedAudiences = ['all'];
        
        if ($userRole === 'Kurir') {
            $allowedAudiences[] = 'kurir';
        } else {
            $allowedAudiences[] = 'user';
        }
        
        return $query->whereIn('target_audience', $allowedAudiences);
    }
}
