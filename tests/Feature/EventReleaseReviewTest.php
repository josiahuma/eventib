<?php
namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\SafeEventDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EventReleaseReviewTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attributes = []): Event
    {
        $event = Event::create(array_merge(['user_id' => User::factory()->create()->id, 'name' => 'Release fixture', 'ticket_cost' => 0, 'ticket_currency' => 'GBP', 'is_disabled' => false, 'digital_pass_mode' => 'off'], $attributes));
        $event->sessions()->create(['session_name' => 'Future session', 'session_date' => now()->addWeek()]);
        return $event;
    }
    public function test_sponsors_require_admin_and_routes_have_unique_names(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('admin.homepage-sponsors.index'))->assertForbidden();
        $names = [];
        foreach (app('router')->getRoutes() as $route) {
            if (!$route->getName()) continue;
            $this->assertNotContains($route->getName(), $names);
            $names[] = $route->getName();
        }
    }
    public function test_unsigned_webhook_is_rejected_when_secret_is_missing(): void
    {
        config(['services.stripe.webhook_secret' => null]);
        $event = $this->event();
        $registration = EventRegistration::create(['event_id' => $event->id, 'name' => 'Guest', 'email' => 'guest@example.test', 'status' => 'pending', 'amount' => 20, 'stripe_session_id' => 'cs_fixture']);
        $this->postJson(route('stripe.webhook'), ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_fixture', 'payment_status' => 'paid']]])->assertStatus(503);
        $this->assertSame('pending', $registration->fresh()->status);
    }
    public function test_cancel_url_cannot_overwrite_a_paid_booking(): void
    {
        $event = $this->event();
        $registration = EventRegistration::create(['event_id' => $event->id, 'name' => 'Guest', 'email' => 'guest@example.test', 'status' => 'paid', 'amount' => 20, 'stripe_session_id' => 'cs_paid']);
        $this->get(route('events.register.result', ['event' => $event, 'canceled' => 1, 'session_id' => 'cs_paid']))->assertRedirect();
        $this->assertSame('paid', $registration->fresh()->status);
    }
    public function test_paid_category_booking_does_not_accept_free_companion_edits(): void
    {
        $event = $this->event(); $user = User::factory()->create();
        $registration = EventRegistration::create(['event_id' => $event->id, 'user_id' => $user->id, 'name' => 'Guest', 'email' => $user->email, 'status' => 'paid', 'amount' => 20, 'party_children' => 0]);
        $this->actingAs($user)->post(route('my.tickets.update', $registration), ['email' => $user->email, 'party_children' => 2, 'child_ages' => [3,7]])->assertRedirect();
        $this->assertEquals(0, $registration->fresh()->party_children);
        $link = URL::temporarySignedRoute('events.ticket.edit', now()->addMinutes(30), ['event' => $event, 'reg' => $registration->id]);
        $this->get($link)->assertOk()->assertDontSee('name="party_children"', false);
    }
    public function test_paid_single_price_card_and_duplicate_warning_are_correct(): void
    {
        $event = $this->event(['ticket_cost' => 25]); $user = User::factory()->create();
        EventRegistration::create(['event_id' => $event->id, 'user_id' => $user->id, 'name' => 'Guest', 'email' => $user->email, 'status' => 'paid', 'amount' => 25]);
        $this->get('/')->assertOk()->assertSee('£25.00');
        $this->actingAs($user)->get(route('events.register', $event))->assertOk()->assertViewHas('alreadyRegistered', false);
    }
    public function test_descriptions_keep_basic_formatting_without_executable_html(): void
    {
        $safe = app(SafeEventDescription::class)->clean('<p onclick="alert(1)">Hello <strong>friends</strong><script>alert(1)</script><a href="javascript:alert(1)">unsafe</a><img src="x" onerror="alert(1)"></p>');
        $this->assertStringContainsString('<strong>friends</strong>', $safe);
        foreach (['onclick','onerror','<script','<img','javascript:'] as $unsafe) $this->assertStringNotContainsString($unsafe, $safe);
    }
    public function test_archive_hides_disabled_and_recurring_future_events(): void
    {
        $hidden = $this->event(['name' => 'Hidden archive fixture', 'is_disabled' => true]);
        $hidden->sessions()->update(['session_date' => now()->subDay()]);
        $recurring = $this->event(['name' => 'Future recurring fixture']);
        $recurring->sessions()->create(['session_name' => 'Earlier session', 'session_date' => now()->subDay()]);
        $this->get(route('events.past'))->assertOk()->assertDontSee('Hidden archive fixture')->assertDontSee('Future recurring fixture');
    }
    public function test_import_fetcher_refuses_private_and_unsafe_urls_before_network_access(): void
    {
        foreach (['http://127.0.0.1/', 'http://10.0.0.1/', 'http://100.64.0.1/', 'file:///etc/passwd', 'https://example.com:8080/'] as $url) {
            try { app(\App\Services\PublicEventFetcher::class)->get($url); $this->fail('Unsafe URL was accepted'); }
            catch (\InvalidArgumentException $error) { $this->assertNotEmpty($error->getMessage()); }
        }
    }
    public function test_signed_paid_webhook_is_idempotent(): void
    {
        Mail::fake(); config(['services.stripe.webhook_secret' => 'whsec_release_test']);
        $event = $this->event();
        $registration = EventRegistration::create(['event_id' => $event->id, 'name' => 'Guest', 'email' => 'guest@example.test', 'status' => 'pending', 'amount' => 20, 'stripe_session_id' => 'cs_signed_fixture']);
        $body = json_encode(['id' => 'evt_release_fixture', 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_signed_fixture', 'object' => 'checkout.session', 'payment_status' => 'paid', 'metadata' => ['registration_id' => (string)$registration->id, 'event_id' => (string)$event->id]]]]);
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_release_test');
        for ($i = 0; $i < 2; $i++) $this->call('POST', route('stripe.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body)->assertOk();
        $this->assertSame('paid', $registration->fresh()->status);
        Mail::assertSent(\App\Mail\RegistrationConfirmedMail::class, 1);
        Mail::assertSent(\App\Mail\NewRegistrationNotificationMail::class, 1);
    }
    public function test_pending_registration_cannot_generate_or_scan_a_free_pass(): void
    {
        $event = $this->event(); $guest = User::factory()->create();
        $reg = EventRegistration::create(['event_id' => $event->id, 'user_id' => $guest->id, 'name' => 'Guest', 'email' => $guest->email, 'status' => 'pending', 'amount' => 20, 'qr_token' => 'fixture_token']);
        $this->actingAs($guest)->get(route('tickets.pass', [$event, $reg]))->assertForbidden();
        $this->actingAs($event->user)->postJson(route('tickets.scan.validate', $event), ['payload' => 'FR|v1|'.$event->public_id.'|'.$reg->id.'|fixture_token'])->assertStatus(422);
        $this->assertNull($reg->fresh()->checked_in_at);
    }
    public function test_passed_fees_are_not_deducted_again_from_payout_availability(): void
    {
        $event = $this->event(['ticket_cost' => 50, 'fee_mode' => 'pass', 'fee_bps' => 590]);
        EventRegistration::create(['event_id' => $event->id, 'name' => 'Paid Guest', 'email' => 'paid@example.test', 'status' => 'paid', 'amount' => 50, 'quantity' => 1, 'platform_fee' => 2.95]);
        $this->actingAs($event->user)->get(route('payouts.create', $event))->assertOk()->assertViewHas('amount', 5000);
    }
}
