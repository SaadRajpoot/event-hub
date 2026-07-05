<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * Handle payment service webhook callbacks.
     * Validates HMAC signature before processing.
     */
    public function handlePaymentWebhook(Request $request): JsonResponse
    {
        $signature = $request->header('X-Payment-Signature');
        $secret = config('services.payment_service.webhook_secret');

        if (!$signature || !$secret) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);
        if (!hash_equals('sha256=' . $expectedSignature, $signature)) {
            Log::warning('Payment webhook signature mismatch');
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $data = $request->validate([
            'event'              => ['required', 'string'],
            'order_reference'    => ['required', 'string'],
            'payment_reference'  => ['required', 'string'],
            'payment_method'     => ['nullable', 'string'],
        ]);

        $order = Order::where('order_number', $data['order_reference'])->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        match ($data['event']) {
            'payment.success' => $this->orderService->confirmOrder($order, [
                'payment_method'    => $data['payment_method'] ?? 'unknown',
                'payment_reference' => $data['payment_reference'],
            ]),
            'payment.failed', 'payment.expired' => $this->orderService->expireOrder($order),
            default => null,
        };

        return response()->json(['message' => 'Webhook processed.']);
    }
}
