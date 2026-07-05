<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'title',
        'slug',
        'description',
        'location',
        'venue_name',
        'latitude',
        'longitude',
        'starts_at',
        'ends_at',
        'sale_starts_at',
        'sale_ends_at',
        'banner_image',
        'category',
        'tags',
        'status',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'      => 'datetime',
            'ends_at'        => 'datetime',
            'sale_starts_at' => 'datetime',
            'sale_ends_at'   => 'datetime',
            'tags'           => 'array',
            'is_featured'    => 'boolean',
            'latitude'       => 'decimal:7',
            'longitude'      => 'decimal:7',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isSaleOpen(): bool
    {
        $now = now();
        if ($this->sale_starts_at && $now->lt($this->sale_starts_at)) {
            return false;
        }
        if ($this->sale_ends_at && $now->gt($this->sale_ends_at)) {
            return false;
        }
        return $this->isPublished();
    }
}
