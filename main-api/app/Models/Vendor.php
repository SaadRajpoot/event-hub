<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'business_name',
        'slug',
        'description',
        'contact_email',
        'contact_phone',
        'bank_account_name',
        'bank_account_number',
        'bank_name',
        'status',
        'webhook_url',
        'webhook_secret',
        'commission_rate',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function payoutBatches(): HasMany
    {
        return $this->hasMany(PayoutBatch::class);
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(VendorWebhookDelivery::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getEffectiveCommissionRate(): float
    {
        if ($this->commission_rate !== null) {
            return (float) $this->commission_rate;
        }
        return (float) config('platform.commission_rate', 0.10);
    }
}
