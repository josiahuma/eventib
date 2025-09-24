<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Organizer;
use App\Models\User;

class OrganizerFollowedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Organizer $organizer, public User $follower) {}

    public function build()
    {
        return $this->subject('New follower: '.$this->follower->name)
            ->markdown('emails.organizers.followed', [
                'organizer' => $this->organizer,
                'follower'  => $this->follower,
            ]);
    }
}
