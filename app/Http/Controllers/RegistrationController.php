<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use App\Mail\NewRegistrationNotificationMail;
use App\Mail\RegistrationConfirmedMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Stripe\StripeClient;


class RegistrationController extends Controller
{
    public function create(Event $event)
    {
        // Load relations used by the form
        $event->load([
            'sessions',
            'categories' => fn ($q) => $q->where('is_active', true)->orderBy('sort')->orderBy('id'),
        ]);

        // Determine pricing type from active categories
        $isPaidEvent = $event->categories()->exists();

        // Only flag duplicates for FREE events (not paid categories)
        $alreadyRegistered = false;

        if (! $isPaidEvent && Auth::check()) {
            $alreadyRegistered = EventRegistration::query()
                ->where('event_id', $event->id)
                ->whereNotIn('status', ['canceled', 'cancelled', 'failed'])
                ->where(function ($q) {
                    $q->where('user_id', Auth::id());
                    if (Auth::user()?->email) {
                        $q->orWhere('email', Auth::user()->email);
                    }
                })
                ->exists();
        }



        return view('events.register', [
            'event'             => $event,
            'isPaidEvent'       => $isPaidEvent,
            'alreadyRegistered' => $alreadyRegistered,
        ]);
    }

    public function store(Request $request, Event $event)
    {
        $event->load([
            'sessions',
            'categories' => fn ($q) => $q->where('is_active', true)->orderBy('sort')->orderBy('id'),
        ]);

        $baseRules = [
            'name'          => 'required|string|max:255',
            'email'         => 'required|email',
            'mobile'        => 'nullable|string|max:30',
            'session_ids'   => 'required|array|min:1',
            'session_ids.*' => 'integer|exists:event_sessions,id',
        ];

        $hasCategories = $event->categories->count() > 0;
        $isSinglePaid  = !$hasCategories && (($event->ticket_cost ?? 0) > 0);

        $extraRules = $hasCategories
            ? ['categories' => 'required|array'] // categories[catId] => qty
            : ($isSinglePaid
                ? ['quantity' => 'required|integer|min:1|max:10']
                : [
                    'party_adults'   => 'nullable|integer|min:0|max:20',
                    'party_children' => 'nullable|integer|min:0|max:20',
                ]);

        $validated = $request->validate($baseRules + $extraRules);

        // Validate sessions belong to this event
        $validSessionIds = $event->sessions()
            ->whereIn('id', $validated['session_ids'])
            ->pluck('id')->all();

        if (!$validSessionIds) {
            return back()
                ->withErrors(['session_ids' => 'Please select at least one valid session for this event.'])
                ->withInput();
        }

        $currency = strtolower($event->ticket_currency ?? 'gbp');

        /* ============================
         |   CATEGORIES PRICING FLOW  |
         ============================ */
        if ($hasCategories) {
            // Build selected line items
            $selected = collect($request->input('categories', []))
                ->map(fn ($qty, $id) => ['id' => (int) $id, 'qty' => max(0, (int) $qty)])
                ->filter(fn ($r) => $r['qty'] > 0);

            $lines = collect();
            foreach ($selected as $s) {
                $cat = $event->categories->firstWhere('id', $s['id']);
                if (!$cat) continue;

                $lines->push([
                    'category'      => $cat,
                    'snapshot_name' => $cat->name,
                    'unit_price'    => (float) $cat->price,
                    'quantity'      => $s['qty'],
                    'line_total'    => round(((float) $cat->price) * $s['qty'], 2),
                ]);
            }

            if ($lines->isEmpty()) {
                return back()
                    ->withErrors(['categories' => 'Please select at least one ticket.'])
                    ->withInput();
            }

            $totalQty   = (int) $lines->sum('quantity');
            $totalMajor = (float) $lines->sum('line_total');
            $subtotalMajor = (float) $totalMajor; // for future use if we add fees/discounts
            $feeMajor      = 0.0; // for future use if we add fees/discounts

            if ($event->feeMode() === 'pass') {
                $feeMajor = round($subtotalMajor * $event->feeRate(), 2); //per transaction fee
            }

            // ❗ Duplicate gate ONLY if this is effectively a FREE registration (total = 0)
            if ($totalMajor <= 0) {
                $existsFree = EventRegistration::where('event_id', $event->id)
                    ->whereNotIn('status', ['canceled', 'cancelled', 'failed'])
                    ->where(function ($q) use ($validated) {
                        if (Auth::check()) $q->orWhere('user_id', Auth::id());
                        $q->orWhere('email', $validated['email']);
                    })
                    ->exists();

                if ($existsFree) {
                    return back()
                        ->withErrors(['email' => 'You are already registered for this free event.'])
                        ->withInput();
                }
            }

            // Reuse pending draft if any; otherwise create a new one
            $registration = EventRegistration::where('event_id', $event->id)
                ->where('status', 'pending')
                ->where(function ($q) use ($validated) {
                    if (Auth::check()) $q->orWhere('user_id', Auth::id());
                    $q->orWhere('email', $validated['email']);
                })
                ->latest('id')->first();

            if ($registration) {
                $registration->fill([
                    'name'     => $validated['name'],
                    'email'    => $validated['email'],
                    'mobile'   => $validated['mobile'] ?? null,
                    'status'   => $subtotalMajor > 0 ? 'pending' : 'free',
                    'amount'   => $subtotalMajor, // organizer revenue (ticket only)
                    'currency' => $currency,
                    'quantity' => $totalQty,
                    'platform_fee' => $feeMajor, // platform fee (if passed on)
                ])->save();
                $registration->items()->delete();
            } else {
                $registration = EventRegistration::create([
                    'event_id' => $event->id,
                    'user_id'  => Auth::id(),
                    'name'     => $validated['name'],
                    'email'    => $validated['email'],
                    'mobile'   => $validated['mobile'] ?? null,
                    'status'   => $subtotalMajor > 0 ? 'pending' : 'free',
                    'amount'   => $subtotalMajor,
                    'currency' => $currency,
                    'quantity' => $totalQty,
                    'platform_fee' => $feeMajor, // platform fee (if passed on)
                ]);
            }

            foreach ($lines as $ln) {
                EventRegistrationItem::create([
                    'event_registration_id'    => $registration->id,
                    'event_ticket_category_id' => $ln['category']->id,
                    'snapshot_name'            => $ln['snapshot_name'],
                    'unit_price'               => $ln['unit_price'],
                    'quantity'                 => $ln['quantity'],
                    'line_total'               => $ln['line_total'],
                ]);
            }

            $registration->sessions()->sync($validSessionIds);

            // FREE categories selection -> confirm immediately
            if ($totalMajor <= 0) {
                if ($event->user?->email) {
                    Mail::to($event->user->email)->send(new NewRegistrationNotificationMail($event, $registration));
                }

                if ($registration->status === 'free' && empty($registration->qr_token)) {
                    $registration->qr_token = \Illuminate\Support\Str::random(40);
                    $registration->save();
                }

                Mail::to($registration->email)->send(new RegistrationConfirmedMail($event, $registration));
                return redirect()->to(route('events.register.result', ['event' => $event, 'registered' => 1]));
            }

            // PAID categories -> Stripe
            $stripe = new StripeClient(config('services.stripe.secret'));
            $lineItems = $lines->map(function ($ln) use ($currency) {
                return [
                    'price_data' => [
                        'currency'     => $currency,
                        'product_data' => ['name' => $ln['snapshot_name']],
                        'unit_amount'  => (int) round($ln['unit_price'] * 100),
                    ],
                    'quantity' => $ln['quantity'],
                ];
            })->values()->all();

            if ($feeMajor > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency'     => $currency,
                        'product_data' => ['name' => 'Processing fee'],
                        'unit_amount'  => (int) round($feeMajor * 100),
                    ],
                    'quantity' => 1,
                ];
            }

