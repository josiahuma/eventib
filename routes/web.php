<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RegistrantEmailController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RegistrantsController;
use App\Http\Controllers\PayoutController;
use App\Models\Event as EventModel;
use App\Http\Controllers\TicketLookupController;
use App\Http\Controllers\MyTicketsController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\PayoutMethodController;
use App\Http\Controllers\Admin\PayoutAdminController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Admin\EventAdminController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\CheckinsController;
use App\Http\Controllers\Admin\HomepageSlideController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\OrganizerController;
use App\Http\Controllers\Admin\HomepageSponsorController;
use App\Http\Controllers\DigitalPassController;



/*
|--------------------------------------------------------------------------
| Legacy numeric ID redirect (must be ABOVE other /events/* routes)
|--------------------------------------------------------------------------
*/
Route::get('/events/{id}/{tail?}', function (int $id, $tail = null) {
    $event = EventModel::query()->select('public_id')->find($id);
    abort_unless($event && $event->public_id, 404);

    $base = '/events/'.$event->public_id.($tail ? '/'.$tail : '');
    $qs   = request()->getQueryString();

    return redirect($base.($qs ? '?'.$qs : ''), 301);
})->whereNumber('id')->where('tail', '.*');

/*
|--------------------------------------------------------------------------
| Public pages
|--------------------------------------------------------------------------
*/
Route::get('/', [EventController::class, 'publicIndex'])->name('homepage');
Route::get('/how-it-works', [PageController::class, 'how'])->name('how');
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');

Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'contactSubmit'])->name('contact.submit');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/cookies', [PageController::class, 'cookies'])->name('cookies');
Route::get('/organizers/{organizer}', [OrganizerController::class, 'show'])->name('organizers.show');
Route::post('/organizers/{organizer}/contact', [OrganizerController::class, 'contact'])->name('organizers.contact');
Route::get('/events/past', [EventController::class, 'past'])->name('events.past');



/* Stripe webhook */
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

