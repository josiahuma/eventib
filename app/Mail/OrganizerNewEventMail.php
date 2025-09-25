<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Event;
use App\Models\Organizer;

class OrganizerNewEventMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Organizer $organizer, public Event $event) {}

    public function build()
    {
        return $this->subject($this->organizer->name . ' just published a new event')
            ->markdown('emails.organizers.new-event', [
                'organizer' => $this->organizer,
                'event'     => $this->event,
            ]);
    }
}
