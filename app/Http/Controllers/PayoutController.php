<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventPayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\PayoutRequestedAdminMail;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
         $this->ensureActiveUser();
         $userId = Auth::id();

        // List of the user's events for the filter dropdown
        $events = Event::where('user_id', $userId)
            ->orderBy('name')
            ->get(['id','public_id','name','ticket_currency']);

        // Selected event (by public_id in query ?event=...)
        $selectedPublicId = $request->query('event');
        $selectedEvent = $selectedPublicId
            ? $events->firstWhere('public_id', $selectedPublicId)
            : null;

        // Base query for payouts (optionally filtered by selected event)
        $payoutsQuery = EventPayout::where('user_id', $userId)
            ->with(['event:id,public_id,name,ticket_currency'])
            ->latest();

        if ($selectedEvent) {
            $payoutsQuery->where('event_id', $selectedEvent->id);
        }

        // Paginated list (keep query string so the filter persists on next/prev)
        $payouts = $payoutsQuery->paginate(12)->withQueryString();

        // Totals over the (filtered) set
        $totalsSet = (clone $payoutsQuery)->get();
        $totalRequestedMinor = (int) $totalsSet->sum('amount');                      // all statuses
        $totalPaidMinor      = (int) $totalsSet->where('status','paid')->sum('amount');

        // Currency + symbol (only meaningful when a single event is selected)
        $currency = $selectedEvent ? strtoupper($selectedEvent->ticket_currency ?? 'GBP') : null;
        $symbols  = ['GBP'=>'£','USD'=>'$','EUR'=>'€','NGN'=>'₦','KES'=>'KSh','GHS'=>'₵','ZAR'=>'R','CAD'=>'$','AUD'=>'$'];
        $symbol   = $currency ? ($symbols[$currency] ?? '') : '';

        return view('payouts.index', compact(
            'payouts',
            'events',
            'selectedPublicId',
            'selectedEvent',
            'totalRequestedMinor',
            'totalPaidMinor',
            'currency',
            'symbol'
        ));
    }

    public function create(Event $event, Request $request)
    {
        $this->ensureActiveUser();
        abort_unless($event->user_id === Auth::id(), 403);

        // Freshly compute how much is available right now
        [$availableMinor, $currency] = $this->availableForEvent($event);

        if ($availableMinor <= 0) {
            return redirect()
                ->route('events.registrants', $event)
                ->with('error', 'No funds available to withdraw yet.');
        }

        // If a query ?amount=... was provided, clamp it to the available amount
        $amount = (int) $request->query('amount', $availableMinor);
        $amount = max(1, min($amount, $availableMinor));

        $spec = $this->payoutSpecFor($currency);

        // NOTE: We no longer block if there is a processing payout; user can request again
        // if there is new money available.

        return view('payouts.create', [
            'event'    => $event,
            'amount'   => $amount,    // minor units
            'currency' => $currency,  // e.g. GBP
            'spec'     => $spec,
        ]);
    }

    public function store(Event $event, Request $request)
    {
        abort_unless($event->user_id === Auth::id(), 403);

        $currency = strtolower($event->ticket_currency ?? 'gbp');

        $validated = $request->validate([
            'amount'         => 'required|integer|min:1', // minor units
            'account_name'   => 'required|string|max:100',
            'sort_code'      => 'nullable|string|max:32',
            'account_number' => 'required|string|max:34',
            'iban'           => 'nullable|string|max:34',
        ]);

        return DB::transaction(function () use ($event, $validated, $currency) {
            // Recompute inside the transaction to avoid race conditions
            [$availableMinor, $curr] = $this->availableForEvent($event);
            if ($availableMinor <= 0 || $validated['amount'] > $availableMinor) {
                return redirect()
                    ->route('events.registrants', $event)
                    ->with('error', 'Requested amount exceeds what is currently available. Please try again.');
            }

            EventPayout::create([
                'event_id'       => $event->id,
                'user_id'        => Auth::id(),
                'amount'         => (int) $validated['amount'],
                'currency'       => $currency,
                'account_name'   => $validated['account_name'],
                'sort_code'      => $validated['sort_code'] ?? null,
                'account_number' => $validated['account_number'],
                'iban'           => $validated['iban'] ?? null,
                'status'         => 'processing',
            ]);

            Mail::to(config('mail.ops_address'))->queue(new PayoutRequestedAdminMail($payout));

            return redirect()
                ->route('payouts.index')
                ->with('success', 'Payout request submitted and marked as processing.');
        });
    }

    /** Helper: compute current available (minor units) and currency for an event. */
    private function availableForEvent(Event $event): array
    {
        $currency = strtoupper($event->ticket_currency ?? 'GBP');

        // Gross from paid registrations (minor units)
        $sumMinor = $event->registrations()
            ->when(true, fn($q) => $q->where('status', 'paid'))
            ->get()
            ->sum(function ($r) {
                return (int) round(((float) ($r->amount ?? 0)) * 100);
            });

        // 9.99% commission
        $commissionMinor = intdiv($sumMinor * 590, 10000);
        $netMinor        = max(0, $sumMinor - $commissionMinor);

        // Subtract payouts not failed/cancelled
        $deductedMinor = (int) EventPayout::where('event_id', $event->id)
            ->where('user_id', Auth::id())
            ->whereNotIn('status', ['failed', 'cancelled'])
            ->sum('amount');

        $availableMinor = max(0, $netMinor - $deductedMinor);

        return [$availableMinor, $currency];
    }

    /** Small helper: field labels + symbol per currency  */
    private function payoutSpecFor(string $cur): array
    {
        $cur = strtoupper($cur);

        $map = [
            'GBP' => [
                'country' => 'United Kingdom',
                'paypal'  => true,
                'symbol'  => '£',
                'labels'  => [
                    'account_name'   => 'Account name',
                    'sort_code'      => 'Sort code',
                    'account_number' => 'Account number',
                    'iban'           => 'IBAN (optional)',
                ],
            ],
            'USD' => [
                'country' => 'United States',
                'paypal'  => true,
                'symbol'  => '$',
                'labels'  => [
                    'account_name'   => 'Account holder name',
                    'sort_code'      => 'Routing number',
                    'account_number' => 'Account number',
                    'iban'           => 'IBAN (optional)',
                ],
            ],
            'EUR' => [
                'country' => 'Eurozone',
                'paypal'  => true,
                'symbol'  => '€',
                'labels'  => [
                    'account_name'   => 'Account holder name',
                    'sort_code'      => 'BIC/SWIFT (optional)',
                    'account_number' => 'Account number (optional)',
                    'iban'           => 'IBAN',
                ],
            ],
            'NGN' => [
                'country' => 'Nigeria',
                'paypal'  => false,
                'symbol'  => '₦',
                'labels'  => [
                    'account_name'   => 'Account name',
                    'sort_code'      => 'Bank code',
                    'account_number' => 'Account number',
                    'iban'           => 'IBAN (optional)',
                ],
            ],
            'KES' => [
                'country' => 'Kenya',
                'paypal'  => true,
                'symbol'  => 'KSh',
                'labels'  => [
                    'account_name'   => 'Account name',
                    'sort_code'      => 'Bank code',
                    'account_number' => 'Account number',
                    'iban'           => 'IBAN (optional)',
                ],
            ],
            'GHS' => [
                'country' => 'Ghana',
                'paypal'  => false,
                'symbol'  => '₵',
                'labels'  => [
                    'account_name'   => 'Account name',
                    'sort_code'      => 'Bank code',
                    'account_number' => 'Account number',
                    'iban'           => 'IBAN (optional)',
                ],
            ],
            'ZAR' => [
                'country' => 'South Africa',
                'paypal'  => true,
                'symbol'  => 'R',
                'labels'  => [
                    'account_name'   => 'Account holder name',
                    'sort_code'      => 'Branch code',
                    'account_number' => 'Account number',
                    'iban'           => 'IBAN (optional)',
                ],
            ],
            'CAD' => [
                'country' => 'Canada',
                'paypal'  => true,
                'symbol'  => '$',
                'labels'  => [
                    'account_name'   => 'Account holder name',
                    'sort_code'      => 'Transit/Institution no.',
                    'account_number' => 'Account number',
                    'iban'           => 'IBAN (optional)',
                ],
            ],
            'AUD' => [
                'country' => 'Australia',
                'paypal'  => true,
                'symbol'  => '$',
                'labels'  => [
                    'account_name'   => 'Account holder name',
                    'sort_code'      => 'BSB',
                    'account_number' => 'Account number',
                    'iban'           => 'IBAN (optional)',
                ],
            ],
        ];

        // Safe fallback
        return $map[$cur] ?? [
            'country' => $cur,
            'paypal'  => true,
            'symbol'  => '',
            'labels'  => [
                'account_name'   => 'Account name',
                'sort_code'      => 'Bank code',
                'account_number' => 'Account number',
                'iban'           => 'IBAN (optional)',
            ],
        ];
    }
}
