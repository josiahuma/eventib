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

        abort_unless($isOwner || $isAdmin, 403);

        // ── Detect paid events (categories OR legacy single price) ──
        $hasPaidCategories = $event->categories()
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->exists();
        $isPaidEvent = $hasPaidCategories || (($event->ticket_cost ?? 0) > 0);

        // Unlock gate only for FREE events (non-admin)
        if (!$isAdmin && !$isPaidEvent) {
            $isUnlocked = \App\Models\EventUnlock::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->whereNotNull('unlocked_at')
                ->exists();

            if (!$isUnlocked) {
                return redirect()->route('events.registrants.unlock', $event)
                    ->with('error', 'Unlock registrant details for this free event.');
            }
        }

        // ── Load registrations (+items for accurate pass-through calc) ──
        $event->load([
            'registrations' => function ($q) {
                $q->whereIn('status', ['paid', 'free'])->latest();
            },
            'registrations.sessions' => fn ($q) => $q->orderBy('session_date'),
            // items let us compute ticket revenue excluding pass-through fees
            'registrations.items',
        ]);

        $feeMode  = $event->fee_mode === 'pass' ? 'pass' : 'absorb'; // default absorb
        $feeRate  = 0.059; // 5.9%
        $paidRegs = $event->registrations->where('status', 'paid');

        // Attendee-paid total (whatever we stored in registrations.amount after Stripe)
        $sumMinor = (int) $paidRegs->sum(function ($r) {
            return (int) round(((float) ($r->amount ?? 0)) * 100);
        });

        // Ticket revenue (organiser’s base) independent of pass/absorb:
        // - If categories: exact from registration items (snapshot at purchase)
        // - Else fallback to legacy: quantity * current event ticket_cost (best effort)
        // - Last resort (pass mode, no items): derive base ≈ total / (1 + feeRate)
        $ticketRevenueMinor = (int) $paidRegs->sum(function ($r) use ($event, $feeMode, $feeRate) {
            // Categories path – most accurate
            if ($r->relationLoaded('items') && $r->items && $r->items->count()) {
                return (int) round(((float) $r->items->sum('line_total')) * 100);
            }

            // Legacy single-price path (best effort using current event price)
            $qty      = max(1, (int) ($r->quantity ?? 1));
            $unitNow  = (float) ($event->ticket_cost ?? 0);
            if ($unitNow > 0) {
                return (int) round($qty * $unitNow * 100);
            }

            // Last resort: if fee was passed and amount includes fee, back-solve
            // base ≈ total / (1 + feeRate)
            if ($feeMode === 'pass') {
                $totalMinor = (int) round(((float) ($r->amount ?? 0)) * 100);
                return (int) round($totalMinor / (1 + $feeRate));
            }

            // Otherwise we have nothing meaningful
            return 0;
        });

        // Commission and organiser net:
        // - PASS: organiser gets full ticket revenue (no commission deduction here)
        // - ABSORB: commission = 5.9% of attendee-paid total
        if ($feeMode === 'pass') {
            $commissionMinor = 0;
            $payoutMinor     = max(0, $ticketRevenueMinor);
            $earnedMinor     = $payoutMinor; // for the “Amount earned” tile
        } else {
            $commissionMinor = intdiv($sumMinor * 590, 10000); // 5.90%
            $payoutMinor     = max(0, $sumMinor - $commissionMinor);
            $earnedMinor     = $payoutMinor;
        }

        // Payout availability uses the event owner for deductions
        $ownerId = $event->user_id;
        $deductedMinor = (int) \App\Models\EventPayout::where('event_id', $event->id)
            ->where('user_id', $ownerId)
            ->whereNotIn('status', ['failed', 'cancelled'])
            ->sum('amount');

        $availableMinor = max(0, $payoutMinor - $deductedMinor);

        $hasProcessingPayout = \App\Models\EventPayout::where('event_id', $event->id)
            ->where('user_id', $ownerId)
            ->where('status', 'processing')
            ->exists();

        $currency = strtoupper($event->ticket_currency ?? 'GBP');
        $symbols  = ['GBP'=>'£','USD'=>'$','EUR'=>'€','NGN'=>'₦','KES'=>'KSh','GHS'=>'₵','ZAR'=>'R','CAD'=>'$','AUD'=>'$'];
        $symbol   = $symbols[$currency] ?? ($currency.' ');

        return view('registrants.index', [
            'event'               => $event,
            'isPaidEvent'         => $isPaidEvent,

            // Attendee total & organiser figures
            'sumMinor'            => $sumMinor,          // what attendees paid (incl pass-through if any)
            'commissionMinor'     => $commissionMinor,   // 0 if pass
            'payoutMinor'         => $payoutMinor,       // organiser net before prior payouts
            'earnedMinor'         => $earnedMinor,       // same as payoutMinor (tile display)
            'availableMinor'      => $availableMinor,

            'currency'            => $currency,
            'symbol'              => $symbol,
            'hasProcessingPayout' => $hasProcessingPayout,
            'isAdmin'             => $isAdmin,
            'isOwner'             => $isOwner,
            'feeMode'             => $feeMode,           // pass to Blade for messaging
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

        // Paid events don’t need unlocking (use same paid detection as index)
        $hasPaidCategories = $event->categories()
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->exists();
        if ($hasPaidCategories || (($event->ticket_cost ?? 0) > 0)) {
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

        // Paid events don’t need unlocking (use same paid detection as index)
        $hasPaidCategories = $event->categories()
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->exists();
        if ($hasPaidCategories || (($event->ticket_cost ?? 0) > 0)) {
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
