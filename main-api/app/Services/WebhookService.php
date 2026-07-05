<?php

namespace App\Services;

use App\Models\Vendor;
use App\Models\VendorWebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    public function dispatch(Vendor $vendor, string $eventType, array $payload): void
    {
        if (empty($vendor->webhook_url)) {
            return;
        }

        $delivery = VendorWebhookDelivery::create([
            'vendor_id'    => $vendor->id,
            'event_type'   => $eventType,
            'payload'      => $payload,
            'url'          => $vendor->webhook_url,
            'status'       => 'pending',
            'attempt_count' => 0,
        ]);

        $this->attempt($delivery, $vendor);
    }

    public function attempt(VendorWebhookDelivery $delivery, Vendor $vendor): void
    {
        try {
            $signature = hash_hmac('sha256', json_encode($delivery->payload), $vendor->webhook_secret ?? '');

            $response = Http::timeout(10)
                ->withHeaders([
                    'X-EventHub-Signature' => 'sha256=' . $signature,
                    'X-EventHub-Event'     => $delivery->event_type,
                    'Content-Type'         => 'application/json',
                ])
                ->post($delivery->url, $delivery->payload);

            $delivery->update([
                'http_status'   => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
                'attempt_count' => $delivery->attempt_count + 1,
                'status'        => $response->successful() ? 'delivered' : 'failed',
                'next_retry_at' => $response->successful() ? null : now()->addMinutes(5 * ($delivery->attempt_count + 1)),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Webhook delivery failed', ['delivery_id' => $delivery->id, 'error' => $e->getMessage()]);
            $delivery->update([
                'attempt_count' => $delivery->attempt_count + 1,
                'status'        => 'failed',
                'next_retry_at' => now()->addMinutes(5 * ($delivery->attempt_count + 1)),
            ]);
        }
    }
}
