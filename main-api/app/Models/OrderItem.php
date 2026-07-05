<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'ticket_type_id',
        'quantity',
        'unit_price',
        'subtotal',
        'ticket_code',
        'status',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'     => 'decimal:2',
            'subtotal'       => 'decimal:2',
            'checked_in_at'  => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function payoutOrderItems(): HasMany
    {
        return $this->hasMany(PayoutOrderItem::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
