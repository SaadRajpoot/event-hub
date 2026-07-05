<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_number',
        'vendor_id',
        'gross_amount',
        'platform_fee',
        'net_amount',
        'order_count',
        'status',
        'payment_reference',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount'  => 'decimal:2',
            'platform_fee'  => 'decimal:2',
            'net_amount'    => 'decimal:2',
            'processed_at'  => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function payoutOrderItems(): HasMany
    {
        return $this->hasMany(PayoutOrderItem::class);
    }
}
