<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\HomepageSponsor;
use App\Models\HomepageSlide;
use App\Models\EventRegistration;
use App\Models\EventPayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Mail\EventCreatedMail;
use App\Mail\OrganizerNewEventMail;
use Barryvdh\DomPDF\Facade\Pdf;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::withCount('registrations')->latest()->paginate(10);
        return view('events.index', compact('events'));
    }

    public function publicIndex(Request $request)
    {
        $q   = trim($request->query('q', ''));
        $loc = trim($request->query('loc', ''));
        $now = Carbon::now();

        // ------- Base event query -------
        $base = Event::query()
            ->where('is_disabled', false)
            ->with([
                'sessions'   => fn ($q) => $q->orderBy('session_date', 'asc'),
                'categories' => fn ($q) => $q->where('is_active', true)->orderBy('sort')->orderBy('id'),
            ])
            ->withMin('sessions', 'session_date')
            ->withMax('sessions', 'session_date');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $base->where(function ($s) use ($like) {
                $s->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('tags', 'like', $like)
                ->orWhere('location', 'like', $like)
                ->orWhereHas('organizer', fn ($oq) => $oq->where('name', 'like', $like));
            });
        }

        if ($loc !== '') {
            $base->where('location', 'like', '%' . $loc . '%');
        }

        // ------- Featured / upcoming / past -------
        $featuredIds = (clone $base)
            ->where('is_promoted', true)
            ->whereHas('sessions', fn ($q) => $q->where('session_date', '>=', $now))
            ->pluck('id');

        $featured = (clone $base)
            ->whereIn('id', $featuredIds)
            ->orderBy('sessions_min_session_date', 'asc')
            ->paginate(8, ['*'], 'featured_page');

        $upcoming = (clone $base)
            ->whereHas('sessions', fn ($q) => $q->where('session_date', '>=', $now))
            ->when($featuredIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $featuredIds))
            ->orderBy('sessions_min_session_date', 'asc')
            ->paginate(12, ['*'], 'upcoming_page');

        $past = (clone $base)
            ->whereDoesntHave('sessions', fn ($q) => $q->where('session_date', '>=', $now))
            ->whereHas('sessions', fn ($q) => $q->where('session_date', '<', $now))
            ->orderBy('sessions_max_session_date', 'desc')
            ->paginate(12, ['*'], 'past_page');

        $slides = HomepageSlide::active()
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        // ------- Sponsor skin (dynamic) -------
        $sponsorSkin = HomepageSponsor::activeForDate($now)
            ->inRandomOrder()
            ->first();

        $sponsorBgUrl = $sponsorSkin && $sponsorSkin->background_path
            ? asset('storage/' . $sponsorSkin->background_path)
            : null;

        $sponsorLogoUrl = $sponsorSkin && $sponsorSkin->logo_path
            ? asset('storage/' . $sponsorSkin->logo_path)
            : null;

        return view('events.public-index', [
            'featured'       => $featured,
            'upcoming'       => $upcoming,
            'past'           => $past,
            'q'              => $q,
            'loc'            => $loc,
            'slides'         => $slides,
            'sponsorSkin'    => $sponsorSkin,
            'sponsorBgUrl'   => $sponsorBgUrl,
            'sponsorLogoUrl' => $sponsorLogoUrl,
        ]);
    }

    public function browse(Request $request)
    {
        $now      = Carbon::now();

        $q        = trim($request->query('q', ''));
        $loc      = trim($request->query('loc', ''));
        $price    = $request->query('price');      // null | free | paid
        $category = $request->query('category');  // string or null
        $when     = $request->query('when');      // null | today | tomorrow | weekend | week | month

        // Same static category list you’re already using
        $catsList = [
            'Arts','Business','Charity','Community','Education','Entertainment',
            'Food & Drink','Fashion','Health','Music','Religion','Sports','Technology','Travel'
        ];

        // ------- Base query: only upcoming events -------
        $events = Event::query()
            ->where('is_disabled', false)
            ->with([
                'sessions'   => fn ($q) => $q->orderBy('session_date', 'asc'),
                'categories' => fn ($q) => $q->where('is_active', true)
                                            ->orderBy('sort')->orderBy('id'),
                'organizer',
            ])
            ->withMin('sessions', 'session_date');

        // Upcoming only
        $events->whereHas('sessions', fn ($q2) => $q2->where('session_date', '>=', $now));

        // Keyword search
        if ($q !== '') {
            $like = '%' . $q . '%';
            $events->where(function ($s) use ($like) {
                $s->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('tags', 'like', $like)
                ->orWhere('location', 'like', $like)
                ->orWhereHas('organizer', fn ($oq) => $oq->where('name', 'like', $like));
            });
        }

        // Location text (e.g. "Nottingham")
        if ($loc !== '') {
            $events->where('location', 'like', '%' . $loc . '%');
        }

        // Category filter
        if ($category) {
            $events->where('category', $category);
        }

        // Price filter: free vs paid
        if ($price === 'free') {
            $events->where(function ($q2) {
                $q2->whereDoesntHave('categories', function ($cq) {
                        $cq->where('is_active', true)->where('price', '>', 0);
                    })
                    ->where(function ($q3) {
                        $q3->whereNull('ticket_cost')
                        ->orWhere('ticket_cost', 0);
                    });
            });
        } elseif ($price === 'paid') {
            $events->where(function ($q2) {
                $q2->whereHas('categories', function ($cq) {
                        $cq->where('is_active', true)->where('price', '>', 0);
                    })
                    ->orWhere('ticket_cost', '>', 0);
            });
        }

        // Date filter
        switch ($when) {
            case 'today':
                $events->whereHas('sessions', fn ($q2) =>
                    $q2->whereDate('session_date', $now->toDateString())
                );
                break;
            case 'tomorrow':
                $tomorrow = $now->copy()->addDay()->toDateString();
                $events->whereHas('sessions', fn ($q2) =>
                    $q2->whereDate('session_date', $tomorrow)
                );
                break;
            case 'weekend':
                // Next Sat–Sun window
                $start = $now->copy()->next(Carbon::SATURDAY)->startOfDay();
                $end   = $start->copy()->addDay()->endOfDay();
                $events->whereHas('sessions', fn ($q2) =>
                    $q2->whereBetween('session_date', [$start, $end])
                );
                break;
            case 'week':
                $end = $now->copy()->addWeek()->endOfDay();
                $events->whereHas('sessions', fn ($q2) =>
                    $q2->whereBetween('session_date', [$now, $end])
                );
                break;
            case 'month':
                $end = $now->copy()->addMonth()->endOfDay();
                $events->whereHas('sessions', fn ($q2) =>
                    $q2->whereBetween('session_date', [$now, $end])
                );
                break;
        }

        $events = $events
            ->orderBy('sessions_min_session_date', 'asc')
            ->paginate(24)
            ->appends($request->query()); // keep filters when paging

        return view('events.find', [
            'events'  => $events,
            'cats'    => $catsList,
            'filters' => [
                'q'        => $q,
                'loc'      => $loc,
                'price'    => $price,
                'category' => $category,
                'when'     => $when,
            ],
        ]);
    }


    public function dashboard(Request $request)
    {
        $user = $request->user();

        // Make sure the user has an organiser profile
        if (! $user->organizer) {
            return redirect()
                ->route('organizers.create')
                ->with('error', 'Please create an organizer profile to view your dashboard.');
        }

        $organizer   = $user->organizer;
        $currentYear = now()->year;
        $year        = (int) $request->input('year', $currentYear);

        // Which KPI is shown in the bar chart: attendees|events|earnings|checkins
        $metric = $request->input('metric', 'attendees');

        /*
        |--------------------------------------------------------------------------
        | 1. Top-level KPIs
        |--------------------------------------------------------------------------
        */

        // 1) Number of events posted in the selected year
        $eventsThisYear = Event::where('user_id', $user->id)
            ->whereYear('created_at', $year);

        $eventsCount = (clone $eventsThisYear)->count();

        // 2) Total earnings from payouts (EventPayout.amount is in minor units / pence)
        $payoutsQuery = EventPayout::whereHas('event', function ($q) use ($organizer) {
                $q->where('organizer_id', $organizer->id);
            })
            ->where('status', 'paid')
            ->whereYear('created_at', $year);

        $totalEarnings = (int) $payoutsQuery->sum('amount'); // minor units

        // 3) Total registered attendees (registrant + party) in the selected year
        $registrationsQuery = EventRegistration::whereHas('event', function ($q) use ($organizer) {
                $q->where('organizer_id', $organizer->id);
            })
            ->whereYear('created_at', $year);

        $registrations = $registrationsQuery->get();

        $totalAttendees = $registrations->sum(function ($r) {
            $adults   = max(0, (int) $r->party_adults);
            $children = max(0, (int) $r->party_children);
            return 1 + $adults + $children;
        });

        // 4) Total check-ins (registrations with a check-in timestamp)
        $totalCheckins = EventRegistration::whereHas('event', function ($q) use ($organizer) {
                $q->where('organizer_id', $organizer->id);
            })
            ->whereNotNull('checked_in_at')
            ->whereYear('checked_in_at', $year)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | 2. Monthly chart data for selected metric
        |--------------------------------------------------------------------------
        */

        // 1-based index (Jan = 1 .. Dec = 12)
        $chartData = array_fill(1, 12, 0);

        if ($metric === 'events') {
            // Events created per month
            $rows = Event::where('organizer_id', $organizer->id)
                ->whereYear('created_at', $year)
                ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
                ->groupBy('month')
                ->pluck('total', 'month');

            foreach ($rows as $month => $total) {
                $chartData[$month] = (int) $total;
            }

        } elseif ($metric === 'earnings') {
            // Earnings per month (convert minor units to major for the chart)
            $rows = EventPayout::whereHas('event', function ($q) use ($organizer) {
                    $q->where('organizer_id', $organizer->id);
                })
                ->where('status', 'paid')
                ->whereYear('created_at', $year)
                ->selectRaw('MONTH(created_at) as month, SUM(amount) as total')
                ->groupBy('month')
                ->pluck('total', 'month');

            foreach ($rows as $month => $total) {
                // convert pence → pounds with 2 decimals
                $chartData[$month] = round(((float) $total) / 100, 2);
            }

        } elseif ($metric === 'checkins') {
            // Check-ins per month
            $rows = EventRegistration::whereHas('event', function ($q) use ($organizer) {
                    $q->where('organizer_id', $organizer->id);
                })
                ->whereNotNull('checked_in_at')
                ->whereYear('checked_in_at', $year)
                ->selectRaw('MONTH(checked_in_at) as month, COUNT(*) as total')
                ->groupBy('month')
                ->pluck('total', 'month');

            foreach ($rows as $month => $total) {
                $chartData[$month] = (int) $total;
            }

        } else {
            // Default metric: registered attendees per month
            $rows = EventRegistration::whereHas('event', function ($q) use ($organizer) {
                    $q->where('organizer_id', $organizer->id);
                })
                ->whereYear('created_at', $year)
                ->selectRaw('MONTH(created_at) as month,
                    SUM(1 + COALESCE(party_adults,0) + COALESCE(party_children,0)) as total')
                ->groupBy('month')
                ->pluck('total', 'month');

            foreach ($rows as $month => $total) {
                $chartData[$month] = (int) $total;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Year dropdown options
        |--------------------------------------------------------------------------
        */

        // e.g. [2025, 2024, 2023, 2022, 2021]
        $availableYears = range($currentYear, $currentYear - 4);

        return view('dashboard', [
            'year'           => $year,
            'metric'         => $metric,
            'eventsCount'    => $eventsCount,
            'totalEarnings'  => $totalEarnings,      // still minor units; Blade divides by 100
            'totalAttendees' => $totalAttendees,
            'totalCheckins'  => $totalCheckins,
            'chartData'      => array_values($chartData), // 0..11 for Jan–Dec
            'availableYears' => $availableYears,
        ]);
    }


    public function downloadReport(Request $request)
    {
        $user = $request->user();

        if (! $user->organizer) {
            return redirect()
                ->route('organizers.create')
                ->with('error', 'Please create an organizer profile to download a report.');
        }

        $organizer   = $user->organizer;
        $currentYear = now()->year;
        $year        = (int) $request->input('year', $currentYear);
        $metric      = $request->input('metric', 'attendees'); // attendees|events|earnings|checkins

        // ---- KPIs (same logic as dashboard) ----
        $eventsThisYear = Event::where('organizer_id', $organizer->id)
            ->whereYear('created_at', $year);

        $eventsCount = (clone $eventsThisYear)->count();

        $payoutsQuery = EventPayout::whereHas('event', function ($q) use ($organizer) {
                $q->where('organizer_id', $organizer->id);
            })
            ->where('status', 'paid')
            ->whereYear('created_at', $year);

        $totalEarnings = (int) $payoutsQuery->sum('amount'); // minor units

        $registrationsQuery = EventRegistration::whereHas('event', function ($q) use ($organizer) {
                $q->where('organizer_id', $organizer->id);
            })
            ->whereYear('created_at', $year);

        $registrations = $registrationsQuery->get();

        $totalAttendees = $registrations->sum(function ($r) {
            $adults   = max(0, (int) $r->party_adults);
            $children = max(0, (int) $r->party_children);
            return 1 + $adults + $children;
        });

        $totalCheckins = EventRegistration::whereHas('event', function ($q) use ($organizer) {
                $q->where('organizer_id', $organizer->id);
            })
            ->whereNotNull('checked_in_at')
            ->whereYear('checked_in_at', $year)
            ->count();

        // ---- Monthly data (same idea as dashboard, but used in a table) ----
        $chartData = array_fill(1, 12, 0);

        if ($metric === 'events') {
            $rows = Event::where('organizer_id', $organizer->id)
                ->whereYear('created_at', $year)
                ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
                ->groupBy('month')
                ->pluck('total', 'month');

            foreach ($rows as $month => $total) {
                $chartData[$month] = (int) $total;
            }

        } elseif ($metric === 'earnings') {
            $rows = EventPayout::whereHas('event', function ($q) use ($organizer) {
                    $q->where('organizer_id', $organizer->id);
                })
                ->where('status', 'paid')
                ->whereYear('created_at', $year)
                ->selectRaw('MONTH(created_at) as month, SUM(amount) as total')
                ->groupBy('month')
                ->pluck('total', 'month');

            foreach ($rows as $month => $total) {
                $chartData[$month] = round(((float) $total) / 100, 2); // pence → pounds
            }

        } elseif ($metric === 'checkins') {
            $rows = EventRegistration::whereHas('event', function ($q) use ($organizer) {
                    $q->where('organizer_id', $organizer->id);
                })
                ->whereNotNull('checked_in_at')
                ->whereYear('checked_in_at', $year)
                ->selectRaw('MONTH(checked_in_at) as month, COUNT(*) as total')
                ->groupBy('month')
                ->pluck('total', 'month');

            foreach ($rows as $month => $total) {
                $chartData[$month] = (int) $total;
            }

        } else { // attendees default
            $rows = EventRegistration::whereHas('event', function ($q) use ($organizer) {
                    $q->where('organizer_id', $organizer->id);
                })
                ->whereYear('created_at', $year)
                ->selectRaw('MONTH(created_at) as month,
                    SUM(1 + COALESCE(party_adults,0) + COALESCE(party_children,0)) as total')
                ->groupBy('month')
                ->pluck('total', 'month');

            foreach ($rows as $month => $total) {
                $chartData[$month] = (int) $total;
            }
        }

        // turn into 0-based array for the view
        $chartData = array_values($chartData);

        $pdf = Pdf::loadView('dashboard-report', [
            'organizer'      => $organizer,
            'year'           => $year,
            'metric'         => $metric,
            'eventsCount'    => $eventsCount,
            'totalEarnings'  => $totalEarnings,  // minor units
            'totalAttendees' => $totalAttendees,
            'totalCheckins'  => $totalCheckins,
            'chartData'      => $chartData,
        ]);

        return $pdf->download("eventib-dashboard-{$year}.pdf");
    }



    public function manage(Request $request)
    {
        $user = $request->user();

        // Ensure they have an organizer profile
        if (! $user->organizer) {
            return redirect()
                ->route('organizers.create')
                ->with('error', 'Please create an organizer profile before managing events.');
        }

        $events = Event::where('user_id', $user->id)
            ->with([
                'sessions',
                'categories',
                'registrations' => function ($q) {
                    // only count useful statuses
                    $q->whereIn('status', ['paid', 'free', 'completed', 'checked_in']);
                },
                'unlocks',
            ])
            ->latest()
            ->paginate(9);

        return view('events.manage', compact('events'));
    }


    public function create()
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }
        $organizers = $user->organizers ?? \App\Models\Organizer::where('user_id', $user->id)->get();

        if ($organizers->isEmpty()) {
            return redirect()->route('organizers.create')->with('error', 'Please create an organizer profile before creating an event.');
        }

        $payouts = auth()->user()
            ? auth()->user()->payoutMethods()->get()->groupBy('country')
            : collect();

        $organizers = \App\Models\Organizer::where('user_id', auth()->id())->get();

        return view('events.create', [
            'payoutsByCountry' => $payouts->toArray(),
            'organizers'       => $organizers,
        ]);
    }


    public function store(Request $request)
    {
        // -------- 1) Base validation (no currency/payout requirements yet) --------
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'organizer_id' => [
                'required',
                'integer',
                Rule::exists('organizers', 'id')->where(fn ($q) => $q->where('user_id', Auth::id())),
            ],
            'category'        => 'nullable|string|max:100',
            'tags'            => 'nullable|array',
            'tags.*'          => 'string|max:50',
            'location'        => 'nullable|string|max:255',
            'description'     => 'nullable|string',

            // legacy fields retained (can be null for free)
            'ticket_cost'     => 'nullable|numeric|min:0|max:99999999.99',
            'ticket_currency' => 'nullable|string|in:GBP,USD,CAD,AUD,INR,NGN,KES,GHS,EUR|size:3',

            'avatar'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            'banner'          => 'nullable|image|mimes:jpg,jpeg,png|max:4096|required_without:external_banner_url',
            'external_banner_url' => 'nullable|url',
            'is_promoted'     => 'nullable|boolean',
            'is_recurring'   => 'nullable|boolean',
            'recurrence_summary' => 'nullable|string|max:255',

            'sessions.*.name' => 'required|string|max:255',
            'sessions.*.date' => 'required|date',
            'sessions.*.time' => 'required',
            'capacity'        => 'nullable|integer|min:0',

            'digital_pass_mode'    => 'required|in:off,optional,required',
            'digital_pass_methods' => 'required|in:voice,face,both',

            'payout_method_id'  => ['nullable','integer', Rule::exists('user_payout_methods', 'id')->where(fn ($q) => $q->where('user_id', Auth::id()))],
        ]);

        // Categories payload (only keep priced > 0)
        $rows = collect($request->input('categories', []))
            ->map(fn ($r) => [
                'name'      => trim((string)($r['name'] ?? '')),
                'price'     => (float)($r['price'] ?? 0),
                'capacity'  => isset($r['capacity']) && $r['capacity'] !== '' ? (int)$r['capacity'] : null,
                'is_active' => array_key_exists('is_active', $r) ? (bool)$r['is_active'] : true,
                'sort'      => (int)($r['sort'] ?? 0),
            ])
            ->filter(fn ($r) => $r['name'] !== '' && $r['price'] > 0)
            ->values();

        $hasCats      = $rows->isNotEmpty();
        $isPaidLegacy = !$hasCats && (float)($validated['ticket_cost'] ?? 0) > 0;
        $isPaid       = $hasCats || $isPaidLegacy;

        // -------- 2) Conditional rules once we know paid/free --------
        $currency = strtoupper($validated['ticket_currency'] ?? ''); // may be empty
        if ($isPaid) {
            // currency required + payout required
            $request->validate([
                'ticket_currency' => 'required|string|in:GBP,USD,CAD,AUD,INR,NGN,KES,GHS,EUR|size:3',
                'payout_method_id' => ['required','integer', Rule::exists('user_payout_methods', 'id')->where(fn ($q) => $q->where('user_id', Auth::id()))],
                'fee_mode'        => 'required|in:absorb,pass',
            ]);
            $currency = strtoupper((string)$request->input('ticket_currency'));
            // country sanity check (banks)
            $pm = \App\Models\UserPayoutMethod::where('id', $request->input('payout_method_id'))
                  ->where('user_id', Auth::id())->first();
            if ($pm) {
                $map = ['GBP'=>'GB','USD'=>'US','CAD'=>'CA','AUD'=>'AU','INR'=>'IN','NGN'=>'NG','KES'=>'KE','GHS'=>'GH','EUR'=>'EU'];
                $expectedCountry = $map[$currency] ?? 'GB';
                if ($pm->type === 'bank' && strtoupper((string)$pm->country) !== $expectedCountry) {
                    return back()->withErrors([
                        'payout_method_id' => "Selected payout method is for {$pm->country}, but the chosen currency requires {$expectedCountry}."
                    ])->withInput();
                }
            }
        } else {
            // FREE ⇒ not required; force a safe default so DB not-null columns are happy
            $currency = $currency ?: 'GBP';
        }

        $feeMode = $isPaid ? ($request->input('fee_mode') ?: 'absorb') : 'absorb';
        $feeBps  = $isPaid ? 590 : 0; // default

        // -------- 3) Persist --------
        $avatarUrl = $request->hasFile('avatar')
            ? $request->file('avatar')->store('avatars', 'public')
            : null;

        $bannerUrl = null;

        if ($request->hasFile('banner')) {
            $bannerUrl = $request->file('banner')->store('banners', 'public');
        } elseif ($request->filled('external_banner_url')) {
            try {
                $response = Http::timeout(10)->get($request->input('external_banner_url'));

                if ($response->successful()) {
                    // crude content-type → extension mapping
                    $contentType = $response->header('Content-Type', 'image/jpeg');
                    $ext = match (true) {
                        str_contains($contentType, 'png')  => 'png',
                        str_contains($contentType, 'webp') => 'webp',
                        default                            => 'jpg',
                    };

                    $filename = 'banners/' . Str::uuid() . '.' . $ext;
                    Storage::disk('public')->put($filename, $response->body());
                    $bannerUrl = $filename;
                }
            } catch (\Throwable $e) {
                // optional: log but don't break event creation
                \Log::warning('Failed to download external banner', [
                    'url' => $request->input('external_banner_url'),
                    'error' => $e->getMessage(),
                ]);
            }
        }


        $tagsArray = is_array($validated['tags'] ?? null) ? $validated['tags'] : [];

        $event = Event::create([
            'user_id'          => Auth::id(),
            'name'             => $validated['name'],
            'organizer_id' => $validated['organizer_id'],
            'category'         => $validated['category'] ?? null,
            'tags'             => json_encode($tagsArray),
            'location'         => $validated['location'] ?? null,
            'description'      => $validated['description'] ?? null,
            'ticket_cost'      => $hasCats ? 0 : ($validated['ticket_cost'] ?? 0), // ignore legacy cost when using categories
            'ticket_currency'  => $currency,
            'payout_method_id' => $isPaid ? ($request->input('payout_method_id') ?? null) : null,
            'avatar_url'       => $avatarUrl,
            'banner_url'       => $bannerUrl,
            'is_promoted'      => (bool)($validated['is_promoted'] ?? false),
            'fee_mode'         => $feeMode,
            'fee_bps'          => $feeBps,
            'is_recurring'       => $request->boolean('is_recurring'),
            'recurrence_summary' => $request->input('recurrence_summary') ?: null,
            'capacity'         => $validated['capacity'] ?? null,
            'digital_pass_mode'    => $validated['digital_pass_mode'],
            'digital_pass_methods' => $validated['digital_pass_methods'],
        ]);

        if ($request->has('sessions')) {
            foreach ($request->sessions as $session) {
                $event->sessions()->create([
                    'session_name' => $session['name'],
                    'session_date' => $session['date'] . ' ' . $session['time'],
                ]);
            }
        }

        foreach ($rows as $r) {
            $event->categories()->create($r);
        }

        Mail::to(auth()->user()->email)->send(new EventCreatedMail($event));

        // notify followers
        $organizer = $event->organizer; // relation
        if ($organizer) {
            $followers = $organizer->followers; // assuming you have many-to-many
            foreach ($followers as $follower) {
                if ($follower->email) {
                    Mail::to($follower->email)->queue(new OrganizerNewEventMail($organizer, $event));
                }
            }
        }

        return redirect()->route('events.manage')->with('success', 'Event created successfully!');
    }

    public function update(Request $request, Event $event)
    {
        abort_if($event->user_id !== Auth::id(), 403);

        // ✅ 1. Base validation – ONLY fields that the edit form actually sends
        $validated = $request->validate([
            'name'          => 'required|string|max:255',

            'organizer_id'  => [
                'required',
                'integer',
                Rule::exists('organizers', 'id')->where(fn ($q) => $q->where('user_id', Auth::id())),
            ],

            'category'      => 'nullable|string|max:100',

            'tags'          => 'nullable|array',
            'tags.*'        => 'string|max:50',

            'location'      => 'nullable|string|max:255',
            'description'   => 'nullable|string',
            'is_recurring'   => 'nullable|boolean',
            'recurrence_summary' => 'nullable|string|max:255',
            'capacity'      => 'nullable|integer|min:0',
            'digital_pass_mode'    => 'required|in:off,optional,required',
            'digital_pass_methods' => 'required|in:voice,face,both',

            // media
            'avatar'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'banner'        => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        // ✅ 2. Handle media uploads (avatar & banner)
        if ($request->hasFile('avatar')) {
            if ($event->avatar_url) {
                Storage::disk('public')->delete($event->avatar_url);
            }

            $event->avatar_url = $request->file('avatar')->store('avatars', 'public');
        }

        if ($request->hasFile('banner')) {
            if ($event->banner_url) {
                Storage::disk('public')->delete($event->banner_url);
            }

            $event->banner_url = $request->file('banner')->store('banners', 'public');
        }

        // ✅ 3. Tags → JSON array
        $tagsArray = is_array($validated['tags'] ?? null) ? $validated['tags'] : [];

        // ✅ 4. Update ONLY editable fields (NO pricing or payout changes here)
        $event->name             = $validated['name'];
        $event->organizer_id     = $validated['organizer_id'];
        $event->category         = $validated['category'] ?? null;
        $event->tags             = json_encode($tagsArray);
        $event->location         = $validated['location'] ?? null;
        $event->description      = $validated['description'] ?? null;
        $event->capacity         = $validated['capacity'] ?? null;
        $event->digital_pass_mode    = $validated['digital_pass_mode'];
        $event->digital_pass_methods = $validated['digital_pass_methods'];

        // ⚠️ Do NOT touch ticket_cost, ticket_currency, payout_method_id, fee_mode, fee_bps here.
        // They are locked after creation.

        // Keep the original is_recurring flag as-is (no toggle on edit)
        // but allow changing the human readable summary text
        // --- Recurrence ---
        // Recurring settings (editable on update)
        $event->is_recurring = $request->boolean('is_recurring');

        $event->recurrence_summary = $request->filled('recurrence_summary')
            ? $request->input('recurrence_summary')
            : null;

        $event->save();

        // ✅ 5. Sessions upsert (create / update / soft delete)
        $sessions = $request->input('sessions', []);

        foreach ($sessions as $row) {
            $name   = trim($row['name'] ?? '');
            $date   = $row['date'] ?? null;
            $time   = $row['time'] ?? null;
            $delete = isset($row['_delete']) && (int)$row['_delete'] === 1;
            $id     = $row['id'] ?? null;

            $dt = ($date && $time) ? Carbon::parse("{$date} {$time}") : null;

            // Existing session
            if ($id) {
                $session = EventSession::where('event_id', $event->id)
                    ->where('id', $id)
                    ->first();

                if (! $session) {
                    continue;
                }

                if ($delete) {
                    $session->delete();
                    continue;
                }

                // Update existing
                if ($name !== '') {
                    $session->session_name = $name;
                }
                if ($dt) {
                    $session->session_date = $dt;
                }
                $session->save();
            } else {
                // New session
                if ($delete || ! $name || ! $dt) {
                    continue;
                }

                EventSession::create([
                    'event_id'     => $event->id,
                    'session_name' => $name,
                    'session_date' => $dt,
                ]);
            }
        }

        // ✅ 6. Ticket categories are NOT modified on edit anymore.
        // Remove the whole categories upsert block from the old method.

        return redirect()
            ->route('events.manage')
            ->with('success', 'Event updated successfully!');
    }


    public function show(Event $event)
    {
        $event->load('organizer'); // Add this line
        $activeCats = $event->categories()->where('is_active', true)->orderBy('sort')->orderBy('id')->get();
        $min = $activeCats->min('price');
        $max = $activeCats->max('price');

        $upcomingSessions = $event->upcomingSessions()->get();
        $nextSession = $upcomingSessions->first();

        return view('events.show', [
            'event'      => $event,
            'activeCats' => $activeCats,
            'minPrice'   => $min,
            'maxPrice'   => $max,
            'upcomingSessions' => $upcomingSessions,
            'nextSession'      => $nextSession,
        ]);
    }

    public function past()
    {
        // Get events whose latest session date is in the past
        $past = \App\Models\Event::whereHas('sessions', function ($query) {
                $query->where('session_date', '<', now());
            })
            ->with(['sessions' => function ($query) {
                $query->orderBy('session_date', 'desc');
            }])
            ->orderByDesc(
                \DB::raw('(SELECT MAX(session_date) FROM event_sessions WHERE event_sessions.event_id = events.id)')
            )
            ->paginate(12);

        return view('events.past', compact('past'));
    }



    public function avatar(Event $event)
    {
        if (!$event->avatar_url) {
            return redirect()->route('events.show', $event)->with('error', 'This event does not have an avatar image yet.');
        }
        return view('events.avatar', compact('event'));
    }

    public function edit(Event $event)
    {
        abort_if($event->user_id !== Auth::id(), 403);

        $payouts = auth()->user()->payoutMethods()->get()->groupBy('country');

        // fetch organizers for this user
        $organizers = \App\Models\Organizer::where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

        return view('events.edit', [
            'event'           => $event,
            'payoutsByCountry'=> $payouts->toArray(),
            'organizers'      => $organizers,
        ]);
    }


    public function destroy(Event $event)
    {
        abort_if($event->user_id !== Auth::id(), 403);

        if ($event->avatar_url) Storage::disk('public')->delete($event->avatar_url);
        if ($event->banner_url) Storage::disk('public')->delete($event->banner_url);

        $event->delete();

        return redirect()->route('events.manage')->with('success', 'Event deleted.');
    }
}
