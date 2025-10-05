{{-- resources/views/homepage.blade.php --}}
<x-app-layout>
    {{-- ===== Header with compact location search ===== --}}
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                Discover Events
            </h2>

            {{-- Location-only search (keeps using ?q=... so no controller changes) --}}
            <form action="{{ route('homepage') }}" method="GET" class="w-full sm:w-[440px]">
                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 flex items-stretch overflow-hidden">
                    <div class="px-3 flex items-center text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/>
                        </svg>
                    </div>
                    <input
                        id="home-where"
                        type="text"
                        name="q"
                        value="{{ $q ?? '' }}"
                        placeholder="Where? (city, venue, area)"
                        class="flex-1 border-0 focus:ring-0 text-[15px] placeholder-gray-400"
                        autocomplete="off"
                    />
                    <button type="submit" class="px-4 bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                        Search
                    </button>
                </div>
            </form>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- ===== Collage hero (Admin Slides only) ===== --}}
        @php
            // Build clickable items from Admin slides (unique by src)
            $slideItems = collect($slides ?? [])
                ->map(function ($s) {
                    $src = $s->image_path ? asset('storage/'.$s->image_path) : null;
                    if (!$src) return null;
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
            /* Single row at 396.39px tall; explicit width avoids iOS/desktop quirks */
            .tile {
                flex: 0 0 auto;
                height: 396.39px;
                width: 297.29px; /* ~3:4 look; 396.39 * 0.75 */
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
                speed: 0.55,     // px per frame
                nudge: 320,      // px per arrow click
                offset: 0,
                segW: 0,
                playing: true,
                dragging: false,
                startX: 0,
                startOffset: 0,

                init () {
                    this.$nextTick(() => {
                        this.measure();
                        // Re-measure on resize and when any image finishes loading
                        window.addEventListener('resize', () => this.measure(), { passive: true });
                        this.$root.querySelectorAll('img[data-collage]').forEach(img => {
                            img.addEventListener('load', () => this.measure(), { once: true });
                        });
                        this.bindDrag();
                        // Autoplay loop
                        const loop = () => {
                            if (this.playing && !this.dragging && this.segW > 0) {
                                this.offset -= this.speed;
                                if (-this.offset >= this.segW) this.offset += this.segW; // seamless wrap
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
                    // Measure first half precisely (child width + right margin)
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
                    // Normalize offset and paint once
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

                    // Pointer + touch
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
            {{-- soft fade edges --}}
            <div class="pointer-events-none absolute inset-y-0 left-0 w-10 bg-gradient-to-r from-white to-transparent z-10"></div>
            <div class="pointer-events-none absolute inset-y-0 right-0 w-10 bg-gradient-to-l from-white to-transparent z-10"></div>

            @if($N === 0)
                <div class="h-40 sm:h-48 lg:h-[396.39px] flex items-center justify-center text-gray-500 text-sm">
                    Add slides in <a href="{{ route('admin.slides.index') }}" class="ml-1 underline">Admin → Homepage Slides</a>
                </div>
            @else
                <div class="overflow-hidden select-none" x-ref="viewport" style="touch-action: pan-y;">
                    <div class="py-4 ps-4">
                        {{-- Track contains TWO copies -> seamless loop --}}
                        <div class="inline-flex items-stretch gap-3 will-change-transform"
                             x-ref="track"
                             style="transform: translateX(0);">
                            {{-- Segment A (server-rendered so it never disappears) --}}
                            @foreach($slideItems as $it)
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
                            {{-- Segment B (duplicate) --}}
                            @foreach($slideItems as $it)
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

                {{-- Arrows --}}
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

        {{-- ===== Featured ===== --}}
        @if ($featured->count())
            <section class="mb-10">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-3xl font-bold text-gray-900">Featured Events</h3>
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

        {{-- ===== Upcoming ===== --}}
        @if ($upcoming->count())
            <section class="mb-10">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-3xl font-bold text-gray-900">Upcoming Events</h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    @foreach ($upcoming as $event)
                        @include('events.partials._event_card', ['event' => $event])
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $upcoming->withQueryString()->links('pagination::tailwind', ['paginator' => $upcoming]) }}
                </div>
            </section>
        @endif

        {{-- ===== Past ===== --}}
        @if ($past->count())
            <section>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-3xl font-bold text-gray-900">Past Events</h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    @foreach ($past as $event)
                        @include('events.partials._event_card', ['event' => $event])
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $past->withQueryString()->links('pagination::tailwind', ['paginator' => $past]) }}
                </div>
            </section>
        @endif

        @if (!$featured->count() && !$upcoming->count() && !$past->count())
            <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
                <h3 class="text-lg font-semibold text-gray-800">No events match your search</h3>
                <p class="mt-1 text-sm text-gray-500">Try a nearby city or different area.</p>
            </div>
        @endif
    </div>

    {{-- Google Places (optional) for the location input --}}
    @if (config('services.google.maps_key'))
        <script>
            window.initHomePlaces = function () {
                const input = document.getElementById('home-where');
                if (!input || !window.google || !google.maps || !google.maps.places) return;
                const ac = new google.maps.places.Autocomplete(input, {
                    fields: ['formatted_address','name'],
                    types: ['geocode']
                });
                ac.addEventListener('place_changed', () => {
                    const p = ac.getPlace();
                    if (!p) return;
                    if (p.formatted_address) input.value = p.formatted_address;
                });
            };
        </script>
        <script src="https://maps.googleapis.com/maps/api/js?key={{ urlencode(config('services.google.maps_key')) }}&libraries=places&callback=initHomePlaces" async defer></script>
    @endif
</x-app-layout>