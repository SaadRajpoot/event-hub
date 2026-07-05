<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PayoutBatch;
use App\Policies\EventPolicy;
use App\Policies\OrderItemPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PayoutBatchPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Event::class       => EventPolicy::class,
        Order::class       => OrderPolicy::class,
        OrderItem::class   => OrderItemPolicy::class,
        PayoutBatch::class => PayoutBatchPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
