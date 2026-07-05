<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function update(User $user, Event $event): bool
    {
        return $user->isVendor() && $user->vendor?->id === $event->vendor_id;
    }

    public function delete(User $user, Event $event): bool
    {
        return ($user->isVendor() && $user->vendor?->id === $event->vendor_id)
            || $user->isAdmin();
    }
}
