<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isAttendee() && $user->attendee?->id === $order->attendee_id) return true;
        if ($user->isVendor() && $user->vendor?->id === $order->vendor_id) return true;
        return false;
    }

    public function cancel(User $user, Order $order): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isAttendee() && $user->attendee?->id === $order->attendee_id) {
            return in_array($order->status, ['pending_payment', 'confirmed']);
        }
        return false;
    }
}