            $session = $stripe->checkout->sessions->create([
                'mode'                  => 'payment',
                'payment_method_types'  => ['card'],
                'line_items'            => $lineItems,
                'success_url'           => route('events.register.result', ['event' => $event, 'paid' => 1]).'&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'            => route('events.register.result', ['event' => $event, 'canceled' => 1]).'&session_id={CHECKOUT_SESSION_ID}',
                'metadata'              => [
                    'purpose'         => 'event_registration',
                    'event_id'        => (string) $event->id,
                    'registration_id' => (string) $registration->id,
                    'session_ids'     => implode(',', $validSessionIds),
                    'email'           => $validated['email'],
                    'name'            => $validated['name'],
                    'user_id'         => (string) (Auth::id() ?? ''),
                    'quantity'        => (string) $totalQty,
                    'subtotal_major'  => (string) $subtotalMajor,
                    'platform_fee'    => (string) $feeMajor,
                    'fee_mode'        => $event->feeMode(),
                ],
            ]);

            $registration->update(['stripe_session_id' => $session->id]);
            return redirect()->away($session->url);
        }

        /* ==============================
         |   LEGACY SINGLE-PRICE FLOW   |
         |   (paid can re-order; free = one) 
         ============================== */
        $isPaid = ($event->ticket_cost ?? 0) > 0;

        $request->validate(
            $isPaid
                ? ['quantity' => 'required|integer|min:1|max:10']
                : [
                    'party_adults'   => 'nullable|integer|min:0|max:20',
                    'party_children' => 'nullable|integer|min:0|max:20',
                ]
        );

        // Duplicate gate ONLY for free single-price events
        if (! $isPaid) {
            $existsFree = EventRegistration::where('event_id', $event->id)
                ->whereNotIn('status', ['canceled', 'cancelled', 'failed'])
                ->where(function ($q) use ($request) {
                    if (Auth::check()) $q->orWhere('user_id', Auth::id());
                    $q->orWhere('email', $request->input('email'));
                })
                ->exists();

            if ($existsFree) {
                return back()
                    ->withErrors(['email' => 'You are already registered for this free event.'])
                    ->withInput();
            }
        }

        $subtotalMajor = $isPaid ? round(($event->ticket_cost ?? 0) * $quantity, 2) : 0.0;
        $feeMajor      = 0.0;
        if ($isPaid && $event->feeMode() === 'pass') {
            $feeMajor = round($subtotalMajor * $event->feeRate(), 2); //per transaction fee
        }

        $quantity      = $isPaid ? (int) $request->input('quantity') : 1;
        $partyAdults   = $isPaid ? 0 : (int) ($request->input('party_adults') ?? 0);
        $partyChildren = $isPaid ? 0 : (int) ($request->input('party_children') ?? 0);

        $registration = EventRegistration::where('event_id', $event->id)
            ->where('status', 'pending')
            ->where(function ($q) use ($request) {
                if (Auth::check()) $q->orWhere('user_id', Auth::id());
                $q->orWhere('email', $request->input('email'));
            })
            ->latest('id')->first();

        if ($registration) {
            $registration->fill([
                'name'           => $request->input('name'),
                'email'          => $request->input('email'),
                'mobile'         => $request->input('mobile') ?? null,
                'quantity'       => $quantity,
                'party_adults'   => $partyAdults,
                'party_children' => $partyChildren,
                'currency'       => $currency,
                'amount'         => $subtotalMajor,
                'platform_fee'   => $feeMajor, // platform fee (if passed on)
                'status'         => $isPaid ? 'pending' : 'free',
            ])->save();
        } else {
            $registration = EventRegistration::create([
                'event_id'       => $event->id,
                'user_id'        => Auth::id(),
                'name'           => $request->input('name'),
                'email'          => $request->input('email'),
                'mobile'         => $request->input('mobile') ?? null,
                'status'         => $isPaid ? 'pending' : 'free',
                'amount'         => $subtotalMajor,
                'platform_fee'   => $feeMajor, // platform fee (if passed on)
                'currency'       => $currency,
                'quantity'       => $quantity,
                'party_adults'   => $partyAdults,
                'party_children' => $partyChildren,
            ]);
        }

        $registration->sessions()->sync($validSessionIds);

        if (! $isPaid) {
            if ($event->user?->email) {
                Mail::to($event->user->email)->send(new NewRegistrationNotificationMail($event, $registration));
            }

            if ($registration->status === 'free' && empty($registration->qr_token)) {
                $registration->qr_token = \Illuminate\Support\Str::random(40);
                $registration->save();
            }

            Mail::to($registration->email)->send(new RegistrationConfirmedMail($event, $registration));
            return redirect()->to(route('events.register.result', ['event' => $event, 'registered' => 1]));
        }

        // Build line items
        $lineItems = [];
        if ($isPaid) {
            $lineItems[] = [
                'price_data' => [
                    'currency'     => $currency,
                    'product_data' => ['name' => $event->name],
                    'unit_amount'  => (int) round(($event->ticket_cost ?? 0) * 100),
                ],
                'quantity' => $quantity,
            ];
            if ($feeMajor > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency'     => $currency,
                        'product_data' => ['name' => 'Booking fee'],
                        'unit_amount'  => (int) round($feeMajor * 100),
                    ],
                    'quantity' => 1,
                ];
            }
        }

        $stripe = new StripeClient(config('services.stripe.secret'));
        $session = $stripe->checkout->sessions->create([
            'mode'                 => 'payment',
            'payment_method_types' => ['card'],
            'line_items'           => $lineItems,
            'success_url' => route('events.register.result', ['event' => $event, 'paid' => 1]).'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => route('events.register.result', ['event' => $event, 'canceled' => 1]).'&session_id={CHECKOUT_SESSION_ID}',
            'metadata'    => [
                'event_id'        => (string) $event->id,
                'registration_id' => (string) $registration->id,
                'session_ids'     => implode(',', $validSessionIds),
                'email'           => $request->input('email'),
                'name'            => $request->input('name'),
                'user_id'         => (string) (Auth::id() ?? ''),
                'quantity'        => (string) $quantity,
                'subtotal_major'  => (string) $subtotalMajor,
                'platform_fee'    => (string) $feeMajor,
                'fee_mode'        => $event->feeMode(),
            ],
        ]);

        $registration->update(['stripe_session_id' => $session->id]);
        return redirect()->away($session->url);
    }

    public function result(Request $request, Event $event)
    {
        // Cancelled -> mark pending draft as canceled and bounce back to the form
        if ($request->boolean('canceled')) {
            if ($request->filled('session_id')) {
                EventRegistration::where('stripe_session_id', $request->query('session_id'))
                    ->where('event_id', $event->id)
                    ->update(['status' => 'canceled']);
            }

            return redirect()
                ->route('events.register', $event)
                ->with('warning', 'Checkout cancelled. No payment was taken.');
        }

        // Ensure categories render on the result page too
        $event->load([
            'sessions',
            'categories' => fn ($q) => $q->where('is_active', true)->orderBy('sort')->orderBy('id'),
        ]);

        $state = 'info';
        $title = 'Registration';
        $message = null;

        if ($request->boolean('registered')) {
            $state = 'success';
            $title = 'You’re registered! 🎉';
            $message = 'We’ve saved your registration. See you there!';
            return view('events.register-result', compact('event', 'state', 'title', 'message'));
        }

        if ($request->boolean('paid') && $request->filled('session_id')) {
            try {
                $stripe  = new \Stripe\StripeClient(config('services.stripe.secret'));
                $session = $stripe->checkout->sessions->retrieve($request->query('session_id'), []);

                if ($session && $session->payment_status === 'paid') {
                    $sessionCurrency = strtolower((string) ($session->currency ?? 'gbp'));
                    $exp             = $this->currencyExponent($sessionCurrency);

                    // Find the registration for this session
                    $reg = EventRegistration::where('stripe_session_id', $session->id)
                        ->where('event_id', $event->id)
                        ->first();

                    // Keep track so we don't email twice on refresh
                    $alreadyPaid = $reg && $reg->status === 'paid';

                    // Persist status/amount/currency
                    EventRegistration::where('stripe_session_id', $session->id)
                        ->where('event_id', $event->id)
                        ->update([
                            'status'   => 'paid',
                            'currency' => $sessionCurrency,
                        ]);

                    // Re-load if we didn't have it
                    if (! $reg) {
                        $reg = EventRegistration::where('stripe_session_id', $session->id)
                            ->where('event_id', $event->id)
                            ->first();
                    }

                    // 🔔 Send emails ONCE (organizer + attendee)
                    if ($reg && ! $alreadyPaid) {
                        try {
                            if ($event->user?->email) {
                                Mail::to($event->user->email)
                                    ->send(new NewRegistrationNotificationMail($event, $reg));
                            }
                            Mail::to($reg->email)
                                ->send(new RegistrationConfirmedMail($event, $reg));
                        } catch (\Throwable $mailErr) {
                            // Optional: log mail errors, but don't fail the success page
                            \Log::warning('Registration paid mail failed', [
                                'event_id' => $event->id,
                                'reg_id'   => $reg->id ?? null,
                                'err'      => $mailErr->getMessage(),
                            ]);
                        }
                    }

                    $state   = 'success';
                    $title   = 'Payment successful 🎉';
                    $message = 'Your registration is confirmed.';
                } else {
                    $state   = 'error';
                    $title   = 'We couldn’t verify your payment';
                    $message = 'If you saw a Stripe success screen, you should be registered. Otherwise, please try again.';
                }
            } catch (\Throwable $e) {
                \Log::error('Stripe verify error', ['err' => $e->getMessage()]);
                $state   = 'error';
                $title   = 'We couldn’t verify your payment';
                $message = 'Please refresh in a moment or contact support if you were charged.';
            }

            return view('events.register-result', compact('event', 'state', 'title', 'message'));
        }

        $state   = 'info';
        $title   = 'Status not clear';
        $message = 'If you just completed checkout, please refresh in a moment.';
        return view('events.register-result', compact('event', 'state', 'title', 'message'));
    }

    private function currencyExponent(string $currency): int
    {
        $c = strtolower($currency);
        $zero  = ['bif','clp','djf','gnf','jpy','kmf','krw','mga','pyg','rwf','ugx','vnd','vuv','xaf','xof','xpf'];
        if (in_array($c, $zero, true)) return 0;
        $three = ['bhd','jod','kwd','omr','tnd'];
        if (in_array($c, $three, true)) return 3;
        return 2;
    }
}
