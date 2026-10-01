<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Webhook;
use Throwable;

use App\Models\EventRegistration;
use App\Models\EventUnlock; // only if you use the unlock flow
use App\Mail\NewRegistrationNotificationMail;
use App\Mail\RegistrationConfirmedMail;
use App\Mail\OrganizerNewRegistrationMail;
use App\Mail\UnlockPurchasedUserMail;
use App\Mail\UnlockPurchasedAdminMail;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        if (!$secret) {
            Log::error('Stripe webhook secret is missing; refusing unsigned payment notifications.');
            return response('webhook not configured', 503);
        }
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (Throwable $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);
            return response('invalid', 400);
        }
        $type = $event->type;
        $data = $event->data->object;

        // Handle successful Checkout Sessions (sync or async)
        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $paymentStatus = $data['payment_status'] ?? null;
            if ($paymentStatus !== 'paid') {
                return response('ok', 200);
            }

            // Optional: handle your "registrants_unlock" purchase
            $purpose = $data['metadata']['purpose'] ?? 'registration';
            if ($purpose === 'registrants_unlock') {
                $eventId = (int) ($data['metadata']['event_id'] ?? 0);
                $userId  = (int) ($data['metadata']['user_id'] ?? 0);

                if (!$eventId || !$userId) return response('invalid unlock metadata', 400);
                $claimedUnlock = \Illuminate\Support\Facades\DB::transaction(function () use ($eventId, $userId, $data) {
                    $ownerEvent = \App\Models\Event::whereKey($eventId)->lockForUpdate()->first();
                    if (!$ownerEvent || $ownerEvent->user_id != $userId) return false;
                    $existing = EventUnlock::where('event_id', $eventId)->where('user_id', $userId)->first();
                    if ($existing && $existing->unlocked_at) return false;
                    EventUnlock::updateOrCreate(['event_id' => $eventId, 'user_id' => $userId], [
                        'stripe_session_id' => $data['id'] ?? null,
                        'stripe_payment_intent_id' => $data['payment_intent'] ?? null,
                        'unlocked_at' => now(),
                    ]);
                    return true;
                });
                if (!$claimedUnlock) return response('ok', 200);

                try {
                // load user
                $user = \App\Models\User::find($userId);
                if ($user) {
                    Mail::to($user->email)->queue(
                        new UnlockPurchasedUserMail($user->name, $data['amount_total'], strtoupper($data['currency']), $data['receipt_url'] ?? '')
                    );
                }

                // notify ops
                Mail::to(config('mail.ops_address'))->queue(
                    new UnlockPurchasedAdminMail($user?->email ?? 'unknown', $data['amount_total'], strtoupper($data['currency']), $data['id'] ?? '')
                );
                } catch (Throwable $error) {
                    Log::warning('Unlock mail failed after confirmation', ['error' => $error->getMessage()]);
                }
                return response('ok', 200);
            }

            // ---- Registration payment flow ----
            $registration = null;

            if (!empty($data['id'])) {
                $registration = EventRegistration::where('stripe_session_id', $data['id'])->first();
            }
            if (!$registration) {
                // A very early webhook can arrive before Checkout ID is saved. Ask Stripe to retry.
                return response('registration not ready', 503);
            }
            if ((!empty($data['metadata']['registration_id']) && (int)$data['metadata']['registration_id'] !== $registration->id)
                || (!empty($data['metadata']['event_id']) && (int)$data['metadata']['event_id'] !== $registration->event_id)) {
                Log::error('Stripe registration metadata mismatch', ['registration_id' => $registration->id]);
                return response('metadata mismatch', 400);
            }
            $claimed = EventRegistration::whereKey($registration->id)->whereIn('status', ['pending', 'canceled', 'cancelled'])
                ->update(['status' => 'paid']);
            if (!$claimed) return response('ok', 200);
            $registration->refresh();

            // Notify attendee
            try {
                Mail::to($registration->email)
                    ->send(new RegistrationConfirmedMail($registration->event, $registration));
            } catch (Throwable $e) {
                Log::warning('Webhook attendee mail failed', ['error' => $e->getMessage()]);
            }

            // Notify organizer (if user relation/email exists)
            try {
                $organizerEmail = optional($registration->event->user)->email;
                if ($organizerEmail) {
                    Mail::to($organizerEmail)
                        ->send(new NewRegistrationNotificationMail($registration->event, $registration));
                }
            } catch (Throwable $e) {
                Log::warning('Webhook organizer mail failed', ['error' => $e->getMessage()]);
            }
        }

        return response('ok', 200);
    }
}
