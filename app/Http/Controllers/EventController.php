<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Mail\EventCreatedMail;
use Illuminate\Validation\Rule;
use App\Mail\OrganizerNewEventMail;
use App\Models\HomepageSponsor;
use App\Models\HomepageSlide;


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


    public function dashboard()
    {
        $events = Event::where('user_id', Auth::id())
            ->with([
                'registrations' => fn ($q) => $q->whereIn('status', ['paid', 'free'])->latest(),
                'categories'    => fn ($q) => $q->select('id','event_id','price','is_active','sort'),
                'unlocks'       => fn ($q) => $q->where('user_id', Auth::id()),
            ])
            ->withMin('sessions', 'session_date')
            ->latest()
            ->paginate(12);

        return view('dashboard', compact('events'));
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
            'banner'          => 'required|image|mimes:jpg,jpeg,png|max:4096',
            'is_promoted'     => 'nullable|boolean',
            'is_recurring'   => 'nullable|boolean',
            'recurrence_summary' => 'nullable|string|max:255',

            'sessions.*.name' => 'required|string|max:255',
            'sessions.*.date' => 'required|date',
            'sessions.*.time' => 'required',
            'capacity'        => 'nullable|integer|min:0',

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
        $avatarUrl = $request->hasFile('avatar') ? $request->file('avatar')->store('avatars', 'public') : null;
        $bannerUrl = $request->hasFile('banner') ? $request->file('banner')->store('banners', 'public') : null;

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

        return redirect()->route('dashboard')->with('success', 'Event created successfully!');
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
            ->route('dashboard')
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

        return redirect()->route('dashboard')->with('success', 'Event deleted.');
    }
}
