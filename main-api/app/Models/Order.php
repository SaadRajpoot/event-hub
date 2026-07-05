<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'attendee_id',
        'event_id',
        'vendor_id',
        'subtotal',
        'platform_fee',
        'total_amount',
        'status',
        'payment_method',
        'payment_reference',
        'expires_at',
        'paid_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'       => 'decimal:2',
            'platform_fee'   => 'decimal:2',
            'total_amount'   => 'decimal:2',
            'expires_at'     => 'datetime',
            'paid_at'        => 'datetime',
            'cancelled_at'   => 'datetime',
        ];
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->gt($this->expires_at)
            && $this->status === 'pending_payment';
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['confirmed', 'partially_refunded']);
    }

    public function canBeRefunded(): bool
    {
        return in_array($this->status, ['confirmed', 'partially_refunded']);
    }
}
