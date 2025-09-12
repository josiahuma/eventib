<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data) { $this->data = $data; }

    public function build()
    {
        return $this->subject('New Contact Message: '.$this->data['subject'])
            ->replyTo($this->data['email'], $this->data['name'])
            ->view('emails.contact-message');
    }
}