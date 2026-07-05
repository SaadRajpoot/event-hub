<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(private readonly EventService $eventService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category'     => ['nullable', 'string', 'max:100'],
            'search'       => ['nullable', 'string', 'max:255'],
            'starts_after' => ['nullable', 'date'],
            'starts_before'=> ['nullable', 'date'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $events = $this->eventService->listPublished($filters);
        return response()->json(['data' => $events]);
    }

    public function show(string $slug): JsonResponse
    {
        $event = Event::with(['vendor', 'ticketTypes' => function ($q) {
            $q->where('is_active', true)->whereNull('deleted_at');
        }])->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return response()->json(['data' => $event]);
    }

    // Vendor endpoints
    public function vendorIndex(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;
        $filters = $request->validate([
            'status'   => ['nullable', 'string', 'in:draft,published,cancelled,completed'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $events = $this->eventService->listForVendor($vendor, $filters);
        return response()->json(['data' => $events]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'location'       => ['nullable', 'string', 'max:500'],
            'venue_name'     => ['nullable', 'string', 'max:255'],
            'latitude'       => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'      => ['nullable', 'numeric', 'between:-180,180'],
            'starts_at'      => ['required', 'date', 'after:now'],
            'ends_at'        => ['required', 'date', 'after:starts_at'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at'   => ['nullable', 'date', 'before:ends_at'],
            'category'       => ['nullable', 'string', 'max:100'],
            'tags'           => ['nullable', 'array'],
            'tags.*'         => ['string', 'max:50'],
            'status'         => ['nullable', 'in:draft,published'],
        ]);

        $event = $this->eventService->create($request->user()->vendor, $data);
        return response()->json(['message' => 'Event created.', 'data' => $event], 201);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'title'          => ['sometimes', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'location'       => ['nullable', 'string', 'max:500'],
            'venue_name'     => ['nullable', 'string', 'max:255'],
            'latitude'       => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'      => ['nullable', 'numeric', 'between:-180,180'],
            'starts_at'      => ['sometimes', 'date'],
            'ends_at'        => ['sometimes', 'date', 'after:starts_at'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at'   => ['nullable', 'date'],
            'category'       => ['nullable', 'string', 'max:100'],
            'tags'           => ['nullable', 'array'],
            'tags.*'         => ['string', 'max:50'],
            'status'         => ['nullable', 'in:draft,published,cancelled'],
        ]);

        $event = $this->eventService->update($event, $data);
        return response()->json(['message' => 'Event updated.', 'data' => $event]);
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);
        $this->eventService->delete($event);
        return response()->json(['message' => 'Event deleted.']);
    }
}
