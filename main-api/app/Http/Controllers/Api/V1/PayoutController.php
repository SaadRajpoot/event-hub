<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PayoutBatch;
use App\Services\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function __construct(private readonly PayoutService $payoutService) {}

    public function index(Request $request): JsonResponse
    {
        $batches = PayoutBatch::where('vendor_id', $request->user()->vendor->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json(['data' => $batches]);
    }

    public function show(Request $request, PayoutBatch $payoutBatch): JsonResponse
    {
        $this->authorize('view', $payoutBatch);
        $payoutBatch->load('payoutOrderItems.orderItem.order');
        return response()->json(['data' => $payoutBatch]);
    }

    // Admin: trigger payout batch creation for a vendor
    public function createBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
        ]);

        $vendor = \App\Models\Vendor::findOrFail($data['vendor_id']);
        $batch = $this->payoutService->createBatchForVendor($vendor);

        if (!$batch) {
            return response()->json(['message' => 'No eligible orders for payout.'], 422);
        }

        return response()->json(['message' => 'Payout batch created.', 'data' => $batch], 201);
    }
}
