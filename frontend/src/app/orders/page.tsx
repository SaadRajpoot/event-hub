'use client';

export const dynamic = 'force-dynamic';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/contexts/AuthContext';
import { api } from '@/lib/api';
import { Order, PaginatedResponse } from '@/types';
import Navbar from '@/components/Navbar';

const STATUS_COLORS: Record<string, string> = {
  pending_payment: 'bg-yellow-100 text-yellow-800',
  confirmed: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800',
  expired: 'bg-gray-100 text-gray-800',
  refunded: 'bg-blue-100 text-blue-800',
};

export default function OrdersPage() {
  const { user, token, loading: authLoading } = useAuth();
  const router = useRouter();
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!authLoading && !user) {
      router.push('/login?redirect=/orders');
      return;
    }
    if (user && token) {
      api.get<PaginatedResponse<Order>>('/orders', token)
        .then((res) => setOrders(res.data.data))
        .catch(console.error)
        .finally(() => setLoading(false));
    }
  }, [user, token, authLoading, router]);

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

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />
      <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 className="text-2xl font-bold text-gray-900 mb-6">My Orders</h1>

        {orders.length === 0 ? (
          <div className="text-center py-16">
            <p className="text-gray-500 mb-4">You haven&apos;t placed any orders yet.</p>
            <Link href="/events" className="text-indigo-600 hover:text-indigo-800 font-medium">
              Browse Events
            </Link>
          </div>
        ) : (
          <div className="space-y-4">
            {orders.map((order) => (
              <Link key={order.id} href={`/orders/${order.id}`} className="block">
                <div className="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-sm transition-shadow">
                  <div className="flex items-start justify-between">
                    <div>
                      <p className="font-semibold text-gray-900">{order.order_number}</p>
                      {order.event && (
                        <p className="text-sm text-gray-600 mt-1">{order.event.title}</p>
                      )}
                      <p className="text-sm text-gray-500 mt-1">
                        {new Date(order.created_at as unknown as string).toLocaleDateString()}
                      </p>
                    </div>
                    <div className="text-right">
                      <span className={`inline-block px-2 py-1 text-xs font-medium rounded-full ${STATUS_COLORS[order.status] || 'bg-gray-100 text-gray-800'}`}>
                        {order.status.replace('_', ' ')}
                      </span>
                      <p className="text-sm font-semibold text-gray-900 mt-2">
                        MYR {parseFloat(order.total_amount).toFixed(2)}
                      </p>
                    </div>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        )}
      </main>
    </div>
  );
}
