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

class EventController extends Controller
{
    public function index()
    {
        $events = Event::withCount('registrations')->latest()->paginate(10);
        return view('events.index', compact('events'));
    }

    public function publicIndex(Request $request)
    {
        $q    = trim($request->query('q', ''));
        $loc  = trim($request->query('loc', ''));
        $now  = Carbon::now();

        $base = Event::query()
            ->where('is_disabled', false)
            ->with([
                'sessions'   => fn($q) => $q->orderBy('session_date', 'asc'),
                'categories' => fn($q) => $q->where('is_active', true)->orderBy('sort')->orderBy('id'),
            ])
            ->withMin('sessions', 'session_date')
            ->withMax('sessions', 'session_date');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $base->where(function ($s) use ($like) {
                $s->where('name', 'like', $like)
                  ->orWhere('organizer', 'like', $like)
                  ->orWhere('description', 'like', $like)
                  ->orWhere('tags', 'like', $like);
            });
        }

        if ($loc !== '') {
            $base->where('location', 'like', '%' . $loc . '%');
        }

        $featuredIds = (clone $base)
            ->where('is_promoted', true)
            ->whereHas('sessions', fn($q) => $q->where('session_date', '>=', $now))
            ->pluck('id');

        $featured = (clone $base)
            ->whereIn('id', $featuredIds)
            ->orderBy('sessions_min_session_date', 'asc')
            ->paginate(8, ['*'], 'featured_page');

        $upcoming = (clone $base)
            ->whereHas('sessions', fn($q) => $q->where('session_date', '>=', $now))
            ->when($featuredIds->isNotEmpty(), fn($q) => $q->whereNotIn('id', $featuredIds))
            ->orderBy('sessions_min_session_date', 'asc')
            ->paginate(12, ['*'], 'upcoming_page');

        $past = (clone $base)
            ->whereDoesntHave('sessions', fn($q) => $q->where('session_date', '>=', $now))
            ->whereHas('sessions', fn($q) => $q->where('session_date', '<', $now))
            ->orderBy('sessions_max_session_date', 'desc')
            ->paginate(12, ['*'], 'past_page');

        $slides = \App\Models\HomepageSlide::active()->orderBy('sort')->orderBy('id')->get();

        return view('events.public-index', compact('featured','upcoming','past','q','loc','slides'));
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

            'sessions.*.name' => 'required|string|max:255',
            'sessions.*.date' => 'required|date',
            'sessions.*.time' => 'required',

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