/* Social login */
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['google','github'])->name('oauth.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['google','github'])->name('oauth.callback');

/*
|--------------------------------------------------------------------------
| Admin (auth required; admin check in controllers)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Homepage slides
    Route::get('/slides',                [HomepageSlideController::class, 'index'])->name('slides.index');
    Route::get('/slides/create',         [HomepageSlideController::class, 'create'])->name('slides.create');
    Route::post('/slides',               [HomepageSlideController::class, 'store'])->name('slides.store');
    Route::get('/slides/{slide}/edit',   [HomepageSlideController::class, 'edit'])->name('slides.edit');
    Route::put('/slides/{slide}',        [HomepageSlideController::class, 'update'])->name('slides.update');
    Route::delete('/slides/{slide}',     [HomepageSlideController::class, 'destroy'])->name('slides.destroy');
    Route::resource('homepage-sponsors', HomepageSponsorController::class)->except(['show']);

    // Payouts
    Route::get('/payouts', [PayoutAdminController::class, 'index'])->name('payouts.index');
    Route::match(['patch','put'], '/payouts/{payout}/status', [PayoutAdminController::class, 'updateStatus'])
        ->name('payouts.updateStatus');

    // Users
    Route::get('/users', [UserAdminController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/toggle-admin', [UserAdminController::class, 'toggleAdmin'])->name('users.toggle-admin');
    Route::patch('/users/{user}/toggle-disabled', [UserAdminController::class, 'toggleDisabled'])->name('users.toggle-disabled');

    // Events
    Route::get('/events', [EventAdminController::class, 'index'])->name('events.index');
    Route::patch('/events/{event}/toggle-disabled', [EventAdminController::class, 'toggleDisabled'])->name('events.toggle-disabled');
    Route::patch('/events/{event}/toggle-promote', [EventAdminController::class, 'togglePromote'])->name('events.toggle-promote');
});

/*
|--------------------------------------------------------------------------
| Auth-only (organisers & attendees)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    // Profile & payouts
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get   ('/profile/payouts', [PayoutMethodController::class, 'index'])->name('profile.payouts');
    Route::post  ('/profile/payouts', [PayoutMethodController::class, 'store'])->name('profile.payouts.store');
    Route::get   ('/profile/payouts/{method}/edit', [PayoutMethodController::class, 'edit'])->name('profile.payouts.edit');
    Route::put   ('/profile/payouts/{method}', [PayoutMethodController::class, 'update'])->name('profile.payouts.update');
    Route::delete('/profile/payouts/{method}', [PayoutMethodController::class, 'destroy'])->name('profile.payouts.destroy');

    // Dashboard & events CRUD (no show)
    Route::get('/dashboard', [EventController::class, 'dashboard'])->name('dashboard');
    Route::resource('events', EventController::class)->except(['show']);

    // Registrants (organiser)
    Route::get('/events/{event}/registrants', [RegistrantsController::class, 'index'])->name('events.registrants');
    Route::get('/events/{event}/registrants/unlock', [RegistrantsController::class, 'unlock'])->name('events.registrants.unlock');
    Route::post('/events/{event}/registrants/checkout', [RegistrantsController::class, 'checkout'])->name('events.registrants.checkout');
    Route::get('/events/{event}/registrants/unlock/success', [RegistrantsController::class, 'success'])->name('events.registrants.unlock.success');

    // Organiser payouts
    Route::get('/payouts', [PayoutController::class, 'index'])->name('payouts.index');
    Route::get('/events/{event}/payouts/new', [PayoutController::class, 'create'])->name('payouts.create');
    Route::post('/events/{event}/payouts', [PayoutController::class, 'store'])->name('payouts.store');

    // Organizers management
    Route::get('/dashboard/organizers', [OrganizerController::class, 'index'])->name('organizers.index');
    Route::get('/dashboard/organizers/create', [OrganizerController::class, 'create'])->name('organizers.create');
    Route::post('/dashboard/organizers', [OrganizerController::class, 'store'])->name('organizers.store');
    Route::get('/dashboard/organizers/{organizer}/edit', [OrganizerController::class, 'edit'])->name('organizers.edit');
    Route::post('/organizers/{organizer}/follow', [OrganizerController::class, 'follow'])
    ->middleware('auth')->name('organizers.follow');
    Route::post('/organizers/{organizer}/unfollow', [OrganizerController::class, 'unfollow'])
    ->middleware('auth')->name('organizers.unfollow');
    Route::get('/organizers/{organizer}/edit', [OrganizerController::class, 'edit'])->name('organizers.edit');
    Route::put('/organizers/{organizer}', [OrganizerController::class, 'update'])->name('organizers.update');

    // Email registrants
    Route::get('/events/{event}/registrants/email', [RegistrantEmailController::class, 'create'])->name('events.registrants.email');
    Route::post('/events/{event}/registrants/email', [RegistrantEmailController::class, 'send'])->name('events.registrants.email.send');

    // My Tickets (attendees)
    Route::get('/my-tickets', [MyTicketsController::class, 'index'])->name('my.tickets');
    Route::get('/my-tickets/{registration}/edit', [MyTicketsController::class, 'edit'])->name('my.tickets.edit');
    Route::post('/my-tickets/{registration}', [MyTicketsController::class, 'update'])->name('my.tickets.update');

    // Ticket viewing (attendees)
    Route::get('/events/{event}/registrations/{registration}/tickets/first', [TicketController::class, 'first'])->name('tickets.first');
    Route::get('/events/{event}/registrations/{registration}/pass',        [TicketController::class, 'pass'])->name('tickets.pass');
    Route::get('/events/{event}/registrations/{registration}/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');

    // PDFs
    Route::get('/events/{event}/registrations/{registration}/tickets/{ticket}/pdf', [TicketController::class, 'pdfTicket'])->name('tickets.ticket.pdf');
    Route::get('/events/{event}/registrations/{registration}/tickets.pdf',          [TicketController::class, 'pdfRegistration'])->name('tickets.registration.pdf');

    // Scanner (organiser) — primary
    Route::get('/events/{event}/scan',  [TicketController::class, 'scanPage'])->name('tickets.scan.page');
    Route::post('/events/{event}/scan', [TicketController::class, 'scanValidate'])->name('tickets.scan.validate');

    // Scanner alias for older links
    Route::get('/events/{event}/tickets/scan', [TicketController::class, 'scanPage'])->name('tickets.scan');

    // Check-ins list (organiser)
    Route::get('/events/{event}/checkins', [CheckinsController::class, 'index'])->name('events.checkins.index');

    // (Optional) organiser: per-event ticket list + export
    Route::get('/events/{event}/tickets',             [TicketController::class, 'eventIndex'])->name('events.tickets.index');
    Route::get('/events/{event}/tickets/export-pdf',  [TicketController::class, 'exportPdf'])->name('events.tickets.export-pdf');

    // Digital Pass setup
    // Wizard page
    Route::get('/digital-pass/setup', [DigitalPassController::class, 'show'])
        ->name('digital-pass.setup');

    // Form POST from the Blade you pasted
    Route::post('/digital-pass/voice', [DigitalPassController::class, 'storeVoice'])
        ->name('digital-pass.store.voice');

    Route::delete('/digital-pass', [DigitalPassController::class, 'destroy'])
        ->name('digital-pass.destroy');

    Route::get('/digital-pass', [DigitalPassController::class, 'show'])
        ->name('digital-pass.show');

    Route::post('/digital-pass/voice-sample', [DigitalPassController::class, 'storeVoiceSample'])
        ->name('digital-pass.voice.sample');

    Route::post('/digital-pass/voice-finalize', [DigitalPassController::class, 'finalizeVoice'])
        ->name('digital-pass.voice.finalize');

    Route::post('/digital-pass/face-sample', [DigitalPassController::class, 'storeFaceSample'])
        ->name('digital-pass.face.sample');

    Route::post('/digital-pass/face-finalize', [DigitalPassController::class, 'finalizeFace'])
        ->name('digital-pass.face.finalize');

    Route::get('/events/{event}/digital-checkin', [TicketController::class, 'digitalCheckinPage'])
        ->name('tickets.digital.checkin');

    Route::post('/events/{event}/digital-checkin/voice', [TicketController::class, 'digitalCheckinVoice'])
        ->name('tickets.digital.checkin.voice');


     // Check-in hub for an event
    Route::get('/events/{event}/check-in', [TicketController::class, 'checkinHub'])
        ->name('events.checkin');

    // Normal QR-code check-in (you may already have this route – if so, keep your existing name/path)
    Route::get('/events/{event}/check-in/qr', [TicketController::class, 'scanPage'])
        ->name('tickets.scan');

    // Digital Pass – voice check-in (you likely already have this, just keep the name consistent)
    Route::get('/events/{event}/check-in/voice', [TicketController::class, 'digitalCheckinPage'])
        ->name('tickets.digital-checkin');

    // Digital Pass – face ID (placeholder “coming soon” page)
    Route::get('/events/{event}/check-in/face', [TicketController::class, 'faceCheckinPage'])
        ->name('tickets.face-checkin');
});


/*
|--------------------------------------------------------------------------
| Public registration & avatar
|--------------------------------------------------------------------------
*/
Route::get('/events/{event}/ticket',                 [TicketLookupController::class, 'showForm'])->name('events.ticket.find');
Route::post('/events/{event}/ticket',                [TicketLookupController::class, 'sendLink'])->name('events.ticket.sendlink');
Route::get('/events/{event}/ticket/manage/{reg}',    [TicketLookupController::class, 'edit'])->name('events.ticket.edit');
Route::post('/events/{event}/ticket/manage/{reg}',   [TicketLookupController::class, 'update'])->name('events.ticket.update');

Route::get('/events/{event}/avatar',   [EventController::class, 'avatar'])->name('events.avatar');
Route::get('/events/{event}/register', [RegistrationController::class, 'create'])->name('events.register');
Route::post('/events/{event}/register',[RegistrationController::class, 'store'])->name('events.register.store');
Route::get('/events/{event}/register/result', [RegistrationController::class, 'result'])->name('events.register.result');

/* Sitemap */
Route::get('/sitemap',     [SitemapController::class, 'index']);
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

/* Public event show (after resource routes) */
Route::get('/events/{event}', [EventController::class, 'show'])
    ->where('event', '^(?!create$).+')
    ->name('events.show');


Route::post('/store-redirect', function (Illuminate\Http\Request $request) {
    session(['url.intended' => $request->redirect_to]);
    return redirect()->route('login');
})->name('store.redirect');

Route::view('/test', 'test')->name('test');


require __DIR__.'/auth.php';
