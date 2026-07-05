<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TicketType;

class TicketTypeService
{
    public function create(Event $event, array $data): TicketType
    {
        return TicketType::create(array_merge($data, ['event_id' => $event->id]));
    }

    public function update(TicketType $ticketType, array $data): TicketType
    {
        $ticketType->update($data);
        return $ticketType->fresh();
    }

    public function delete(TicketType $ticketType): void
    {
        $ticketType->delete();
    }
}
