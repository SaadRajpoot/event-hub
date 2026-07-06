'use client';

export const dynamic = 'force-dynamic';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/contexts/AuthContext';
import { api, ApiError } from '@/lib/api';
import { Order, ApiResponse } from '@/types';
import Navbar from '@/components/Navbar';

const STATUS_COLORS: Record<string, string> = {
  pending_payment: 'bg-yellow-100 text-yellow-800',
  confirmed: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800',
  expired: 'bg-gray-100 text-gray-800',
  refunded: 'bg-blue-100 text-blue-800',
};

export default function OrderDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { user, token, loading: authLoading } = useAuth();
  const router = useRouter();
  const [order, setOrder] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true);
  const [cancelling, setCancelling] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [orderId, setOrderId] = useState<string | null>(null);

  useEffect(() => {
    params.then(({ id }) => setOrderId(id));
  }, [params]);

  useEffect(() => {
    if (!authLoading && !user) {
      router.push('/login');
      return;
    }
    if (user && token && orderId) {
      api.get<ApiResponse<Order>>(`/orders/${orderId}`, token)
        .then((res) => setOrder(res.data))
        .catch(() => router.push('/orders'))
        .finally(() => setLoading(false));
    }
  }, [user, token, authLoading, orderId, router]);

  const handleCancel = async () => {
    if (!order || !token) return;
    if (!confirm('Are you sure you want to cancel this order?')) return;

    setCancelling(true);
    setError(null);

    try {
      const res = await api.post<ApiResponse<Order>>(`/orders/${order.id}/cancel`, {}, token);
      setOrder(res.data);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError('Failed to cancel order.');
      }
    } finally {
      setCancelling(false);
    }
  };

  if (authLoading || loading) {
    return (
      <div className="min-h-screen bg-gray-50">
        <Navbar />
        <div className="flex items-center justify-center py-16">
          <p className="text-gray-500">Loading...</p>
        </div>
      </div>
    );
  }

  if (!order) return null;

  const canCancel = ['pending_payment', 'confirmed'].includes(order.status);

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />
      <main className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div className="flex items-start justify-between mb-6">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">{order.order_number}</h1>
              {order.event && (
                <p className="text-gray-600 mt-1">{order.event.title}</p>
              )}
            </div>
            <span className={`px-3 py-1 text-sm font-medium rounded-full ${STATUS_COLORS[order.status] || 'bg-gray-100 text-gray-800'}`}>
              {order.status.replace('_', ' ')}
            </span>
          </div>

          {order.status === 'pending_payment' && order.expires_at && (
            <div className="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md text-sm text-yellow-800">
              ⏰ Complete payment before {new Date(order.expires_at).toLocaleTimeString()}
            </div>
          )}

          {error && (
            <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
              {error}
            </div>
          )}

          {/* Order Items */}
          {order.items && order.items.length > 0 && (
            <div className="mb-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-3">Tickets</h2>
              <div className="space-y-3">
                {order.items.map((item) => (
                  <div key={item.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                      <p className="font-medium text-gray-900">{item.ticket_type?.name}</p>
                      <p className="text-sm text-gray-500">Qty: {item.quantity}</p>
                      {order.status === 'confirmed' && (
                        <p className="text-xs font-mono text-indigo-600 mt-1">🎫 {item.ticket_code}</p>
                      )}
                    </div>
                    <p className="font-medium text-gray-900">
                      MYR {parseFloat(item.subtotal).toFixed(2)}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Order Summary */}
          <div className="border-t border-gray-200 pt-4 space-y-2">
            <div className="flex justify-between text-sm text-gray-600">
              <span>Subtotal</span>
              <span>MYR {parseFloat(order.subtotal).toFixed(2)}</span>
            </div>
            <div className="flex justify-between font-semibold text-gray-900">
              <span>Total</span>
              <span>MYR {parseFloat(order.total_amount).toFixed(2)}</span>
            </div>
          </div>

          {canCancel && (
            <div className="mt-6">
              <button
                onClick={handleCancel}
                disabled={cancelling}
                className="px-4 py-2 border border-red-300 text-red-700 rounded-md hover:bg-red-50 disabled:opacity-50 text-sm font-medium"
              >
                {cancelling ? 'Cancelling...' : 'Cancel Order'}
              </button>
            </div>
          )}
        </div>
      </main>
    </div>
  );
}
