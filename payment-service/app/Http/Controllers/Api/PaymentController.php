<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * Initiate a new payment session.
     * Called by main-api when an order is created.
     */
    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_reference' => ['required', 'string', 'max:50'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'currency'        => ['nullable', 'string', 'size:3'],
            'payment_method'  => ['nullable', 'string', 'in:fpx,card,ewallet'],
            'callback_url'    => ['required', 'url'],
            'redirect_url'    => ['nullable', 'url'],
        ]);

        $payment = $this->paymentService->initiate($data);

        return response()->json([
            'message' => 'Payment session created.',
            'data'    => [
                'payment_reference' => $payment->payment_reference,
                'payment_url'       => url("/api/pay/{$payment->payment_reference}"),
                'expires_at'        => $payment->expires_at,
            ],
        ], 201);
    }

    /**
     * Get payment status.
     */
    public function status(string $paymentReference): JsonResponse
    {
        $payment = Payment::where('payment_reference', $paymentReference)->firstOrFail();

        return response()->json(['data' => $payment]);
    }

    /**
     * Mock payment completion endpoint (simulates user completing payment).
     * In production this would be a real gateway callback.
     */
    public function mockComplete(Request $request, string $paymentReference): JsonResponse
    {
        $payment = Payment::where('payment_reference', $paymentReference)
            ->where('status', 'pending')
            ->firstOrFail();

        if ($payment->isExpired()) {
            $this->paymentService->expire($payment);
            return response()->json(['message' => 'Payment has expired.'], 422);
        }

        $data = $request->validate([
            'payment_method' => ['nullable', 'string', 'in:fpx,card,ewallet'],
            'simulate_fail'  => ['nullable', 'boolean'],
        ]);

        if (!empty($data['simulate_fail'])) {
            $payment = $this->paymentService->fail($payment, 'Simulated failure.');
            return response()->json(['message' => 'Payment failed (simulated).', 'data' => $payment]);
        }

        $payment = $this->paymentService->complete($payment, $data['payment_method'] ?? 'fpx');
        return response()->json(['message' => 'Payment completed.', 'data' => $payment]);
    }

    /**
     * List payments (internal/admin use).
     */
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->order_reference, fn($q) => $q->where('order_reference', $request->order_reference))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['data' => $payments]);
    }
}
