<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegistrantBulkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Event $event,
        public string $subjectLine,
        public string $messageHtml
    ) {}

    public function build()
    {
        // Ensure relations are available (handles projects that disable lazy loading)
        $this->event->loadMissing(['organizer', 'user']);

        // Compute clean display values once
        $organizerName  = $this->event->organizer?->name
            ?? $this->event->user?->name
            ?? 'the organizer';

        $organizerEmail = $this->event->organizer_email
            ?? $this->event->organizer?->contact_email
            ?? $this->event->user?->email
            ?? null;

        return $this->subject($this->subjectLine)
            ->view('emails.bulk-registrants')
            ->text('emails.bulk-registrants-plain')
            ->with([
                'event'          => $this->event,
                'messageHtml'    => $this->messageHtml,
                'organizerName'  => $organizerName,
                'organizerEmail' => $organizerEmail,
            ]);
    }
}
