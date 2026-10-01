<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::create(['user_id' => User::factory()->create()->id, 'name' => 'Discovery test event', 'is_disabled' => false]);
    }

    public function test_guests_cannot_save_or_read_plans(): void
    {
        $this->get(route('discovery.saved'))->assertRedirect(route('login'));
        $this->post(route('discovery.save', $this->event()), ['saved' => 1])->assertRedirect(route('login'));
        $this->assertDatabaseCount('event_saves', 0);
    }

    public function test_saving_is_idempotent_and_removal_is_explicit(): void
    {
        $user = User::factory()->create();
        $event = $this->event();
        $this->actingAs($user);
        for ($i = 0; $i < 2; $i++) $this->post(route('discovery.save', $event), ['saved' => 1])->assertRedirect();
        $this->assertDatabaseCount('event_saves', 1);
        $this->assertDatabaseHas('event_saves', ['user_id' => $user->id, 'event_id' => $event->id]);
        $this->post(route('discovery.save', $event), ['saved' => 0])->assertRedirect();
        $this->assertDatabaseCount('event_saves', 0);
    }

    public function test_saved_events_are_private_to_each_account(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $event = $this->event();
        $this->actingAs($alice)->post(route('discovery.save', $event), ['saved' => 1]);
        $this->get(route('discovery.saved'))->assertOk()->assertSee($event->name);
        $this->actingAs($bob)->get(route('discovery.saved'))->assertOk()->assertDontSee($event->name);
    }

    public function test_disabled_events_cannot_be_saved_or_shared(): void
    {
        $event = $this->event();
        $event->update(['is_disabled' => true]);
        $this->get(route('discovery.share', $event))->assertNotFound();
        $this->actingAs(User::factory()->create())->post(route('discovery.save', $event), ['saved' => 1])->assertNotFound();
    }

    public function test_save_state_is_validated(): void
    {
        $this->actingAs(User::factory()->create())->post(route('discovery.save', $this->event()), ['saved' => 'invalid'])->assertSessionHasErrors('saved');
        $this->assertDatabaseCount('event_saves', 0);
    }

    public function test_share_studio_is_public_for_enabled_events(): void
    {
        $this->get(route('discovery.share', $this->event()))->assertOk()->assertSee('THE SOCIAL INVITE STUDIO');
    }
    public function test_home_category_filters_are_distinct_and_disabled_events_are_hidden(): void
    {
        $music = $this->event(); $music->update(['name' => 'Music selection fixture', 'category' => 'Music']);
        $music->sessions()->create(['session_name' => 'Live', 'session_date' => now()->addDay()]);
        $sports = $this->event(); $sports->update(['name' => 'Sports selection fixture', 'category' => 'Sports']);
        $sports->sessions()->create(['session_name' => 'Game', 'session_date' => now()->addDay()]);
        $disabled = $this->event(); $disabled->update(['name' => 'Disabled selection fixture', 'category' => 'Music', 'is_disabled' => true]);
        $disabled->sessions()->create(['session_name' => 'Hidden', 'session_date' => now()->addDay()]);
        $this->get('/?category=Music')->assertOk()->assertSee('Music selection fixture')->assertDontSee('Sports selection fixture')->assertDontSee('Disabled selection fixture');
        $this->get('/?category=Sports')->assertOk()->assertSee('Sports selection fixture')->assertDontSee('Music selection fixture');
        $this->get(route('events.find', ['category' => 'Music']))->assertOk()->assertSee('Music events')->assertSee('Music selection fixture')->assertDontSee('Sports selection fixture');
    }
}
