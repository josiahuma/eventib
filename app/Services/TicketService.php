<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventTicket;

class TicketService
{
    /** Idempotent: creates missing tickets (one per quantity). Returns ordered collection. */
    public function ensureTickets(Event $event, EventRegistration $registration)
    {
        $qty = max(1, (int)($registration->quantity ?? 1));
        $existing = $registration->tickets()->count();

        for ($i = $existing; $i < $qty; $i++) {
            $token  = EventTicket::makeToken($event->public_id, $registration->id, $i);
            $serial = EventTicket::makeSerial($registration->id, $i);
            if (!EventTicket::where('token', $token)->exists()) {
                $registration->tickets()->create(compact('serial', 'token') + [
                    'event_id' => $event->id,
                    'index'    => $i,
                ]);
            }
        }

        return $registration->tickets()->orderBy('index')->get();
    }

    /** Encodes the exact QR payload our scanner expects */
    public function qrPayload(string $eventPublicId, string $ticketToken): string
    {
        return "ET|v1|{$eventPublicId}|{$ticketToken}";
    }
}
