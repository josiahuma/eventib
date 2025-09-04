<?php

// app/Observers/EventRegistrationObserver.php
namespace App\Observers;

use App\Models\EventRegistration;
use App\Services\TicketService;
use App\Mail\TicketsIssuedMail;
use Illuminate\Support\Facades\Mail;

class EventRegistrationObserver
{
    public function saved(EventRegistration $reg): void
    {
        // only when transitioning to paid
        if ($reg->getOriginal('status') === 'paid' || $reg->status !== 'paid') return;

        $event = $reg->event()->first();          // assumes event() relation exists
        if (!$event) return;

        $tickets = app(TicketService::class)->ensureTickets($event, $reg);

        if ($reg->email) {
            Mail::to($reg->email)->queue(new TicketsIssuedMail($event, $reg, $tickets));
        }
    }
}
