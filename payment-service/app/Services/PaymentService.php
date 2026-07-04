<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public function initiate(array $data): Payment
    {
        return Payment::create([
            'payment_reference' => 'PAY-' . strtoupper(Str::random(12)),
            'order_reference'   => $data['order_reference'],
            'amount'            => $data['amount'],
            'currency'          => $data['currency'] ?? 'MYR',
            'payment_method'    => $data['payment_method'] ?? null,
            'callback_url'      => $data['callback_url'],
            'redirect_url'      => $data['redirect_url'] ?? null,
            'status'            => 'pending',
            'expires_at'        => now()->addMinutes(15),
        ]);
    }

    /**
     * Simulate payment completion (mock provider).
     * In production, this would be triggered by a real payment gateway callback.
     */
    public function complete(Payment $payment, string $paymentMethod = 'fpx'): Payment
    {
        $payment->update([
            'status'             => 'completed',
            'payment_method'     => $paymentMethod,
            'provider_reference' => 'MOCK-' . strtoupper(Str::random(8)),
            'provider_response'  => ['mock' => true, 'timestamp' => now()->toIso8601String()],
            'paid_at'            => now(),
        ]);

        $this->deliverWebhook($payment, 'payment.success');

        return $payment->fresh();
    }

    public function fail(Payment $payment, string $reason = 'Payment declined'): Payment
    {
        $payment->update([
            'status'         => 'failed',
            'failure_reason' => $reason,
            'failed_at'      => now(),
        ]);

        $this->deliverWebhook($payment, 'payment.failed');

        return $payment->fresh();
    }

    public function expire(Payment $payment): Payment
    {
        if ($payment->status !== 'pending') {
            return $payment;
        }

        $payment->update(['status' => 'expired']);
        $this->deliverWebhook($payment, 'payment.expired');

        return $payment->fresh();
    }

    public function deliverWebhook(Payment $payment, string $event): void
    {
        $secret = config('services.main_api.webhook_secret');
        $payload = [
            'event'             => $event,
            'order_reference'   => $payment->order_reference,
            'payment_reference' => $payment->payment_reference,
            'payment_method'    => $payment->payment_method,
            'amount'            => $payment->amount,
            'currency'          => $payment->currency,
            'timestamp'         => now()->toIso8601String(),
        ];

        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), $secret ?? '');

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Payment-Signature' => $signature,
                    'Content-Type'        => 'application/json',
                ])
                ->post($payment->callback_url, $payload);

            $payment->update([
                'webhook_delivered_at' => $response->successful() ? now() : null,
                'webhook_attempts'     => $payment->webhook_attempts + 1,
            ]);

            if (!$response->successful()) {
                Log::warning('Webhook delivery failed', [
                    'payment_reference' => $payment->payment_reference,
                    'status'            => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Webhook delivery exception', [
                'payment_reference' => $payment->payment_reference,
                'error'             => $e->getMessage(),
            ]);
            $payment->increment('webhook_attempts');
        }
    }
}
