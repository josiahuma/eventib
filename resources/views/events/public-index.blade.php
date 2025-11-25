{{-- resources/views/events/public-index.blade.php --}}
<x-app-layout :useSponsorSkin="(bool) $sponsorSkin">
    {{-- ===== Header with sleek modern search ===== --}}
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 w-full">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight flex-shrink-0">
                Discover Events
            </h2>

            <form action="{{ route('homepage') }}" method="GET" class="flex-1 w-full">
                <div
                    class="relative flex items-center bg-gray-50 border rounded-full shadow-sm
                        hover:shadow-md transition focus-within:ring-2 focus-within:ring-indigo-500"
                >
                    {{-- Left search icon (inside input area) --}}
                    <div class="pl-4 flex items-center text-gray-400 pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1010.5 3a7.5 7.5 0 006.15 13.65z" />
                        </svg>
                    </div>

                    {{-- Text input --}}
                    <input
                        id="home-where"
                        type="text"
                        name="q"
                        value="{{ $q ?? '' }}"
                        placeholder="Where? (city, venue, area)"
                        class="flex-1 bg-transparent border-0 text-[15px] text-gray-700
                            placeholder-gray-400 focus:ring-0 px-3 py-2 pr-14"
                        autocomplete="off"
                    />

                    {{-- BLUE ROUND SUBMIT BUTTON --}}
                    <button
                        type="submit"
                        class="absolute inset-y-0 right-2 my-auto flex items-center justify-center
                            h-9 w-9 rounded-full bg-indigo-600 text-white
                            hover:bg-indigo-700 focus:outline-none focus:ring-2
                            focus:ring-offset-1 focus:ring-indigo-500"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1010.5 3a7.5 7.5 0 006.15 13.65z" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </x-slot>


    {{-- ===== Page wrapper (no background colour changes here) ===== --}}
    <div class="relative min-h-screen">
        {{-- Desktop sponsor “skin” only on the sides, centre left alone --}}
        @if ($sponsorSkin && $sponsorBgUrl)
            <div class="hidden lg:block fixed inset-y-0 left-0 right-0 z-0">
                <div class="h-full w-full flex justify-between">
                    {{-- Left sponsor gutter (clickable) --}}
                    <a href="{{ $sponsorSkin->website_url }}"
                    target="_blank"
                    rel="noopener"
                    class="block h-full w-[260px] xl:w-[320px] bg-center bg-cover"
                    style="background-image: url('{{ $sponsorBgUrl }}');">
                    </a>

                    {{-- Centre is intentionally empty so the normal site background shows through --}}
                    <div class="flex-1"></div>

                    {{-- Right sponsor gutter (clickable) --}}
                    <a href="{{ $sponsorSkin->website_url }}"
                    target="_blank"
                    rel="noopener"
                    class="block h-full w-[260px] xl:w-[320px] bg-center bg-cover"
                    style="background-image: url('{{ $sponsorBgUrl }}');">
                    </a>
                </div>
            </div>
        @endif


        {{-- MAIN CONTENT --}}
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8
                    @if($sponsorSkin && $sponsorBgUrl) text-slate-50 @else text-gray-900 @endif">


            {{-- ===== Sponsor strip (top bar) ===== --}}
            @if ($sponsorSkin)
                <div class="mb-6">
                    <div class="w-full bg-[#0A0A0A] text-white py-4 px-6 flex flex-col sm:flex-row items-center justify-between gap-4 border-y-2 border-[#1F1F1F]">

                        {{-- LEFT SIDE — TEXT --}}
                        <div class="flex items-center gap-4">
                            {{-- Optional Logo --}}
                            @if ($sponsorLogoUrl)
                                <img src="{{ $sponsorLogoUrl }}" 
                                    alt="{{ $sponsorSkin->name }}" 
                                    class="h-10 w-auto object-contain">
                            @endif

                            <div class="flex flex-col leading-tight">
                                <span class="text-xs tracking-wider text-gray-300">
                                    GET TICKETS FOR:
                                </span>

                                <a href="{{ $sponsorSkin->website_url }}" class="text-xl font-extrabold uppercase text-white">
                                    {{ $sponsorSkin->name }}
                                </a>
                            </div>
                        </div>

                        {{-- RIGHT SIDE — BUTTON --}}
                        @if ($sponsorSkin->website_url)
                            <a href="{{ $sponsorSkin->website_url }}" 
                            target="_blank" 
                            rel="noopener"
                            class="text-[#FFD43B] font-bold uppercase tracking-wide text-sm flex items-center gap-2 hover:text-yellow-300">
                                Ends {{ $sponsorSkin->ends_on ? $sponsorSkin->ends_on->format('F j, Y') : 'TBA' }}
                                {{-- Right arrow icon --}}
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" 
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        @endif
                    </div>
                </div>
            @endif



            {{-- ===== Collage hero (admin slides) ===== --}}
            @php
                $slideItems = collect($slides ?? [])
                    ->map(function ($s) {
                        $src = $s->image_path ? asset('storage/'.$s->image_path) : null;
                        if (! $src) return null;
                        return [
                            'src'   => $src,
                            'href'  => $s->link_url ?: null,
                            'title' => $s->title ?: '',
                        ];
                    })
                    ->filter()
                    ->unique('src')
                    ->values();
                $N = $slideItems->count();
            @endphp

            <style>
                .tile {
                    flex: 0 0 auto;
                    height: 396.39px;
                    width: 297.29px;
                    overflow: hidden;
                    border-radius: .65rem;
                    box-shadow: 0 4px 16px rgba(0,0,0,.22);
                    outline: 1px solid rgba(0,0,0,.06);
                    background: #f3f4f6;
                }
                .tile > img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    display: block;
                }
            </style>

            <section
                x-data="{
                    speed: 0.55,
                    nudge: 320,
                    offset: 0,
                    segW: 0,
                    playing: true,
                    dragging: false,
                    startX: 0,
                    startOffset: 0,

                    init () {
                        this.$nextTick(() => {
                            this.measure();
                            window.addEventListener('resize', () => this.measure(), { passive: true });
                            this.$root.querySelectorAll('img[data-collage]').forEach(img => {
                                img.addEventListener('load', () => this.measure(), { once: true });
                            });
                            this.bindDrag();
                            const loop = () => {
                                if (this.playing && !this.dragging && this.segW > 0) {
                                    this.offset -= this.speed;
                                    if (-this.offset >= this.segW) this.offset += this.segW;
                                    if (this.$refs.track) this.$refs.track.style.transform = `translateX(${this.offset}px)`;
                                }
                                requestAnimationFrame(loop);
                            };
                            loop();
                        });
                    },

                    measure () {
                        const track = this.$refs.track;
                        if (!track) return;
                        const children = Array.from(track.children);
                        const half = Math.floor(children.length / 2);
                        let w = 0;
                        for (let i = 0; i < half; i++) {
                            const el = children[i];
                            const r  = el.getBoundingClientRect();
                            const mr = parseFloat(getComputedStyle(el).marginRight || '0');
                            w += r.width + mr;
                        }
                        this.segW = w || track.getBoundingClientRect().width / 2;
                        if (this.segW > 0) {
                            while (this.offset <= -this.segW) this.offset += this.segW;
                            while (this.offset > 0) this.offset -= this.segW;
                        }
                        if (this.$refs.track) this.$refs.track.style.transform = `translateX(${this.offset}px)`;
                    },

                    pause () { this.playing = false; },
                    play  () { this.playing = true;  },

                    prev () { this.nudgeBy(+this.nudge); },
                    next () { this.nudgeBy(-this.nudge); },
                    nudgeBy (dx) {
                        this.offset += dx;
                        if (this.segW > 0) {
                            while (this.offset <= -this.segW) this.offset += this.segW;
                            while (this.offset > 0) this.offset -= this.segW;
                        }
                        if (this.$refs.track) this.$refs.track.style.transform = `translateX(${this.offset}px)`;
                    },

                    bindDrag () {
                        const vp = this.$refs.viewport;
                        if (!vp) return;

                        const down = (e) => {
                            this.dragging = true;
                            this.playing  = false;
                            this.startX = (e.touches ? e.touches[0].clientX : e.clientX);
                            this.startOffset = this.offset;
                        };
                        const move = (e) => {
                            if (!this.dragging) return;
                            const x = (e.touches ? e.touches[0].clientX : e.clientX);
                            const dx = x - this.startX;
                            this.offset = this.startOffset + dx;
                            if (this.segW > 0) {
                                if (this.offset <= -this.segW) this.offset += this.segW;
                                if (this.offset > 0) this.offset -= this.segW;
                            }
                            if (this.$refs.track) this.$refs.track.style.transform = `translateX(${this.offset}px)`;
                        };
                        const up = () => {
                            if (!this.dragging) return;
                            this.dragging = false;
                            this.playing  = true;
                        };

                        vp.addEventListener('pointerdown', down);
                        window.addEventListener('pointermove', move, { passive: true });
                        window.addEventListener('pointerup',   up);

                        vp.addEventListener('touchstart', down, { passive: true });
                        window.addEventListener('touchmove',  move, { passive: true });
                        window.addEventListener('touchend',   up);
                    },
                }"
                x-init="init()"
                @mouseenter="pause()"
                @mouseleave="play()"
                class="rounded-2xl overflow-hidden ring-1 ring-gray-200 mb-10 relative bg-white"
            >
                <div class="pointer-events-none absolute inset-y-0 left-0 w-10 bg-gradient-to-r from-white to-transparent z-10"></div>
                <div class="pointer-events-none absolute inset-y-0 right-0 w-10 bg-gradient-to-l from-white to-transparent z-10"></div>

                @if ($N === 0)
                    <div class="h-40 sm:h-48 lg:h-[396.39px] flex items-center justify-center text-gray-500 text-sm">
                        Add slides in <a href="{{ route('admin.slides.index') }}" class="ml-1 underline">Admin → Homepage Slides</a>
                    </div>
                @else
                    <div class="overflow-hidden select-none" x-ref="viewport" style="touch-action: pan-y;">
                        <div class="py-4 ps-4">
                            <div class="inline-flex items-stretch gap-3 will-change-transform"
                                 x-ref="track"
                                 style="transform: translateX(0);">
                                @foreach ($slideItems as $it)
                                    @php
                                        $href = $it['href'] ?: 'javascript:void(0)';
                                        $target = $it['href'] ? '_blank' : null;
                                    @endphp
                                    <a class="tile block"
                                       href="{{ $href }}"
                                       @if($target) target="{{ $target }}" rel="noopener" @endif
                                       aria-label="{{ $it['title'] ?: 'Slide' }}">
                                        <img src="{{ $it['src'] }}"
                                             alt="{{ $it['title'] }}"
                                             data-collage loading="lazy" decoding="async">
                                    </a>
                                @endforeach
                                @foreach ($slideItems as $it)
                                    @php
                                        $href = $it['href'] ?: 'javascript:void(0)';
                                        $target = $it['href'] ? '_blank' : null;
                                    @endphp
                                    <a class="tile block"
                                       href="{{ $href }}"
                                       @if($target) target="{{ $target }}" rel="noopener" @endif
                                       aria-label="{{ $it['title'] ?: 'Slide' }}">
                                        <img src="{{ $it['src'] }}"
                                             alt="{{ $it['title'] }}"
                                             data-collage loading="lazy" decoding="async">
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <button type="button"
                            @click="prev()"
                            class="absolute left-3 top-1/2 -translate-y-1/2 inline-flex items-center justify-center
                                   h-11 w-11 rounded-full bg-white/95 shadow ring-1 ring-gray-200 hover:bg-white z-20">
                        <svg class="h-5 w-5 text-gray-700" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                        </svg>
                    </button>
                    <button type="button"
                            @click="next()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex items-center justify-center
                                   h-11 w-11 rounded-full bg-white/95 shadow ring-1 ring-gray-200 hover:bg-white z-20">
                        <svg class="h-5 w-5 text-gray-700" viewBox="0 0 24 24" fill="currentColor">
                            <path d="m10 6-1.41 1.41L13.17 12l-4.58 4.59L10 18l6-6z"/>
                        </svg>
                    </button>
                @endif
            </section>

            {{-- ===== Featured / Upcoming / Past sections ===== --}}
            @if ($featured->count())
                <section class="mb-10">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-2xl font-bold text-gray-900">
                            Featured Events
                        </h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        @foreach ($featured as $event)
                            @include('events.partials._event_card', ['event' => $event, 'forceFeatured' => true])
                        @endforeach
                    </div>
                    <div class="mt-6">
                        {{ $featured->withQueryString()->links('pagination::tailwind', ['paginator' => $featured]) }}
                    </div>
                </section>
            @endif

            @if ($upcoming->count())
                <section class="mb-10" x-data="upcomingInfinite()" x-init="init()">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-2xl font-bold text-gray-900">
                            Upcoming Events
                        </h3>
                    </div>

                    <div id="upcoming-grid" x-ref="grid"
                         class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        @foreach ($upcoming as $event)
                            @include('events.partials._event_card', ['event' => $event])
                        @endforeach
                    </div>

                    <div id="upcoming-pagination" x-ref="pagination" class="mt-6">
                        {{ $upcoming->withQueryString()->links('pagination::tailwind', ['paginator' => $upcoming]) }}
                    </div>

                    <div x-ref="sentinel" class="mt-4 flex items-center justify-center text-sm text-gray-400 h-10">
                        <span x-show="loading">Loading more events…</span>
                        <span x-show="!loading && !nextUrl">You’ve reached the end.</span>
                    </div>
                </section>
            @endif

            @if ($past->count())
                <style>
                    #pastRowScroller { scrollbar-width: none; -ms-overflow-style: none; }
                    #pastRowScroller::-webkit-scrollbar { display: none; }
                </style>

                <section class="mb-12">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-2xl font-bold text-gray-900">
                            Past Events
                        </h3>
                        <a href="{{ route('events.past') }}" class="text-indigo-600 hover:text-indigo-800 font-medium text-sm">
                            See all
                        </a>
                    </div>

                    <div class="relative"
                         x-data="{
                            step: 320,
                            atEnd: false,
                            update() {
                                const el = this.$refs.scroller;
                                this.atEnd = (el.scrollLeft + el.clientWidth) >= (el.scrollWidth - 1);
                            },
                            right() {
                                const el = this.$refs.scroller;
                                el.scrollBy({ left: this.step, behavior: 'smooth' });
                                setTimeout(() => this.update(), 300);
                            },
                            init() {
                                this.$nextTick(() => this.update());
                                window.addEventListener('resize', () => this.update(), { passive: true });
                            }
                         }"
                         x-init="init()"
                    >
                        {{-- Gradients back to white, no black tint --}}
                        <div class="pointer-events-none absolute inset-y-0 left-0 w-12 bg-gradient-to-r from-white to-transparent z-10"></div>
                        <div class="pointer-events-none absolute inset-y-0 right-0 w-12 bg-gradient-to-l from-white to-transparent z-10"
                             :class="{ 'opacity-0': atEnd }"></div>

                        <div id="pastRowScroller"
                             x-ref="scroller"
                             class="flex gap-4 overflow-x-auto snap-x snap-mandatory scroll-smooth scrollbar-hide pr-14"
                             @scroll="update()">
                            @foreach ($past as $event)
                                <div class="snap-start shrink-0 w-[260px] sm:w-[300px]">
                                    @include('events.partials._event_card', ['event' => $event])
                                </div>
                            @endforeach
                        </div>

                        <button type="button"
                                @click="right()"
                                class="hidden sm:flex items-center justify-center absolute right-3 top-1/2 -translate-y-1/2 h-12 w-12 rounded-full bg-white shadow ring-1 ring-gray-200 hover:bg-white z-20"
                                :class="{ 'opacity-0 pointer-events-none': atEnd }"
                                aria-label="Scroll right">
                            <svg class="h-6 w-6 text-gray-700" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M10 6l-1.41 1.41L13.17 12l-4.58 4.59L10 18l6-6z"/>
                            </svg>
                        </button>
                    </div>
                </section>
            @endif

            @if (! $featured->count() && ! $upcoming->count() && ! $past->count())
                <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
                    <h3 class="text-lg font-semibold text-gray-800">No events match your search</h3>
                    <p class="mt-1 text-sm text-gray-500">Try a nearby city or different area.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Google Places + infinite scroll JS (unchanged) --}}
    @if (config('services.google.maps_key'))
        <script>
            window.initHomePlaces = function () {
                const input = document.getElementById('home-where');
                if (!input || !window.google || !google.maps || !google.maps.places) return;
                const ac = new google.maps.places.Autocomplete(input, {
                    fields: ['formatted_address','name'],
                    types: ['geocode']
                });
                ac.addListener('place_changed', () => {
                    const p = ac.getPlace();
                    if (!p) return;
                    if (p.formatted_address) input.value = p.formatted_address;
                });
            };
        </script>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('upcomingInfinite', () => ({
                    nextUrl: null,
                    loading: false,
                    observer: null,
                    init() {
                        const nav = this.$refs.pagination;
                        if (nav) {
                            const nextLink =
                                nav.querySelector('a[rel="next"]') ||
                                nav.querySelector('a[aria-label="Next &raquo;"]');
                            this.nextUrl = nextLink ? nextLink.href : null;
                            nav.classList.add('hidden');
                        }
                        const sentinel = this.$refs.sentinel;
                        if (!sentinel) return;
                        this.observer = new IntersectionObserver(
                            (entries) => {
                                entries.forEach(entry => {
                                    if (entry.isIntersecting) this.loadMore();
                                });
                            },
                            { root: null, rootMargin: '0px 0px 200px 0px', threshold: 0.1 }
                        );
                        this.observer.observe(sentinel);
                    },
                    async loadMore() {
                        if (!this.nextUrl || this.loading) return;
                        this.loading = true;
                        try {
                            const res = await fetch(this.nextUrl, {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            });
                            const html = await res.text();
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newGrid = doc.querySelector('#upcoming-grid');
                            const newPagination = doc.querySelector('#upcoming-pagination');
                            if (newGrid) {
                                Array.from(newGrid.children).forEach(child => {
                                    this.$refs.grid.appendChild(child);
                                });
                            }
                            if (newPagination) {
                                const nextLink =
                                    newPagination.querySelector('a[rel="next"]') ||
                                    newPagination.querySelector('a[aria-label="Next &raquo;"]');
                                this.nextUrl = nextLink ? nextLink.href : null;
                            } else {
                                this.nextUrl = null;
                            }
                        } catch (e) {
                            console.error('Failed to load more events', e);
                            this.nextUrl = null;
                        } finally {
                            this.loading = false;
                        }
                    },
                }));
            });
        </script>

        <script src="https://maps.googleapis.com/maps/api/js?key={{ urlencode(config('services.google.maps_key')) }}&libraries=places&callback=initHomePlaces" async defer></script>
    @endif
</x-app-layout>
