<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PayoutBatch;
use App\Models\PayoutOrderItem;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayoutService
{
    public function createBatchForVendor(Vendor $vendor): ?PayoutBatch
    {
        return DB::transaction(function () use ($vendor) {
            // Find confirmed orders not yet in a payout batch
            $orders = Order::where('vendor_id', $vendor->id)
                ->where('status', 'confirmed')
                ->whereDoesntHave('items.payoutOrderItems')
                ->with('items')
                ->get();

            if ($orders->isEmpty()) {
                return null;
            }

            $commissionRate = $vendor->getEffectiveCommissionRate();
            $grossAmount = 0;
            $platformFee = 0;
            $orderItemsData = [];

            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    if ($item->status !== 'active' && $item->status !== 'used') {
                        continue;
                    }
                    $itemGross = (float) $item->subtotal;
                    $itemFee = round($itemGross * $commissionRate, 2);
                    $itemNet = $itemGross - $itemFee;

                    $grossAmount += $itemGross;
                    $platformFee += $itemFee;

                    $orderItemsData[] = [
                        'order_item_id' => $item->id,
                        'gross_amount'  => $itemGross,
                        'platform_fee'  => $itemFee,
                        'net_amount'    => $itemNet,
                    ];
                }
            }

            if (empty($orderItemsData)) {
                return null;
            }

            $netAmount = $grossAmount - $platformFee;

            $batch = PayoutBatch::create([
                'batch_number'     => 'PAY-' . strtoupper(Str::random(10)),
                'vendor_id'        => $vendor->id,
                'gross_amount'     => $grossAmount,
                'platform_fee'     => $platformFee,
                'net_amount'       => $netAmount,
                'order_count'      => $orders->count(),
                'status'           => 'pending',
            ]);

            foreach ($orderItemsData as $itemData) {
                PayoutOrderItem::create(array_merge($itemData, ['payout_batch_id' => $batch->id]));
            }

            return $batch->fresh();
        });
    }

    public function markBatchCompleted(PayoutBatch $batch, string $paymentReference): PayoutBatch
    {
        $batch->update([
            'status'           => 'completed',
            'payment_reference' => $paymentReference,
            'processed_at'     => now(),
        ]);
        return $batch->fresh();
    }

    public function markBatchFailed(PayoutBatch $batch): PayoutBatch
    {
        $batch->update(['status' => 'failed']);
        return $batch->fresh();
    }
}
