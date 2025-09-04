<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventRegistration;
use App\Services\TicketService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketsIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Event $event,
        public EventRegistration $registration,
        public $tickets // Collection<EventTicket>
    ) {}

    public function build()
    {
        $service = app(TicketService::class);

        // Build an array with QR SVGs (inline) for the email
        $qr = [];
        foreach ($this->tickets as $t) {
            $payload = $service->qrPayload($this->event->public_id, $t->token);
            $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(220)->margin(1)->generate($payload);
            $qr[$t->id] = $svg;
        }

        $listUrl = route('tickets.list', [$this->event, $this->registration]);
        $pdfUrl  = route('tickets.pdf.registration', [$this->event, $this->registration]);

        return $this->subject('Your tickets — '.$this->event->name)
            ->view('emails.tickets-issued', [
                'event'        => $this->event,
                'registration' => $this->registration,
                'tickets'      => $this->tickets,
                'qr'           => $qr,
                'listUrl'      => $listUrl,
                'pdfUrl'       => $pdfUrl,
            ]);
    }
}
