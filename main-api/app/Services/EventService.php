<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Vendor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class EventService
{
    public function listPublished(array $filters = []): LengthAwarePaginator
    {
        $query = Event::with(['vendor', 'ticketTypes'])
            ->where('status', 'published');

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }
        if (!empty($filters['starts_after'])) {
            $query->where('starts_at', '>=', $filters['starts_after']);
        }
        if (!empty($filters['starts_before'])) {
            $query->where('starts_at', '<=', $filters['starts_before']);
        }

        return $query->orderBy('starts_at')->paginate($filters['per_page'] ?? 15);
    }

    public function listForVendor(Vendor $vendor, array $filters = []): LengthAwarePaginator
    {
        $query = Event::with('ticketTypes')
            ->where('vendor_id', $vendor->id);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function create(Vendor $vendor, array $data): Event
    {
        $slug = Str::slug($data['title']);
        $originalSlug = $slug;
        $count = 1;
        while (Event::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        return Event::create(array_merge($data, [
            'vendor_id' => $vendor->id,
            'slug'      => $slug,
            'status'    => $data['status'] ?? 'draft',
        ]));
    }

    public function update(Event $event, array $data): Event
    {
        if (isset($data['title']) && $data['title'] !== $event->title) {
            $slug = Str::slug($data['title']);
            $originalSlug = $slug;
            $count = 1;
            while (Event::where('slug', $slug)->where('id', '!=', $event->id)->exists()) {
                $slug = $originalSlug . '-' . $count++;
            }
            $data['slug'] = $slug;
        }

        $event->update($data);
        return $event->fresh(['vendor', 'ticketTypes']);
    }

    public function publish(Event $event): Event
    {
        $event->update(['status' => 'published']);
        return $event;
    }

    public function cancel(Event $event): Event
    {
        $event->update(['status' => 'cancelled']);
        return $event;
    }

    public function delete(Event $event): void
    {
        $event->delete();
    }
}
