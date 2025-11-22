@php
    use Illuminate\Support\Carbon;

    $image = $event->banner_url
        ? asset('storage/' . $event->banner_url)
        : ($event->avatar_url ? asset('storage/' . $event->avatar_url) : null);

    $activeCats = collect($event->categories ?? []);
    $paidPrices = $activeCats->pluck('price')->map(fn($p) => (float) $p)->filter(fn($p) => $p > 0);
    $isFree     = $paidPrices->isEmpty();
    $min        = $paidPrices->min();
    $max        = $paidPrices->max();

    $cur = strtoupper($event->ticket_currency ?? 'GBP');
    $symbols = [
        'GBP'=>'£','USD'=>'$','EUR'=>'€','NGN'=>'₦','KES'=>'KSh','GHS'=>'₵','ZAR'=>'R',
        'CAD'=>'$','AUD'=>'$','NZD'=>'$','INR'=>'₹','JPY'=>'¥','CNY'=>'¥'
    ];
    $sym = $symbols[$cur] ?? '';

    $priceLabel = $isFree
        ? 'Free'
        : ($min == $max
            ? (($sym ?: $cur.' ') . number_format($min, 2))
            : (($sym ?: $cur.' ') . number_format($min, 2) . '–' . ($sym ?: $cur.' ') . number_format($max, 2)));

    // -------- NEW: pick the next upcoming session date --------
    $sessions = $event->sessions ?? collect();
    $now      = Carbon::now();

    // earliest session that is now or in the future
    $nextSession = $sessions
        ->filter(fn ($s) => $s->session_date && Carbon::parse($s->session_date)->gte($now))
        ->sortBy('session_date')
        ->first();

    // if no future sessions, fall back to the latest past one
    if (! $nextSession && $sessions->isNotEmpty()) {
        $nextSession = $sessions->sortByDesc('session_date')->first();
    }

    $nextDate = $nextSession ? Carbon::parse($nextSession->session_date) : null;
@endphp

<a href="{{ route('events.show', $event) }}"
   aria-label="{{ $event->name }}"
   class="block group focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-2xl">
    <div class="relative h-full bg-white rounded-2xl overflow-hidden shadow-sm ring-1 ring-gray-200
                transition group-hover:shadow-md group-hover:ring-gray-300 group-active:scale-[.99]">

        {{-- Media --}}
        <div class="relative">
            @if ($image)
                <img src="{{ $image }}" alt="{{ $event->name }}" class="h-40 w-full object-cover" loading="lazy" decoding="async">
            @else
                <div class="h-40 w-full bg-gradient-to-br from-slate-200 to-slate-100 flex items-center justify-center">
                    <span class="text-slate-500 text-sm">No image</span>
                </div>
            @endif

            @if ($event->is_promoted ?? false)
                <span class="absolute top-3 left-3 bg-amber-500/95 text-white text-xs font-semibold px-2.5 py-1 rounded-md shadow">
                    Promoted
                </span>
            @endif

            <span class="absolute top-3 right-3 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                         {{ $isFree ? 'bg-emerald-500 text-white' : 'bg-black/80 text-white' }}">
                {{ $priceLabel }}
            </span>
        </div>

        {{-- Body --}}
        <div class="p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-gray-900 line-clamp-2 group-hover:underline underline-offset-2">
                        {{ $event->name }}
                    </h3>
                    @if($event->category)
                        <p class="text-xs text-gray-500 mt-0.5">{{ $event->category }}</p>
                    @endif
                </div>
            </div>

            @if ($event->location)
                <div class="mt-2 flex items-center text-sm text-gray-600 gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C8.686 2 6 4.686 6 8c0 4.418 6 12 6 12s6-7.582 6-12c0-3.314-2.686-6-6-6zm0 8.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/>
                    </svg>
                    <span class="line-clamp-1">{{ $event->location }}</span>
                </div>
            @endif

            <div class="mt-1 flex items-center text-sm text-gray-600 gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M7 2a1 1 0 0 1 1 1v1h8V3a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v3H3V6a2 2 0 0 1 2-2h1V3a1 1 0 0 1 1-1z"/>
                    <path d="M3 10h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8z"/>
                </svg>
                @if ($nextDate)
                    <span>{{ $nextDate->format('D, d M Y · g:ia') }}</span>
                @else
                    <span>No sessions yet</span>
                @endif
            </div>
        </div>
    </div>
</a>
