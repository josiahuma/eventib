<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;
use App\Mail\AdminNewUserMail;
use App\Mail\WelcomeUserMail; // you already have this

class SendSignupEmails
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        // to ops
        Mail::to(config('mail.ops_address'))->queue(new AdminNewUserMail($user));

        // to user (if you want your existing welcome)
        try {
            Mail::to($user->email)->queue(new WelcomeUserMail($user));
        } catch (\Throwable $e) {
            // swallow if WelcomeUserMail expects different ctor; adjust if needed
        }
    }
}
