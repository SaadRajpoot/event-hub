<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_reference',
        'order_reference',
        'amount',
        'currency',
        'payment_method',
        'status',
        'provider_reference',
        'provider_response',
        'callback_url',
        'redirect_url',
        'expires_at',
        'paid_at',
        'failed_at',
        'failure_reason',
        'webhook_delivered_at',
        'webhook_attempts',
    ];

    protected function casts(): array
    {
        return [
            'amount'               => 'decimal:2',
            'provider_response'    => 'array',
            'expires_at'           => 'datetime',
            'paid_at'              => 'datetime',
            'failed_at'            => 'datetime',
            'webhook_delivered_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->gt($this->expires_at) && $this->status === 'pending';
    }
}
