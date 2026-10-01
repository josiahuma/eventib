<?php

namespace Tests\Feature;

use App\Mail\OrganizerEventAlertMail;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Services\OrganizerEventAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EventGrowthTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(): Event
    {
        $owner = User::factory()->create();
        $organizer = Organizer::create(['user_id' => $owner->id, 'name' => 'Community Club', 'slug' => 'club-'.$owner->id]);
        $event = Event::create(['user_id' => $owner->id, 'organizer_id' => $organizer->id, 'name' => 'A community plan', 'is_disabled' => false, 'ticket_cost' => 0, 'digital_pass_mode' => 'off']);
        $event->sessions()->create(['session_name' => 'Afternoon', 'session_date' => now()->addWeek()]);
        return $event;
    }
    private function subscribe(User $user, Organizer $organizer): int
    {
        $this->actingAs($user)->post(route('organizer-alerts.update', $organizer), ['enabled' => 1])->assertRedirect();
        return (int) DB::table('organizer_alert_subscriptions')->where('user_id', $user->id)->where('organizer_id', $organizer->id)->value('id');
    }
    public function test_create_persists_faqs_and_records_consented_alerts(): void
    {
        Mail::fake(); \Illuminate\Support\Facades\Storage::fake('public');
        $existing = $this->makeEvent(); $follower = User::factory()->create();
        $this->subscribe($follower, $existing->organizer);
        $banner = \Illuminate\Http\UploadedFile::fake()->createWithContent('banner.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4MC0FAARkAes5HMjvAAAAAElFTkSuQmCC'));
        $this->actingAs($existing->user)->post(route('events.store'), [
            'name' => 'New release', 'organizer_id' => $existing->organizer_id,
            'banner' => $banner, 'ticket_cost' => 0, 'ticket_currency' => 'GBP',
            'digital_pass_mode' => 'off', 'digital_pass_methods' => 'both',
            'sessions' => [['name' => 'First session', 'date' => now()->addWeek()->format('Y-m-d'), 'time' => '14:00']],
            'faqs' => [['question' => 'Parking?', 'answer' => 'Parking is available nearby.']],
        ])->assertRedirect(route('events.manage'));
        $created = Event::where('name', 'New release')->firstOrFail();
        $this->assertSame('Parking?', $created->faqs[0]['question']);
        $this->assertDatabaseHas('organizer_event_alerts', ['event_id' => $created->id, 'status' => 'pending']);
        Mail::assertNotSent(OrganizerEventAlertMail::class);
    }
    public function test_repeated_opt_in_preserves_pending_alerts(): void
    {
        Mail::fake(); $event = $this->makeEvent(); $user = User::factory()->create();
        $this->subscribe($user, $event->organizer); app(OrganizerEventAlerts::class)->record($event);
        $this->travel(2)->minutes();
        $this->post(route('organizer-alerts.update', $event->organizer), ['enabled' => 1])->assertRedirect();
        $this->artisan('eventib:send-organizer-alerts')->assertSuccessful();
        Mail::assertSent(OrganizerEventAlertMail::class, 1);
    }
    public function test_calendar_cannot_export_another_events_session(): void
    {
        $event = $this->makeEvent(); $other = $this->makeEvent();
        $this->get(route('events.calendar', [$event, $other->sessions()->first()]))->assertNotFound();
        $calendar = $this->get(route('events.calendar', [$event, $event->sessions()->first()]));
        $calendar->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->assertSee('BEGIN:VCALENDAR')->assertSee('DTSTART:')->assertSee('END:VEVENT');
        foreach (explode("\r\n", $calendar->getContent()) as $line) $this->assertLessThanOrEqual(75, strlen($line));
    }
    public function test_calendar_escapes_multiline_text_and_folds_utf8(): void
    {
        $event = $this->makeEvent(); $event->update(['name' => str_repeat('é', 80), 'location' => "Hall, Room; 2\nSecond line"]);
        $response = $this->get(route('events.calendar', [$event, $event->sessions()->first()]))->assertOk();
        $response->assertSee('Hall\\, Room\\; 2\\nSecond line');
        foreach (explode("\r\n", $response->getContent()) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'));
        }
    }
    public function test_booking_prefill_accepts_only_this_events_future_sessions(): void
    {
        $event = $this->makeEvent(); $other = $this->makeEvent();
        $session = $event->sessions()->first();
        $this->get(route('events.register', ['event' => $event, 'session_ids' => [$session->id], 'party_adults' => 2]))->assertOk()->assertViewHas('bookingSelection', fn ($s) => $s['party_adults'] == 2);
        $this->getJson(route('events.register', ['event' => $event, 'session_ids' => [$other->sessions()->first()->id]]))->assertUnprocessable();
        $session->update(['session_date' => now()->subDay()]);
        $this->getJson(route('events.register', ['event' => $event, 'session_ids' => [$session->id]]))->assertUnprocessable();
    }
    public function test_foreign_category_and_disabled_event_are_rejected(): void
    {
        $event = $this->makeEvent(); $other = $this->makeEvent();
        $cat = $other->categories()->create(['name' => 'Other ticket', 'price' => 10, 'is_active' => true]);
        $this->get(route('events.register', ['event' => $event, 'categories' => [$cat->id => 1]]))->assertUnprocessable();
        $event->update(['is_disabled' => true]);
        $this->get(route('events.show', $event))->assertNotFound();
        $this->post(route('events.register.store', $event), [])->assertNotFound();
    }
    public function test_following_alone_does_not_create_email_alerts(): void
    {
        $event = $this->makeEvent(); $user = User::factory()->create();
        $user->followedOrganizers()->attach($event->organizer_id);
        app(OrganizerEventAlerts::class)->record($event);
        $this->assertDatabaseCount('organizer_event_alerts', 0);
    }
    public function test_outbox_is_idempotent_and_successful_delivery_is_not_repeated(): void
    {
        Mail::fake(); $event = $this->makeEvent(); $user = User::factory()->create();
        $this->subscribe($user, $event->organizer);
        app(OrganizerEventAlerts::class)->record($event); app(OrganizerEventAlerts::class)->record($event);
        $this->assertDatabaseCount('organizer_event_alerts', 1);
        $this->artisan('eventib:send-organizer-alerts')->assertSuccessful();
        $this->artisan('eventib:send-organizer-alerts')->assertSuccessful();
        Mail::assertSent(OrganizerEventAlertMail::class, 1);
        $this->assertDatabaseHas('organizer_event_alerts', ['status' => 'sent']);
    }
    public function test_signed_unsubscribe_requires_confirmation_and_cancels_pending_mail(): void
    {
        Mail::fake(); $event = $this->makeEvent(); $user = User::factory()->create();
        $id = $this->subscribe($user, $event->organizer); app(OrganizerEventAlerts::class)->record($event);
        $url = URL::signedRoute('organizer-alerts.unsubscribe', ['subscription' => $id]);
        $this->get($url)->assertOk(); $this->assertDatabaseHas('organizer_alert_subscriptions', ['id' => $id, 'enabled' => true]);
        $this->post($url)->assertOk();
        $this->assertDatabaseHas('organizer_alert_subscriptions', ['id' => $id, 'enabled' => false]);
        $this->artisan('eventib:send-organizer-alerts')->assertSuccessful(); Mail::assertNothingSent();
        $this->get(route('organizer-alerts.unsubscribe', $id))->assertForbidden();
    }
    public function test_unfollowing_stops_alerts_and_settings_are_private(): void
    {
        Mail::fake(); $event = $this->makeEvent(); $alice = User::factory()->create(); $bob = User::factory()->create();
        $id = $this->subscribe($alice, $event->organizer); app(OrganizerEventAlerts::class)->record($event);
        $this->post(route('organizers.unfollow', $event->organizer))->assertRedirect();
        $this->assertDatabaseHas('organizer_alert_subscriptions', ['id' => $id, 'enabled' => false]);
        $this->actingAs($bob)->get(route('organizer-alerts.index'))->assertOk()->assertDontSee('Community Club');
        $this->artisan('eventib:send-organizer-alerts')->assertSuccessful(); Mail::assertNothingSent();
    }
    public function test_disabled_events_and_uncertain_deliveries_are_not_sent(): void
    {
        Mail::fake(); $event = $this->makeEvent(); $user = User::factory()->create();
        $this->subscribe($user, $event->organizer); app(OrganizerEventAlerts::class)->record($event);
        $event->update(['is_disabled' => true]);
        $this->artisan('eventib:send-organizer-alerts')->assertSuccessful(); Mail::assertNothingSent();
        $this->assertDatabaseHas('organizer_event_alerts', ['status' => 'skipped']);
        DB::table('organizer_event_alerts')->update(['status' => 'processing']);
        $event->update(['is_disabled' => false]);
        $this->artisan('eventib:send-organizer-alerts')->assertSuccessful(); Mail::assertNothingSent();
    }
    public function test_faqs_are_escaped_and_nonowners_cannot_edit_them(): void
    {
        $event = $this->makeEvent();
        $event->update(['faqs' => [['question' => 'Parking?', 'answer' => '<script>bad()</script>']]]);
        $this->get(route('events.show', $event))->assertOk()->assertSee('Parking?')->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false);
        $this->actingAs(User::factory()->create())->put(route('events.update', $event), ['faqs' => []])->assertForbidden();
    }
    public function test_children_require_one_valid_age_each_and_ages_are_saved(): void
    {
        Mail::fake(); $event = $this->makeEvent();
        $payload = ['name' => 'Family Guest', 'email' => 'family@example.test', 'session_ids' => [$event->sessions()->first()->id], 'party_children' => 2, 'party_adults' => 0];
        $this->post(route('events.register.store', $event), $payload + ['child_ages' => [7]])->assertSessionHasErrors('child_ages');
        $this->post(route('events.register.store', $event), $payload + ['child_ages' => [0,18]])->assertSessionHasErrors('child_ages.1');
        $this->post(route('events.register.store', $event), $payload + ['child_ages' => [0,17]])->assertRedirect();
        $registration = \App\Models\EventRegistration::where('event_id', $event->id)->where('email', 'family@example.test')->firstOrFail();
        $this->assertEquals([0,17], $registration->child_ages);
        $this->assertEquals(2, $registration->party_children);
    }
}
