import { notFound } from 'next/navigation';
import { api } from '@/lib/api';
import { Event, ApiResponse } from '@/types';
import Navbar from '@/components/Navbar';
import BookingWidget from '@/components/BookingWidget';

async function getEvent(slug: string): Promise<Event | null> {
  try {
    const res = await api.get<ApiResponse<Event>>(`/events/${slug}`);
    return res.data;
  } catch {
    return null;
  }
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-MY', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export default async function EventDetailPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const event = await getEvent(slug);

  if (!event) {
    notFound();
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Event Details */}
          <div className="lg:col-span-2">
            {event.banner_image ? (
              <img
                src={event.banner_image}
                alt={event.title}
                className="w-full h-64 object-cover rounded-xl mb-6"
              />
            ) : (
              <div className="w-full h-64 bg-gradient-to-br from-indigo-400 to-purple-500 rounded-xl mb-6 flex items-center justify-center">
                <span className="text-white text-6xl">🎉</span>
              </div>
            )}

            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
              {event.category && (
                <span className="inline-block px-3 py-1 text-sm font-medium bg-indigo-100 text-indigo-700 rounded-full mb-3">
                  {event.category}
                </span>
              )}
              <h1 className="text-3xl font-bold text-gray-900 mb-4">{event.title}</h1>

              <div className="space-y-3 mb-6">
                <div className="flex items-center gap-2 text-gray-600">
                  <span>📅</span>
                  <span>{formatDate(event.starts_at)}</span>
                </div>
                {event.ends_at && (
                  <div className="flex items-center gap-2 text-gray-600">
                    <span>⏰</span>
                    <span>Ends: {formatDate(event.ends_at)}</span>
                  </div>
                )}
                {event.venue_name && (
                  <div className="flex items-center gap-2 text-gray-600">
                    <span>📍</span>
                    <span>{event.venue_name}{event.location ? `, ${event.location}` : ''}</span>
                  </div>
                )}
                {event.vendor && (
                  <div className="flex items-center gap-2 text-gray-600">
                    <span>🏢</span>
                    <span>Organized by {event.vendor.business_name}</span>
                  </div>
                )}
              </div>

              {event.description && (
                <div>
                  <h2 className="text-xl font-semibold text-gray-900 mb-3">About this event</h2>
                  <p className="text-gray-600 whitespace-pre-wrap">{event.description}</p>
                </div>
              )}

              {event.tags && event.tags.length > 0 && (
                <div className="mt-4 flex flex-wrap gap-2">
                  {event.tags.map((tag) => (
                    <span key={tag} className="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">
                      #{tag}
                    </span>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Booking Widget */}
          <div className="lg:col-span-1">
            <BookingWidget event={event} />
          </div>
        </div>
      </main>
    </div>
  );
}
