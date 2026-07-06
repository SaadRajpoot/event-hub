import { api } from '@/lib/api';
import { Event, PaginatedResponse } from '@/types';
import EventCard from '@/components/EventCard';
import Navbar from '@/components/Navbar';

interface SearchParams {
  category?: string;
  search?: string;
  page?: string;
}

async function getEvents(params: SearchParams): Promise<{ events: Event[]; total: number; lastPage: number }> {
  try {
    const query = new URLSearchParams();
    if (params.category) query.set('category', params.category);
    if (params.search) query.set('search', params.search);
    if (params.page) query.set('page', params.page);
    query.set('per_page', '12');

    const res = await api.get<PaginatedResponse<Event>>(`/events?${query.toString()}`);
    return {
      events: res.data.data,
      total: res.data.total,
      lastPage: res.data.last_page,
    };
  } catch {
    return { events: [], total: 0, lastPage: 1 };
  }
}

export default async function EventsPage({ searchParams }: { searchParams: Promise<SearchParams> }) {
  const params = await searchParams;
  const { events, total, lastPage } = await getEvents(params);
  const currentPage = parseInt(params.page || '1');

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-4">All Events</h1>
          <form method="GET" className="flex gap-3">
            <input
              type="text"
              name="search"
              defaultValue={params.search}
              placeholder="Search events..."
              className="flex-1 px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <select
              name="category"
              defaultValue={params.category}
              className="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="">All Categories</option>
              <option value="music">Music</option>
              <option value="sports">Sports</option>
              <option value="tech">Tech</option>
              <option value="food">Food & Drink</option>
              <option value="arts">Arts & Culture</option>
              <option value="business">Business</option>
            </select>
            <button
              type="submit"
              className="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
            >
              Search
            </button>
          </form>
        </div>

        <p className="text-sm text-gray-500 mb-4">{total} events found</p>

        {events.length > 0 ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {events.map((event) => (
              <EventCard key={event.id} event={event} />
            ))}
          </div>
        ) : (
          <div className="text-center py-16">
            <p className="text-gray-500 text-lg">No events found.</p>
          </div>
        )}

        {lastPage > 1 && (
          <div className="flex justify-center gap-2 mt-8">
            {Array.from({ length: lastPage }, (_, i) => i + 1).map((page) => (
              <a
                key={page}
                href={`?${new URLSearchParams({ ...params, page: String(page) }).toString()}`}
                className={`px-4 py-2 rounded-md text-sm font-medium ${
                  page === currentPage
                    ? 'bg-indigo-600 text-white'
                    : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                }`}
              >
                {page}
              </a>
            ))}
          </div>
        )}
      </main>
    </div>
  );
}
