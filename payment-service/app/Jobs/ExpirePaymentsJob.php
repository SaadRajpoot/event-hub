<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpirePaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PaymentService $paymentService): void
    {
        $expired = Payment::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $payment) {
            try {
                $paymentService->expire($payment);
            } catch (\Throwable $e) {
                Log::error('Failed to expire payment', [
                    'payment_reference' => $payment->payment_reference,
                    'error'             => $e->getMessage(),
                ]);
            }
        }

        Log::info('ExpirePaymentsJob completed', ['expired_count' => $expired->count()]);
    }
}
