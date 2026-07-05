<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;

class OrderItemPolicy
{
    public function checkIn(User $user, OrderItem $orderItem): bool
    {
        if ($user->isAdmin()) return true;
        // Vendor can check in tickets for their own events
        if ($user->isVendor()) {
            return $user->vendor?->id === $orderItem->order->event->vendor_id;
        }
        return false;
    }
}
