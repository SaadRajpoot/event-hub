<?php

namespace App\Policies;

use App\Models\PayoutBatch;
use App\Models\User;

class PayoutBatchPolicy
{
    public function view(User $user, PayoutBatch $payoutBatch): bool
    {
        if ($user->isAdmin()) return true;
        return $user->isVendor() && $user->vendor?->id === $payoutBatch->vendor_id;
    }
}
