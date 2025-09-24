<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UnlockPurchasedUserMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public int $amountMinor,     // e.g. 999
        public string $currency,     // e.g. GBP
        public string $receiptUrl = '' // optional
    ) {}

    public function build()
    {
        return $this->subject('Your Event Unlock is active 🎉')
            ->markdown('emails.unlock.purchased', [
                'userName'   => $this->userName,
                'amount'     => number_format($this->amountMinor/100, 2),
                'currency'   => $this->currency,
                'receiptUrl' => $this->receiptUrl,
            ]);
    }
}
