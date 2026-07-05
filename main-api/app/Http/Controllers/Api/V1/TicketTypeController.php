<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketType;
use App\Services\TicketTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketTypeController extends Controller
{
    public function __construct(private readonly TicketTypeService $ticketTypeService) {}

    public function store(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'price'          => ['required', 'numeric', 'min:0'],
            'total_quantity' => ['required', 'integer', 'min:1'],
            'max_per_order'  => ['nullable', 'integer', 'min:1', 'max:100'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at'   => ['nullable', 'date'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $ticketType = $this->ticketTypeService->create($event, $data);
        return response()->json(['message' => 'Ticket type created.', 'data' => $ticketType], 201);
    }

    public function update(Request $request, Event $event, TicketType $ticketType): JsonResponse
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'price'          => ['sometimes', 'numeric', 'min:0'],
            'total_quantity' => ['sometimes', 'integer', 'min:1'],
            'max_per_order'  => ['nullable', 'integer', 'min:1', 'max:100'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at'   => ['nullable', 'date'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $ticketType = $this->ticketTypeService->update($ticketType, $data);
        return response()->json(['message' => 'Ticket type updated.', 'data' => $ticketType]);
    }

    public function destroy(Event $event, TicketType $ticketType): JsonResponse
    {
        $this->authorize('update', $event);
        $this->ticketTypeService->delete($ticketType);
        return response()->json(['message' => 'Ticket type deleted.']);
    }
}
