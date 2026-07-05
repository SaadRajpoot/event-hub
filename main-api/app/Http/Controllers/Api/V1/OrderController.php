<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['items.ticketType', 'event'])
            ->where('attendee_id', $request->user()->attendee->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json(['data' => $orders]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);
        $order->load(['items.ticketType', 'event.vendor', 'attendee.user']);
        return response()->json(['data' => $order]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.ticket_type_id' => ['required', 'integer', 'exists:ticket_types,id'],
            'items.*.quantity'       => ['required', 'integer', 'min:1'],
        ]);

        $order = $this->orderService->createOrder($request->user()->attendee, $data);
        return response()->json(['message' => 'Order created. Complete payment within 15 minutes.', 'data' => $order], 201);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $order = $this->orderService->cancelOrder($order, $data['reason'] ?? 'Cancelled by user.', $request->user()->id);
        return response()->json(['message' => 'Order cancelled.', 'data' => $order]);
    }

    public function checkIn(Request $request, OrderItem $orderItem): JsonResponse
    {
        // Vendor checks in attendee by ticket_code
        $this->authorize('checkIn', $orderItem);
        $orderItem = $this->orderService->checkIn($orderItem);
        return response()->json(['message' => 'Check-in successful.', 'data' => $orderItem]);
    }

    public function findByTicketCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket_code' => ['required', 'string'],
        ]);

        $orderItem = OrderItem::with(['order.attendee.user', 'ticketType.event'])
            ->where('ticket_code', $data['ticket_code'])
            ->firstOrFail();

        $this->authorize('checkIn', $orderItem);

        return response()->json(['data' => $orderItem]);
    }
}
