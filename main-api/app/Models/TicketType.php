<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'total_quantity',
        'quantity_sold',
        'quantity_reserved',
        'max_per_order',
        'sale_starts_at',
        'sale_ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'              => 'decimal:2',
            'sale_starts_at'     => 'datetime',
            'sale_ends_at'       => 'datetime',
            'is_active'          => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function waitlists(): HasMany
    {
        return $this->hasMany(Waitlist::class);
    }

    public function getAvailableQuantity(): int
    {
        return $this->total_quantity - $this->quantity_sold - $this->quantity_reserved;
    }

    public function hasAvailability(int $requested = 1): bool
    {
        return $this->getAvailableQuantity() >= $requested;
    }

    public function isSaleOpen(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        $now = now();
        if ($this->sale_starts_at && $now->lt($this->sale_starts_at)) {
            return false;
        }
        if ($this->sale_ends_at && $now->gt($this->sale_ends_at)) {
            return false;
        }
        return true;
    }
}
