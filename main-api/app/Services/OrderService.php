<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\Waitlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function createOrder(Attendee $attendee, array $data): Order
    {
        return DB::transaction(function () use ($attendee, $data) {
            $items = $data['items']; // [['ticket_type_id' => x, 'quantity' => y], ...]

            $subtotal = 0;
            $lineItems = [];

            foreach ($items as $item) {
                $ticketType = TicketType::lockForUpdate()->findOrFail($item['ticket_type_id']);

                if (!$ticketType->isSaleOpen()) {
                    throw ValidationException::withMessages([
                        'items' => ["Ticket type '{$ticketType->name}' is not available for sale."],
                    ]);
                }

                if (!$ticketType->hasAvailability($item['quantity'])) {
                    throw ValidationException::withMessages([
                        'items' => ["Not enough tickets available for '{$ticketType->name}'."],
                    ]);
                }

                if ($item['quantity'] > $ticketType->max_per_order) {
                    throw ValidationException::withMessages([
                        'items' => ["Maximum {$ticketType->max_per_order} tickets per order for '{$ticketType->name}'."],
                    ]);
                }

                $lineSubtotal = $ticketType->price * $item['quantity'];
                $subtotal += $lineSubtotal;

                $lineItems[] = [
                    'ticket_type'  => $ticketType,
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $ticketType->price,
                    'subtotal'     => $lineSubtotal,
                ];

                // Reserve tickets
                $ticketType->increment('quantity_reserved', $item['quantity']);
            }

            $commissionRate = $lineItems[0]['ticket_type']->event->vendor->getEffectiveCommissionRate();
            $platformFee = round($subtotal * $commissionRate, 2);
            $totalAmount = $subtotal;

            $order = Order::create([
                'order_number'    => 'ORD-' . strtoupper(Str::random(10)),
                'attendee_id'     => $attendee->id,
                'event_id'        => $lineItems[0]['ticket_type']->event_id,
                'vendor_id'       => $lineItems[0]['ticket_type']->event->vendor_id,
                'subtotal'        => $subtotal,
                'platform_fee'    => $platformFee,
                'total_amount'    => $totalAmount,
                'status'          => 'pending_payment',
                'expires_at'      => now()->addMinutes(15),
            ]);

            foreach ($lineItems as $line) {
                OrderItem::create([
                    'order_id'       => $order->id,
                    'ticket_type_id' => $line['ticket_type']->id,
                    'quantity'       => $line['quantity'],
                    'unit_price'     => $line['unit_price'],
                    'subtotal'       => $line['subtotal'],
                    'ticket_code'    => strtoupper(Str::random(16)),
                    'status'         => 'active',
                ]);
            }

            return $order->load(['items.ticketType', 'event', 'attendee.user']);
        });
    }

    public function confirmOrder(Order $order, array $paymentData): Order
    {
        return DB::transaction(function () use ($order, $paymentData) {
            if ($order->isExpired()) {
                $this->expireOrder($order);
                throw ValidationException::withMessages(['order' => ['Order has expired.']]);
            }

            foreach ($order->items as $item) {
                $ticketType = TicketType::lockForUpdate()->find($item->ticket_type_id);
                $ticketType->decrement('quantity_reserved', $item->quantity);
                $ticketType->increment('quantity_sold', $item->quantity);
            }

            $order->update([
                'status'            => 'confirmed',
                'payment_method'    => $paymentData['payment_method'],
                'payment_reference' => $paymentData['payment_reference'],
                'paid_at'           => now(),
            ]);

            return $order->fresh(['items.ticketType', 'event', 'attendee.user']);
        });
    }

    public function expireOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            if ($order->status !== 'pending_payment') {
                return;
            }

            foreach ($order->items as $item) {
                $ticketType = TicketType::lockForUpdate()->find($item->ticket_type_id);
                $ticketType->decrement('quantity_reserved', $item->quantity);

                // Notify waitlist
                $waitlisted = Waitlist::where('ticket_type_id', $item->ticket_type_id)
                    ->where('status', 'waiting')
                    ->orderBy('created_at')
                    ->first();
                if ($waitlisted) {
                    $waitlisted->update(['status' => 'notified', 'notified_at' => now()]);
                }
            }

            $order->update(['status' => 'expired']);
        });
    }

    public function cancelOrder(Order $order, string $reason, int $initiatedBy): Order
    {
        return DB::transaction(function () use ($order, $reason, $initiatedBy) {
            foreach ($order->items as $item) {
                if ($order->status === 'pending_payment') {
                    $ticketType = TicketType::lockForUpdate()->find($item->ticket_type_id);
                    $ticketType->decrement('quantity_reserved', $item->quantity);
                } elseif ($order->status === 'confirmed') {
                    $ticketType = TicketType::lockForUpdate()->find($item->ticket_type_id);
                    $ticketType->decrement('quantity_sold', $item->quantity);
                }
                $item->update(['status' => 'cancelled']);
            }

            $order->update([
                'status'              => 'cancelled',
                'cancelled_at'        => now(),
                'cancellation_reason' => $reason,
            ]);

            return $order->fresh();
        });
    }

    public function checkIn(OrderItem $orderItem): OrderItem
    {
        if ($orderItem->status !== 'active') {
            throw ValidationException::withMessages(['ticket' => ['Ticket is not valid for check-in.']]);
        }

        $orderItem->update([
            'status'        => 'used',
            'checked_in_at' => now(),
        ]);

        return $orderItem->fresh();
    }
}
