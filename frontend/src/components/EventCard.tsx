import Link from 'next/link';
import { Event } from '@/types';

interface EventCardProps {
  event: Event;
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-MY', {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function getMinPrice(event: Event): string {
  if (!event.ticket_types || event.ticket_types.length === 0) return 'Free';
  const prices = event.ticket_types.map((t) => parseFloat(t.price));
  const min = Math.min(...prices);
  return min === 0 ? 'Free' : `MYR ${min.toFixed(2)}`;
}

export default function EventCard({ event }: EventCardProps) {
  return (
    <Link href={`/events/${event.slug}`} className="block group">
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
        {event.banner_image ? (
          <img
            src={event.banner_image}
            alt={event.title}
            className="w-full h-48 object-cover"
          />
        ) : (
          <div className="w-full h-48 bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center">
            <span className="text-white text-4xl">🎉</span>
          </div>
        )}
        <div className="p-4">
          {event.category && (
            <span className="inline-block px-2 py-1 text-xs font-medium bg-indigo-100 text-indigo-700 rounded-full mb-2">
              {event.category}
            </span>
          )}
          <h3 className="font-semibold text-gray-900 group-hover:text-indigo-600 line-clamp-2 mb-1">
            {event.title}
          </h3>
          <p className="text-sm text-gray-500 mb-2">{formatDate(event.starts_at)}</p>
          {event.venue_name && (
            <p className="text-sm text-gray-500 mb-3">📍 {event.venue_name}</p>
          )}
          <div className="flex items-center justify-between">
            <span className="text-sm font-medium text-indigo-600">{getMinPrice(event)}</span>
            {event.vendor && (
              <span className="text-xs text-gray-400">{event.vendor.business_name}</span>
            )}
          </div>
        </div>
      </div>
    </Link>
  );
}
