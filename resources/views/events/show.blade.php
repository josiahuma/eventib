<x-app-layout>
    @php
        // Choose OG image
        $ogImage = $event->banner_url
            ? asset('storage/' . $event->banner_url)
            : ($event->avatar_url ? asset('storage/' . $event->avatar_url) : asset('images/og-default.jpg'));

        $ogTitle = $event->name;
        $plainDesc = trim(preg_replace('/\s+/', ' ', strip_tags($event->description ?? '')));
        $ogDesc = \Illuminate\Support\Str::limit($plainDesc ?: 'View event details and register.', 160, '…');

        $image = $event->banner_url
            ? asset('storage/' . $event->banner_url)
            : ($event->avatar_url ? asset('storage/' . $event->avatar_url) : null);

        // Currency + symbol
        $cur = strtoupper($event->ticket_currency ?? 'GBP');
        $symbols = [
            'GBP'=>'£','USD'=>'$','EUR'=>'€','NGN'=>'₦','KES'=>'KSh','GHS'=>'₵','ZAR'=>'R',
            'CAD'=>'$','AUD'=>'$','NZD'=>'$','INR'=>'₹','JPY'=>'¥','CNY'=>'¥'
        ];
        $sym = $symbols[$cur] ?? '';

        // ---- PRICE LABEL (use categories if present, otherwise Free) ----
        $hasCats = isset($activeCats) && $activeCats->count() > 0;
        if ($hasCats) {
            $min = isset($minPrice) ? (float)$minPrice : (float)$activeCats->min('price');
            $max = isset($maxPrice) ? (float)$maxPrice : (float)$activeCats->max('price');
            $priceLabel = ($min === $max)
                ? ($sym ? $sym.number_format($min, 2) : ($cur . ' ' . number_format($min, 2)))
                : (($sym ? $sym.number_format($min, 2) : ($cur . ' ' . number_format($min, 2)))
                   . '–' .
                   ($sym ? $sym.number_format($max, 2) : ($cur . ' ' . number_format($max, 2))));
        } else {
            $priceLabel = 'Free';
        }
        $isFree = !$hasCats;

        // Tags
        $tags = is_array($event->tags) ? $event->tags : (json_decode($event->tags ?? '[]', true) ?: []);

        // --- Countdown target: next upcoming session (if any) ---
        $sortedSessions = $event->sessions->sortBy('session_date');
        $nextFuture = $sortedSessions->first(function($s){
            return \Carbon\Carbon::parse($s->session_date)->isFuture();
        });
        $countdownIso = $nextFuture ? \Carbon\Carbon::parse($nextFuture->session_date)->toIso8601String() : null;

        $nextDateForChip = $nextFuture
            ? $nextFuture->session_date
            : optional($sortedSessions->first())->session_date;

        $firstUpcoming = $event->sessions
            ->sortBy('session_date')
            ->first(fn($s) => \Carbon\Carbon::parse($s->session_date)->isFuture());

        $hasUpcoming = !is_null($firstUpcoming);

        $manageUrl = auth()->check()
            ? route('my.tickets')
            : route('events.ticket.find', $event);
    @endphp

    @section('title', $ogTitle)

    @section('meta')
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name', 'ovievent') }}">
        <meta property="og:title" content="{{ $ogTitle }}">
        <meta property="og:description" content="{{ $ogDesc }}">
        <meta property="og:url" content="{{ request()->fullUrl() }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:secure_url" content="{{ $ogImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $ogTitle }}">
        <meta name="twitter:description" content="{{ $ogDesc }}">
        <meta name="twitter:image" content="{{ $ogImage }}">
    @parent
    @endsection

    {{-- Hero --}}
    <div class="w-full bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto">
            <div class="relative rounded-b-2xl overflow-hidden">
                @if ($image)
                    <img src="{{ $image }}" alt="{{ $event->name }}" class="w-full h-[320px] md:h-[420px] object-cover" loading="lazy" decoding="async">
                @else
                    <div class="w-full h-[320px] md:h-[420px] bg-gradient-to-br from-slate-200 to-slate-100"></div>
                @endif

                <div class="absolute top-4 left-4 flex flex-wrap gap-2">
                    @if ($event->category)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white/90 text-gray-800 shadow">
                            {{ $event->category }}
                        </span>
                    @endif
                    @if ($event->is_promoted)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-500 text-white shadow">
                            Featured
                        </span>
                    @endif
                </div>

                <div class="absolute top-4 right-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $isFree ? 'bg-emerald-500' : 'bg-black/80' }} text-white shadow">
                        {{ $priceLabel }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Content --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Left --}}
            <div class="lg:col-span-2 space-y-6">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900">{{ $event->name }}</h1>

                    {{-- Countdown --}}
                    @if($countdownIso)
                        <div
                            x-data="countdown('{{ $countdownIso }}')"
                            x-init="start()"
                            class="mt-4"
                            aria-label="Countdown to event start"
                        >
                            <div class="grid grid-cols-4 gap-2 max-w-xl">
                                <template x-for="label in ['Days','Hours','Minutes','Seconds']" :key="label">
                                    <div class="flex flex-col items-center justify-center border border-gray-300 bg-white px-4 py-3 shadow-sm">
                                        <span class="text-[10px] sm:text-xs tracking-widest uppercase text-gray-500" x-text="label"></span>
                                        <span class="mt-1 text-2xl sm:text-3xl font-bold tabular-nums leading-none"
                                              x-text="label==='Days'?dd:label==='Hours'?hh:label==='Minutes'?mm:ss"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-gray-600">
                        @if ($event->organizer)
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-4 0-8 2-8 6v1h16v-1c0-4-4-6-8-6z"/></svg>
                                Organized by <a href="{{ route('organizers.show', $event->organizer->slug) }}" class="text-indigo-600 hover:underline">{{ $event->organizer->name }}</a>
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Sessions --}}
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Dates & Sessions</h2>
                    @if ($event->sessions->count())
                        <ul class="mt-3 divide-y divide-gray-100">
                            @foreach ($event->sessions as $s)
                                <li class="py-3 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center h-9 w-9 rounded-full bg-indigo-50">
                                            <svg class="h-4 w-4 text-indigo-600" viewBox="0 0 24 24" fill="currentColor"><path d="M7 2a1 1 0 0 1 1 1v1h8V3a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v3H3V6a2 2 0 0 1 2-2h1V3a1 1 0 0 1 1-1z"/><path d="M3 10h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8z"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $s->session_name }}</div>
                                            <div class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($s->session_date)->format('D, d M Y · g:ia') }}</div>
                                        </div>
                                    </div>
                                    <a href="#" class="text-sm text-indigo-600 hover:text-indigo-700">Add to calendar</a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-2 text-sm text-gray-600">Schedule TBA.</p>
                    @endif
                </div>

                {{-- Description --}}
                @if ($event->description)
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                        <h2 class="text-lg font-semibold text-gray-900">About this event</h2>
                        @php
                            function linkify($text) {
                                $pattern = '/(https?:\/\/[^\s<]+)/i';
                                return preg_replace($pattern, '<a href="$1" class="text-indigo-600 underline" target="_blank" rel="noopener">$1</a>', e($text));
                            }
                        @endphp

                        <div class="prose max-w-none mt-2 text-gray-700">
                            {!! nl2br(linkify($event->description)) !!}
                        </div>

                    </div>
                @endif

                {{-- Tags --}}
                @if (!empty($tags))
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                        <h2 class="text-lg font-semibold text-gray-900">Tags</h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($tags as $tag)
                                <span class="px-3 py-1 text-xs rounded-full bg-blue-50 text-blue-700 border border-blue-100">#{{ $tag }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right column (sticky floating ticket card) --}}
            <div class="lg:col-span-1 space-y-6">
                <div class="lg:sticky lg:top-6 z-20">

                {{-- Ticket card --}}
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm text-gray-600">Ticket</div>
                            <div class="text-2xl font-bold text-gray-900">{{ $priceLabel }}</div>
                        </div>
                        @if ($image)
                            <img src="{{ $image }}" alt="" class="h-12 w-12 rounded-lg object-cover">
                        @endif
                    </div>

                    {{-- Register button directly under price --}}
                    @if ($hasUpcoming)
                        <a href="{{ route('events.register', $event) }}"
                        class="mt-5 inline-flex items-center justify-center w-full rounded-lg bg-indigo-600 text-white px-4 py-3 font-semibold text-lg hover:bg-indigo-700">
                            {{ $isFree ? 'Attend' : 'Register' }}
                        </a>
                    @else
                        <span class="mt-5 w-full inline-flex justify-center items-center px-4 py-3 rounded-xl bg-gray-100 text-gray-500 font-medium text-lg cursor-not-allowed">
                            Registration closed
                        </span>
                    @endif

                    {{-- Date & Location --}}
                    <div class="mt-6 space-y-4">
                        {{-- Date --}}
                        <div class="flex items-center gap-2 text-gray-700">
                            <svg class="h-5 w-5 text-gray-500" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M7 2a1 1 0 0 1 1 1v1h8V3a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v3H3V6a2 2 0 0 1 2-2h1V3a1 1 0 0 1 1-1z"/>
                                <path d="M3 10h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8z"/>
                            </svg>
                            @if ($nextDateForChip)
                                <span class="text-base md:text-lg">{{ \Carbon\Carbon::parse($nextDateForChip)->format('d M Y') }}</span>
                            @else
                                <span class="text-base md:text-lg">Dates TBA</span>
                            @endif
                        </div>

                        {{-- Location --}}
                        @if ($event->location)
                            <div class="flex items-start gap-2 text-gray-700">
                                <svg class="h-5 w-5 text-gray-500 mt-0.5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2c-3.866 0-7 3.134-7 7 0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 10a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
                                </svg>
                                <div>
                                    <div class="text-base md:text-lg">{{ $event->location }}</div>
                                    <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($event->location) }}"
                                    target="_blank" rel="noopener"
                                    class="mt-1 inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 text-base md:text-lg font-medium">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M14 3h7v7h-2V6.41l-9.29 9.3-1.42-1.42L17.59 5H14V3z"/>
                                        </svg>
                                        <span>Get Directions</span>
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Secondary actions --}}
                    <div class="mt-6 border-t border-gray-200 pt-4 space-y-3">
                        @auth
                            <a href="{{ route('my.tickets') }}"
                            class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium text-base md:text-lg">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M3 7h18v4a2 2 0 0 1 0 4v4H3v-4a2 2 0 0 1 0-4V7z"/>
                                </svg>
                                <span>Already registered? Manage your booking</span>
                            </a>
                        @else
                            <a href="{{ $manageUrl }}"
                            class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium text-base md:text-lg">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M3 7h18v4a2 2 0 0 1 0 4v4H3v-4a2 2 0 0 1 0-4V7z"/>
                                </svg>
                                <span>Already registered? Manage your booking</span>
                            </a>
                        @endauth

                        @if ($event->avatar_url)
                            <a href="{{ route('events.avatar', $event) }}"
                            class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium text-base md:text-lg">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M4 7a2 2 0 0 1 2-2h2l1.5-2h5L16 5h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7z"/>
                                    <circle cx="12" cy="12" r="3.2"/>
                                </svg>
                                <span>Create Personal Display Picture</span>
                            </a>
                        @endif

                        <button onclick="shareEvent()"
                                class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium text-base md:text-lg w-full text-left">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 3l4 4h-3v6h-2V7H8l4-4z"/>
                                <path d="M5 10h14v10H5z"/>
                            </svg>
                            <span>Share Event</span>
                        </button>
                    </div>
                </div>




                {{-- Organizer card --}}
                <x-organizer-card :organizer="$event->organizer" />

                {{-- Google Ads --}}
                <x-google-ads />
                </div>
            </div>
        </div>
    </div>

    {{-- Alpine countdown helper --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('countdown', (iso) => ({
                target: new Date(iso).getTime(),
                dd: '0', hh: '00', mm: '00', ss: '00',
                timer: null,
                start() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
                tick() {
                    const diff = Math.max(0, this.target - Date.now());
                    const d = Math.floor(diff / 86400000);
                    const h = Math.floor((diff % 86400000) / 3600000);
                    const m = Math.floor((diff % 3600000) / 60000);
                    const s = Math.floor((diff % 60000) / 1000);
                    this.dd = String(d);
                    this.hh = String(h).padStart(2, '0');
                    this.mm = String(m).padStart(2, '0');
                    this.ss = String(s).padStart(2, '0');
                }
            }))
        })
    </script>

    <script>
        function shareEvent() {
            const shareData = {
                title: @json($event->name),
                text: "Check out this event on {{ config('app.name') }}!",
                url: "{{ request()->fullUrl() }}"
            };

            if (navigator.share) {
                navigator.share(shareData).catch(err => console.log("Share cancelled", err));
            } else {
                navigator.clipboard.writeText(shareData.url).then(() => {
                    alert("Event link copied to clipboard!");
                });
            }
        }
    </script>

    {{-- Floating Attend/Register Bar (Always visible, respects closed registrations) --}}
    <div 
        x-data="{ hideNearFooter: false }"
        x-init="
            const footer = document.querySelector('footer');
            if (footer) {
                const observer = new IntersectionObserver((entries) => {
                    hideNearFooter = entries[0].isIntersecting;
                }, { threshold: 0.1 });
                observer.observe(footer);
            }
        "
        :style="hideNearFooter ? 'transform: translateY(120%); opacity: 0;' : 'transform: translateY(0); opacity: 1;'"
        class="fixed bottom-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md shadow-lg border-t border-gray-200 transition-all duration-500 ease-in-out"
    >
        <div class="max-w-5xl mx-auto flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-4">

            {{-- Event Info --}}
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 text-gray-800">
                <span class="text-lg sm:text-xl font-bold text-blue-700 truncate">{{ $event->name }}</span>

                @if($nextDateForChip)
                    <span class="text-sm sm:text-base font-medium text-gray-600">
                        {{ \Carbon\Carbon::parse($nextDateForChip)->format('D, M j · g:ia') }}
                    </span>
                @endif
            </div>

            {{-- Price + Button --}}
            <div class="flex items-center gap-4 shrink-0">
                @if ($isFree)
                    <span class="px-4 py-1.5 bg-emerald-100 text-emerald-700 rounded-full text-base font-semibold">
                        Free
                    </span>
                @else
                    <span class="px-4 py-1.5 bg-gray-100 text-gray-800 rounded-full text-base font-semibold">
                        {{ $priceLabel }}
                    </span>
                @endif

                {{-- Show Attend/Register only if event has upcoming session --}}
                @if ($hasUpcoming)
                    <a href="{{ route('events.register', $event) }}"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-600 text-white font-bold px-6 py-3 text-base sm:text-lg hover:bg-indigo-700 shadow-md transition transform hover:scale-[1.03] active:scale-[0.98]">
                        {{ $isFree ? 'Attend' : 'Register' }}
                    </a>
                @else
                    <span class="inline-flex items-center justify-center gap-2 rounded-full bg-gray-200 text-gray-500 font-bold px-6 py-3 text-base sm:text-lg cursor-not-allowed">
                        Registration closed
                    </span>
                @endif
            </div>
        </div>
    </div>

</x-app-layout>
