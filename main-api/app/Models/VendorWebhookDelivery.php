<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorWebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'event_type',
        'payload',
        'url',
        'http_status',
        'response_body',
        'attempt_count',
        'status',
        'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'       => 'array',
            'next_retry_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
