<?php

namespace App\Jobs;

use App\Models\VendorWebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryWebhookDeliveriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(WebhookService $webhookService): void
    {
        $deliveries = VendorWebhookDelivery::where('status', 'failed')
            ->where('attempt_count', '<', 5)
            ->where('next_retry_at', '<=', now())
            ->with('vendor')
            ->get();

        foreach ($deliveries as $delivery) {
            try {
                $webhookService->attempt($delivery, $delivery->vendor);
            } catch (\Throwable $e) {
                Log::error('Webhook retry failed', ['delivery_id' => $delivery->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
