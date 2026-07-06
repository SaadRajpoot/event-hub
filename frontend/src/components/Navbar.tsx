'use client';

import Link from 'next/link';
import { useAuth } from '@/contexts/AuthContext';
import { useRouter } from 'next/navigation';

export default function Navbar() {
  const { user, logout, loading } = useAuth();
  const router = useRouter();

  const handleLogout = async () => {
    await logout();
    router.push('/');
  };

  return (
    <nav className="bg-white shadow-sm border-b border-gray-200">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16 items-center">
          <Link href="/" className="text-xl font-bold text-indigo-600">
            EventHub
          </Link>

          <div className="flex items-center gap-4">
            <Link href="/events" className="text-gray-600 hover:text-gray-900 text-sm font-medium">
              Events
            </Link>

            {!loading && (
              <>
                {user ? (
                  <>
                    {user.role === 'vendor' && (
                      <Link href="/vendor/dashboard" className="text-gray-600 hover:text-gray-900 text-sm font-medium">
                        Dashboard
                      </Link>
                    )}
                    {user.role === 'attendee' && (
                      <Link href="/orders" className="text-gray-600 hover:text-gray-900 text-sm font-medium">
                        My Orders
                      </Link>
                    )}
                    {user.role === 'admin' && (
                      <Link href="/admin/dashboard" className="text-gray-600 hover:text-gray-900 text-sm font-medium">
                        Admin
                      </Link>
                    )}
                    <span className="text-sm text-gray-500">{user.name}</span>
                    <button
                      onClick={handleLogout}
                      className="text-sm text-red-600 hover:text-red-800 font-medium"
                    >
                      Logout
                    </button>
                  </>
                ) : (
                  <>
                    <Link href="/login" className="text-gray-600 hover:text-gray-900 text-sm font-medium">
                      Login
                    </Link>
                    <Link
                      href="/register"
                      className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
                    >
                      Sign Up
                    </Link>
                  </>
                )}
              </>
            )}
          </div>
        </div>
      </div>
    </nav>
  );
}
