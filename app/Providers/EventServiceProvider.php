<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use App\Listeners\SendWelcomeEmail;
use App\Listeners\SendSignupEmails;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{

    protected $listen = [
        Registered::class => [
            SendWelcomeEmail::class,
            SendSignupEmails::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

}