        return redirect()->route('dashboard')->with('success', 'Event created successfully!');
    }

    public function update(Request $request, Event $event)
    {
        abort_if($event->user_id !== Auth::id(), 403);

        // base validation
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'organizer_id' => [
                    'required',
                    'integer',
                    Rule::exists('organizers', 'id')->where(fn ($q) => $q->where('user_id', Auth::id())),
                ],
            'category'    => 'nullable|string|max:100',
            'tags'        => 'nullable|array',
            'tags.*'      => 'string|max:50',
            'location'    => 'nullable|string|max:255',
            'description' => 'nullable|string',

            'ticket_cost'     => 'nullable|numeric|min:0|max:99999999.99',
            'ticket_currency' => 'nullable|string|in:GBP,USD,CAD,AUD,INR,NGN,KES,GHS,EUR|size:3',

            'avatar'      => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'banner'      => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
            'payout_method_id' => ['nullable','integer', Rule::exists('user_payout_methods', 'id')->where(fn ($q) => $q->where('user_id', Auth::id()))],
        ]);

        // categories posted
        $posted = collect($request->input('categories', []))
            ->map(fn($r) => [
                'id'        => $r['id'] ?? null,
                'name'      => trim((string)($r['name'] ?? '')),
                'price'     => (float)($r['price'] ?? 0),
                'capacity'  => isset($r['capacity']) && $r['capacity'] !== '' ? (int)$r['capacity'] : null,
                'is_active' => array_key_exists('is_active', $r) ? (bool)$r['is_active'] : true,
                'sort'      => (int)($r['sort'] ?? 0),
            ])
            ->filter(fn($r) => $r['name'] !== '' && $r['price'] > 0)
            ->values();

        $hasCats      = $posted->isNotEmpty();
        $isPaidLegacy = !$hasCats && (float)($validated['ticket_cost'] ?? 0) > 0;
        $isPaid       = $hasCats || $isPaidLegacy;

        $currency = strtoupper($validated['ticket_currency'] ?? '');
        if ($isPaid) {
            $request->validate([
                'ticket_currency' => 'required|string|in:GBP,USD,CAD,AUD,INR,NGN,KES,GHS,EUR|size:3',
                'payout_method_id' => ['required','integer', Rule::exists('user_payout_methods', 'id')->where(fn ($q) => $q->where('user_id', Auth::id()))],
                'fee_mode'        => 'required|in:absorb,pass',
            ]);
            $currency = strtoupper((string)$request->input('ticket_currency'));
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
            $currency = $currency ?: ($event->ticket_currency ?: 'GBP');
        }

        if ($request->hasFile('avatar')) {
            if ($event->avatar_url) Storage::disk('public')->delete($event->avatar_url);
            $event->avatar_url = $request->file('avatar')->store('avatars', 'public');
        }
        if ($request->hasFile('banner')) {
            if ($event->banner_url) Storage::disk('public')->delete($event->banner_url);
            $event->banner_url = $request->file('banner')->store('banners', 'public');
        }

        $tagsArray = is_array($validated['tags'] ?? null) ? $validated['tags'] : [];

        $event->name             = $validated['name'];
        $event->organizer_id = $validated['organizer_id'];
        $event->category         = $validated['category'] ?? null;
        $event->tags             = json_encode($tagsArray);
        $event->location         = $validated['location'] ?? null;
        $event->description      = $validated['description'] ?? null;
        $event->ticket_currency  = $currency;
        $event->ticket_cost      = $hasCats ? 0 : ($validated['ticket_cost'] ?? 0);
        $event->payout_method_id = $isPaid ? ($request->input('payout_method_id') ?? null) : null;
        $event->fee_mode         = $isPaid ? ($request->input('fee_mode') ?: 'absorb') : 'absorb';
        $event->fee_bps          = 590; // default
        $event->save();

        // Sessions upsert
        $sessions = $request->input('sessions', []);
        foreach ($sessions as $row) {
            $name   = trim($row['name'] ?? '');
            $date   = $row['date'] ?? null;
            $time   = $row['time'] ?? null;
            $delete = isset($row['_delete']) && (int)$row['_delete'] === 1;
            $id     = $row['id'] ?? null;
            $dt = ($date && $time) ? Carbon::parse("{$date} {$time}") : null;

            if ($id) {
                $session = EventSession::where('event_id', $event->id)->where('id', $id)->first();
                if (!$session) continue;
                if ($delete) { $session->delete(); continue; }
                $session->session_name = $name ?: $session->session_name;
                if ($dt) $session->session_date = $dt;
                $session->save();
            } else {
                if ($delete || !$name || !$dt) continue;
                EventSession::create([
                    'event_id'     => $event->id,
                    'session_name' => $name,
                    'session_date' => $dt,
                ]);
            }
        }

        // Categories upsert/delete
        $keepIds = [];
        foreach ($posted as $row) {
            $payload = $row; unset($payload['id']);
            if (!empty($row['id'])) {
                $cat = $event->categories()->whereKey($row['id'])->first();
                if ($cat) { $cat->update($payload); $keepIds[] = $cat->id; continue; }
            }
            $cat = $event->categories()->create($payload);
            $keepIds[] = $cat->id;
        }
        $event->categories()->whereNotIn('id', $keepIds)->delete();

        return redirect()->route('dashboard')->with('success', 'Event updated successfully!');
    }

    public function show(Event $event)
    {
        $event->load('organizer'); // Add this line
        $activeCats = $event->categories()->where('is_active', true)->orderBy('sort')->orderBy('id')->get();
        $min = $activeCats->min('price');
        $max = $activeCats->max('price');

        return view('events.show', [
            'event'      => $event,
            'activeCats' => $activeCats,
            'minPrice'   => $min,
            'maxPrice'   => $max,
        ]);
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
        return view('events.edit', compact('event') + ['payoutsByCountry' => $payouts->toArray()]);
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
