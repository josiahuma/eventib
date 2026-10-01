<?php

namespace App\Console\Commands;

use App\Mail\OrganizerEventAlertMail;
use App\Models\Event;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendOrganizerEventAlerts extends Command
{
    protected $signature = 'eventib:send-organizer-alerts {--limit=20 : Maximum messages per run}';
    protected $description = 'Deliver consented organiser event alerts from the outbox';

    public function handle(): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $sent = 0;
        for ($i = 0; $i < $limit; $i++) {
            // Claim one row atomically; simultaneous processes cannot send the same pending row.
            $alert = DB::transaction(function () {
                $row = DB::table('organizer_event_alerts')->where('status', 'pending')->orderBy('id')->lockForUpdate()->first();
                if ($row) DB::table('organizer_event_alerts')->where('id', $row->id)->update(['status' => 'processing', 'claimed_at' => now(), 'updated_at' => now()]);
                return $row;
            });
            if (!$alert) break;
            try {
                $subscription = DB::table('organizer_alert_subscriptions')->find($alert->subscription_id);
                $event = Event::with(['organizer', 'sessions' => fn ($q) => $q->where('session_date', '>', now())->orderBy('session_date')])->find($alert->event_id);
                $user = $subscription ? User::find($subscription->user_id) : null;
                if (!$subscription || !$subscription->enabled || !$event || $event->is_disabled || $event->sessions->isEmpty()
                    || $event->organizer_id != $subscription->organizer_id || !$user || !$user->email || $user->is_disabled || ($subscription->enabled_at && \Illuminate\Support\Carbon::parse($subscription->enabled_at)->gt($alert->created_at))) {
                    DB::table('organizer_event_alerts')->where('id', $alert->id)->update(['status' => 'skipped', 'updated_at' => now()]);
                    continue;
                }
                $url = URL::signedRoute('organizer-alerts.unsubscribe', ['subscription' => $subscription->id]);
                Mail::to($user->email)->send(new OrganizerEventAlertMail($event, $url));
                DB::table('organizer_event_alerts')->where('id', $alert->id)->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                DB::table('organizer_event_alerts')->where('id', $alert->id)->update(['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 1000), 'updated_at' => now()]);
                report($e);
                $this->warn('An alert failed; review delivery records before retrying.');
            }
        }
        $this->info("Delivered {$sent} organiser alerts.");
        return self::SUCCESS;
    }
}
