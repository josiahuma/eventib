<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventUnlock;
use App\Models\EventPayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegistrantsController extends Controller
{
    // £9.99 to unlock free-event registrants (minor units in GBP)
    private int $unlockAmount = 999;
    private string $currency = 'gbp';

    public function index(Event $event)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $isAdmin = (bool) ($user->is_admin ?? false);
        $isOwner = $event->user_id === $user->id;

        // ✅ Access control: owner OR admin
        abort_unless($isOwner || $isAdmin, 403);

        $isPaidEvent = ($event->ticket_cost ?? 0) > 0;

        // ✅ Unlock gate only applies to non-admins on FREE events
        if (!$isAdmin && !$isPaidEvent) {
            $isUnlocked = EventUnlock::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->whereNotNull('unlocked_at')
                ->exists();

            if (!$isUnlocked) {
                return redirect()->route('events.registrants.unlock', $event)
                    ->with('error', 'Unlock registrant details for this free event.');
            }
        }

        // Load registrations:
        //  - For paid events: only include rows with status=paid
        //  - For free events: include all (as per original behavior)
        $event->load([
            'registrations' => function ($q) use ($isPaidEvent) {
                if ($isPaidEvent) {
                    $q->where('status', 'paid');
                }
            },
            'registrations.sessions' => fn ($q) => $q->orderBy('session_date'),
        ]);

        // Sum gross using registration->amount (assumed major units)
        $sumMinor = $event->registrations->sum(function ($r) {
            return (int) round(((float) ($r->amount ?? 0)) * 100);
        });

        // 9.99% commission
        $commissionMinor = intdiv($sumMinor * 999, 10000);

        // Net currently earned
        $payoutMinor = max(0, $sumMinor - $commissionMinor);

        // ✅ When admin is viewing, compute payouts using the EVENT OWNER id
        $ownerId = $event->user_id;

        // Subtract all payouts that are not failed/cancelled
        $deductedMinor = (int) EventPayout::where('event_id', $event->id)
            ->where('user_id', $ownerId)
            ->whereNotIn('status', ['failed', 'cancelled'])
            ->sum('amount');

        // What can be requested right now
        $availableMinor = max(0, $payoutMinor - $deductedMinor);

        // Show a “Processing” chip for context
        $hasProcessingPayout = EventPayout::where('event_id', $event->id)
            ->where('user_id', $ownerId)
            ->where('status', 'processing')
            ->exists();

        $currency = strtoupper($event->ticket_currency ?? 'GBP');
        $symbols  = ['GBP'=>'£','USD'=>'$','EUR'=>'€','NGN'=>'₦','KES'=>'KSh','GHS'=>'₵','ZAR'=>'R','CAD'=>'$','AUD'=>'$'];
        $symbol   = $symbols[$currency] ?? ($currency.' ');

        return view('registrants.index', [
            'event'               => $event,
            'isPaidEvent'         => $isPaidEvent,
            'sumMinor'            => $sumMinor,
            'commissionMinor'     => $commissionMinor,
            'payoutMinor'         => $payoutMinor,
            'availableMinor'      => $availableMinor,
            'currency'            => $currency,
            'symbol'              => $symbol,
            'hasProcessingPayout' => $hasProcessingPayout,
            // Optional: pass flags in case your Blade wants to show admin-only affordances
            'isAdmin'             => $isAdmin,
            'isOwner'             => $isOwner,
        ]);
    }

    public function unlock(Event $event)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $isAdmin = (bool) ($user->is_admin ?? false);
        $isOwner = $event->user_id === $user->id;

        // ✅ Admins never need to unlock
        if ($isAdmin) {
            return redirect()->route('events.registrants', $event)
                ->with('success', 'Admins can view registrants without unlocking.');
        }

        // Only the owner can unlock
        abort_unless($isOwner, 403);

        // Paid events don’t need unlocking
        if (($event->ticket_cost ?? 0) > 0) {
            return redirect()->route('events.registrants', $event);
        }

        $already = EventUnlock::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereNotNull('unlocked_at')
            ->first();

        if ($already) {
            return redirect()->route('events.registrants', $event)
                ->with('success', 'Registrants already unlocked.');
        }

        return view('registrants.unlock', [
            'event'    => $event,
            'amount'   => $this->unlockAmount,   // minor units
            'currency' => strtoupper($this->currency),
        ]);
    }

    public function checkout(Request $request, Event $event)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $isAdmin = (bool) ($user->is_admin ?? false);
        $isOwner = $event->user_id === $user->id;

        // ✅ Admins never unlock / pay
        if ($isAdmin) {
            return redirect()->route('events.registrants', $event);
        }

        // Only the owner can unlock
        abort_unless($isOwner, 403);

        if (($event->ticket_cost ?? 0) > 0) {
            return redirect()->route('events.registrants', $event);
        }

        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $this->currency, // GBP for unlocks
                    'product_data' => [
                        'name'        => 'Unlock registrant details',
                        'description' => 'One-time unlock for event: ' . $event->name,
                    ],
                    'unit_amount' => $this->unlockAmount, // minor units
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'purpose'  => 'registrants_unlock',
                'event_id' => (string) $event->id,
                'user_id'  => (string) $user->id,
            ],
            'success_url' => route('events.registrants.unlock.success', $event) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => route('events.registrants.unlock', $event),
        ]);

        EventUnlock::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            [
                'stripe_session_id' => $session->id,
                'amount'            => $this->unlockAmount, // minor units
                'currency'          => $this->currency,
            ]
        );

        return redirect()->away($session->url);
    }

    public function success(Request $request, Event $event)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $isAdmin = (bool) ($user->is_admin ?? false);
        $isOwner = $event->user_id === $user->id;

        // ✅ Admins don’t need success flow
        if ($isAdmin) {
            return redirect()->route('events.registrants', $event);
        }

        // Only owner completes unlock
        abort_unless($isOwner, 403);

        $sessionId = $request->query('session_id');
        if (!$sessionId) {
            return redirect()->route('events.registrants.unlock', $event)->with('error', 'Missing session id.');
        }

        $stripe  = new \Stripe\StripeClient(config('services.stripe.secret'));
        $session = $stripe->checkout->sessions->retrieve($sessionId, []);

        if (!$session || $session->payment_status !== 'paid') {
            return redirect()->route('events.registrants.unlock', $event)->with('error', 'Payment not completed.');
        }

        // Persist the actual paid amount/currency from Stripe (minor units)
        $paidMinor = (int) ($session->amount_total ?? $this->unlockAmount);
        $paidCurr  = strtolower((string) ($session->currency ?? $this->currency));

        EventUnlock::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            [
                'stripe_session_id'        => $session->id,
                'stripe_payment_intent_id' => $session->payment_intent ?? null,
                'amount'                   => $paidMinor, // minor units
                'currency'                 => $paidCurr,
                'unlocked_at'              => now(),
            ]
        );

        return redirect()->route('events.registrants', $event)->with('success', 'Registrants unlocked.');
    }

    /** Normalize currency code to 3-letter lowercase (defaults to gbp). */
    private function normalizeCurrency(?string $code): string
    {
        $c = strtolower(trim((string) $code));
        return preg_match('/^[a-z]{3}$/', $c) ? $c : 'gbp';
    }

    /** Number of decimal places for a currency (Stripe minor unit exponent). */
    private function currencyExponent(string $currency): int
    {
        $c = strtolower($currency);

        // 0-decimal on Stripe
        $zero = [
            'bif','clp','djf','gnf','jpy','kmf','krw','mga',
            'pyg','rwf','ugx','vnd','vuv','xaf','xof','xpf',
        ];
        if (in_array($c, $zero, true)) return 0;

        // 3-decimal currencies
        $three = ['bhd','jod','kwd','omr','tnd'];
        if (in_array($c, $three, true)) return 3;

        return 2;
    }
}
