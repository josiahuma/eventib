<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

class OrganizerEventAlerts
{
    public function record(Event $event): void
    {
        if (!$event->organizer_id || $event->is_disabled || !$event->sessions()->where('session_date', '>', now())->exists()) return;
        DB::table('organizer_alert_subscriptions')->where('organizer_id', $event->organizer_id)
            ->where('enabled', true)->orderBy('id')->chunkById(200, function ($subscriptions) use ($event) {
                foreach ($subscriptions as $subscription) {
                    DB::table('organizer_event_alerts')->insertOrIgnore([
                        'subscription_id' => $subscription->id, 'event_id' => $event->id,
                        'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
    }
}
