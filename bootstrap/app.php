<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->command('queue:work --stop-when-empty --sleep=3 --tries=3 --max-time=300')
            ->everyMinute()
            ->withoutOverlapping();
    })
    ->withProviders([
        App\Providers\AppServiceProvider::class,
        App\Providers\EventServiceProvider::class, // ← add this line
    ])->create();
