<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UnlockPurchasedAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userEmail,
        public int $amountMinor,
        public string $currency,
        public string $stripeSessionId
    ) {}

    public function build()
    {
        return $this->subject('Unlock purchased: '.$this->userEmail)
            ->markdown('emails.admin.unlock-purchased', [
                'userEmail' => $this->userEmail,
                'amount'    => number_format($this->amountMinor/100, 2),
                'currency'  => $this->currency,
                'sessionId' => $this->stripeSessionId,
            ]);
    }
}
