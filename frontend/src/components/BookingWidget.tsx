'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Event, TicketType } from '@/types';
import { useAuth } from '@/contexts/AuthContext';
import { api, ApiError } from '@/lib/api';

interface BookingWidgetProps {
  event: Event;
}

interface CartItem {
  ticketType: TicketType;
  quantity: number;
}

export default function BookingWidget({ event }: BookingWidgetProps) {
  const { user, token } = useAuth();
  const router = useRouter();
  const [cart, setCart] = useState<CartItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const ticketTypes = event.ticket_types?.filter((t) => t.is_active) ?? [];

  const updateQuantity = (ticketType: TicketType, quantity: number) => {
    setCart((prev) => {
      const existing = prev.find((c) => c.ticketType.id === ticketType.id);
      if (quantity === 0) {
        return prev.filter((c) => c.ticketType.id !== ticketType.id);
      }
      if (existing) {
        return prev.map((c) =>
          c.ticketType.id === ticketType.id ? { ...c, quantity } : c
        );
      }
      return [...prev, { ticketType, quantity }];
    });
  };

  const getQuantity = (ticketTypeId: number) =>
    cart.find((c) => c.ticketType.id === ticketTypeId)?.quantity ?? 0;

  const total = cart.reduce(
    (sum, item) => sum + parseFloat(item.ticketType.price) * item.quantity,
    0
  );

  const available = (tt: TicketType) =>
    tt.total_quantity - tt.quantity_sold - tt.quantity_reserved;

  const handleCheckout = async () => {
    if (!user || !token) {
      router.push('/login?redirect=' + encodeURIComponent(`/events/${event.slug}`));
      return;
    }

    if (user.role !== 'attendee') {
      setError('Only attendees can purchase tickets.');
      return;
    }

    if (cart.length === 0) {
      setError('Please select at least one ticket.');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const res = await api.post<{ data: { id: number; order_number: string } }>(
        '/orders',
        {
          items: cart.map((item) => ({
            ticket_type_id: item.ticketType.id,
            quantity: item.quantity,
          })),
        },
        token
      );

      router.push(`/orders/${res.data.id}`);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError('Failed to create order. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  };

  if (ticketTypes.length === 0) {
    return (
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <p className="text-gray-500 text-center">No tickets available for this event.</p>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sticky top-4">
      <h2 className="text-xl font-semibold text-gray-900 mb-4">Get Tickets</h2>

      <div className="space-y-4 mb-6">
        {ticketTypes.map((tt) => {
          const qty = getQuantity(tt.id);
          const avail = available(tt);
          const price = parseFloat(tt.price);

          return (
            <div key={tt.id} className="border border-gray-200 rounded-lg p-4">
              <div className="flex justify-between items-start mb-2">
                <div>
                  <h3 className="font-medium text-gray-900">{tt.name}</h3>
                  {tt.description && (
                    <p className="text-sm text-gray-500">{tt.description}</p>
                  )}
                </div>
                <span className="font-semibold text-indigo-600">
                  {price === 0 ? 'Free' : `MYR ${price.toFixed(2)}`}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-xs text-gray-400">
                  {avail > 0 ? `${avail} available` : 'Sold out'}
                </span>
                {avail > 0 && (
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => updateQuantity(tt, Math.max(0, qty - 1))}
                      className="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-50 disabled:opacity-50"
                      disabled={qty === 0}
                    >
                      −
                    </button>
                    <span className="w-6 text-center font-medium">{qty}</span>
                    <button
                      onClick={() =>
                        updateQuantity(tt, Math.min(tt.max_per_order, avail, qty + 1))
                      }
                      className="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-50 disabled:opacity-50"
                      disabled={qty >= Math.min(tt.max_per_order, avail)}
                    >
                      +
                    </button>
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>

      {cart.length > 0 && (
        <div className="border-t border-gray-200 pt-4 mb-4">
          <div className="flex justify-between text-sm text-gray-600 mb-1">
            <span>Subtotal</span>
            <span>MYR {total.toFixed(2)}</span>
          </div>
          <div className="flex justify-between font-semibold text-gray-900">
            <span>Total</span>
            <span>MYR {total.toFixed(2)}</span>
          </div>
        </div>
      )}

      {error && (
        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
          {error}
        </div>
      )}

      <button
        onClick={handleCheckout}
        disabled={loading || cart.length === 0}
        className="w-full py-3 px-4 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        {loading ? 'Processing...' : cart.length === 0 ? 'Select Tickets' : `Checkout — MYR ${total.toFixed(2)}`}
      </button>

      {!user && (
        <p className="text-xs text-gray-500 text-center mt-2">
          You&apos;ll need to log in to complete your purchase.
        </p>
      )}
    </div>
  );
}
