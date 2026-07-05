<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(OrderService $orderService): void
    {
        $expiredOrders = Order::where('status', 'pending_payment')
            ->where('expires_at', '<', now())
            ->with('items')
            ->get();

        foreach ($expiredOrders as $order) {
            try {
                $orderService->expireOrder($order);
            } catch (\Throwable $e) {
                Log::error('Failed to expire order', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        Log::info('ExpireOrdersJob completed', ['expired_count' => $expiredOrders->count()]);
    }
}
