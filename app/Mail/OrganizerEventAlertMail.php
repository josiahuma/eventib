<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrganizerEventAlertMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public Event $event, public string $unsubscribeUrl) {}
    public function build()
    {
        return $this->subject(($this->event->organizer?->name ?? 'An organiser you follow').' has a new event')
            ->markdown('emails.organizers.event-alert');
    }
}
