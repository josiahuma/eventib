<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Payout; // your model

class PayoutRequestedAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Payout $payout) {}

    public function build()
    {
        return $this->subject('Payout requested: #'.$this->payout->id)
            ->markdown('emails.admin.payout-requested', ['payout' => $this->payout]);
    }
}
