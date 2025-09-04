<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventTicket;
use App\Services\TicketService;
use Barryvdh\DomPDF\Facade\Pdf;

class TicketPdfController extends Controller
{
    /** Attendee / organizer: registration-level PDF with all tickets */
    public function registration(Event $event, EventRegistration $registration)
    {
        abort_unless($registration->event_id === $event->id, 404);

        $tickets = app(TicketService::class)->ensureTickets($event, $registration);

        // Generate SVG QR strings for DomPDF (best fidelity)
        $qr = [];
        $svc = app(TicketService::class);
        foreach ($tickets as $t) {
            $payload = $svc->qrPayload($event->public_id, $t->token);
            $qr[$t->id] = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(220)->margin(1)->generate($payload);
        }

        $pdf = Pdf::loadView('tickets.pdf.registration', [
            'event'        => $event,
            'registration' => $registration,
            'tickets'      => $tickets,
            'qr'           => $qr,
        ])->setPaper('a4');

        return $pdf->download('tickets-'.$event->public_id.'-reg-'.$registration->id.'.pdf');
    }

    /** Optional: single ticket PDF */
    public function single(Event $event, EventRegistration $registration, EventTicket $ticket)
    {
        abort_unless(
            $registration->event_id === $event->id &&
            $ticket->event_id === $event->id &&
            $ticket->registration_id === $registration->id,
            404
        );

        $svc = app(TicketService::class);
        $payload = $svc->qrPayload($event->public_id, $ticket->token);
        $qr = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(240)->margin(1)->generate($payload);

        $pdf = Pdf::loadView('tickets.pdf.single', compact('event','registration','ticket','qr'))->setPaper('a6');

        return $pdf->download('ticket-'.$ticket->serial.'.pdf');
    }
}
