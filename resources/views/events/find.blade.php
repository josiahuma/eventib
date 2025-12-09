{{-- resources/views/events/find.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Find events
        </h2>
    </x-slot>

    @php
        $f = $filters ?? [];
        $q        = $f['q']        ?? '';
        $loc      = $f['loc']      ?? '';
        $price    = $f['price']    ?? '';
        $category = $f['category'] ?? '';
        $when     = $f['when']     ?? '';
    @endphp

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Filter bar --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
            <form id="filters-form" method="GET" action="{{ route('events.find') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">

                    {{-- Search --}}
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 tracking-wide mb-1">
                            Search
                        </label>
                        <input
                            type="text"
                            name="q"
                            value="{{ $q }}"
                            placeholder="Event name, organiser, tag…"
                            class="block w-full border border-gray-300 rounded-none px-3 py-2 text-sm
                                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                    </div>

                    {{-- Location + geolocation --}}
                    <div class="md:col-span-2">
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-700 tracking-wide mb-1">
                                    Location
                                </label>
                                <input
                                    id="filter-loc"
                                    type="text"
                                    name="loc"
                                    value="{{ $loc }}"
                                    placeholder="City, region or venue"
                                    class="block w-full border border-gray-300 rounded-none px-3 py-2 text-sm
                                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                >
                            </div>
                            <button
                                type="button"
                                id="use-location"
                                class="inline-flex items-center whitespace-nowrap px-3 py-2 text-xs font-medium
                                       border border-gray-300 rounded-none text-gray-700 hover:bg-gray-50 mt-[22px]"
                            >
                                Use my location
                            </button>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-500">
                            We’ll ask your browser for permission and try to fill in your city automatically.
                        </p>
                    </div>

                    {{-- Date --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 tracking-wide mb-1">
                            Date
                        </label>
                        <select
                            name="when"
                            class="block w-full border border-gray-300 rounded-none px-3 py-2 text-sm
                                   bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">Any time</option>
                            <option value="today"    @selected($when==='today')>Today</option>
                            <option value="tomorrow" @selected($when==='tomorrow')>Tomorrow</option>
                            <option value="weekend"  @selected($when==='weekend')>This weekend</option>
                            <option value="week"     @selected($when==='week')>Next 7 days</option>
                            <option value="month"    @selected($when==='month')>Next 30 days</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-4 gap-3 pt-1">

                    {{-- Category --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 tracking-wide mb-1">
                            Category
                        </label>
                        <select
                            name="category"
                            class="block w-full border border-gray-300 rounded-none px-3 py-2 text-sm
                                   bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">All categories</option>
                            @foreach($cats as $cat)
                                <option value="{{ $cat }}" @selected($category===$cat)>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Price --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 tracking-wide mb-1">
                            Price
                        </label>
                        <select
                            name="price"
                            class="block w-full border border-gray-300 rounded-none px-3 py-2 text-sm
                                   bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">Any</option>
                            <option value="free" @selected($price==='free')>Free</option>
                            <option value="paid" @selected($price==='paid')>Paid</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2 md:col-span-2 flex items-end justify-end gap-2">
                        <a href="{{ route('events.find') }}"
                           class="inline-flex items-center px-3 py-2 text-xs font-medium border border-gray-300
                                  rounded-none text-gray-700 hover:bg-gray-50">
                            Reset
                        </a>
                        <button
                            type="submit"
                            class="inline-flex items-center px-5 py-2 text-sm font-semibold rounded-none
                                   bg-indigo-600 text-white hover:bg-indigo-700"
                        >
                            Apply filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Results --}}
        <div class="space-y-3">
            <div class="flex items-center justify-between text-sm text-gray-600">
                <div>
                    Showing
                    <span class="font-semibold">{{ $events->total() }}</span>
                    event{{ $events->total() === 1 ? '' : 's' }}
                    @if($loc)
                        near <span class="font-semibold">{{ $loc }}</span>
                    @endif
                </div>
                @if($events->hasPages())
                    <div class="hidden sm:block">
                        {{ $events->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>

            @if($events->count() === 0)
                <div class="rounded-2xl border border-gray-200 bg-white p-6 text-sm text-gray-600">
                    No events match these filters yet. Try clearing a filter or changing the date range.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($events as $event)
                        @php
                            $firstSession = $event->sessions->sortBy('session_date')->first();
                            $date = $firstSession ? \Carbon\Carbon::parse($firstSession->session_date) : null;

                            $cats = $event->categories ?? collect();
                            $hasCats = $cats->count() > 0;
                            $currency = strtoupper($event->ticket_currency ?? 'GBP');
                            $symbols = [
                                'GBP'=>'£','USD'=>'$','EUR'=>'€','NGN'=>'₦','KES'=>'KSh','GHS'=>'₵','ZAR'=>'R',
                                'CAD'=>'$','AUD'=>'$','NZD'=>'$','INR'=>'₹','JPY'=>'¥','CNY'=>'¥'
                            ];
                            $sym = $symbols[$currency] ?? '';

                            $min = $hasCats ? (float) $cats->min('price') : null;
                            $max = $hasCats ? (float) $cats->max('price') : null;
                            $unit = (float) ($event->ticket_cost ?? 0);
                            $mode = $hasCats ? 'cats' : ($unit > 0 ? 'single' : 'free');

                            $img = $event->banner_url
                                ? asset('storage/' . $event->banner_url)
                                : ($event->avatar_url ? asset('storage/' . $event->avatar_url) : null);
                        @endphp

                        <a href="{{ route('events.show', $event) }}"
                           class="flex flex-col border border-gray-200 rounded-2xl bg-white hover:shadow-md transition-shadow">
                            @if($img)
                                <div class="h-32 w-full overflow-hidden bg-gray-100">
                                    <img src="{{ $img }}" alt="{{ $event->name }}"
                                         class="h-full w-full object-cover">
                                </div>
                            @endif
                            <div class="flex-1 p-4 space-y-2">
                                @if($date)
                                    <div class="text-xs font-medium text-indigo-600 uppercase tracking-wide">
                                        {{ $date->format('D, d M · g:ia') }}
                                    </div>
                                @endif
                                <div class="text-sm font-semibold text-gray-900 line-clamp-2">
                                    {{ $event->name }}
                                </div>
                                @if($event->location)
                                    <div class="text-xs text-gray-600">
                                        {{ $event->location }}
                                    </div>
                                @endif
                                <div class="mt-2 flex items-center justify-between text-xs">
                                    <span class="inline-flex items-center px-2 py-1 border border-gray-200 rounded-none text-gray-700">
                                        @if($mode === 'cats')
                                            @if($min === $max)
                                                {{ $sym }}{{ number_format($min, 2) }}
                                            @else
                                                {{ $sym }}{{ number_format($min, 2) }}–{{ $sym }}{{ number_format($max, 2) }}
                                            @endif
                                        @elseif($mode === 'single')
                                            {{ $sym }}{{ number_format($unit, 2) }}
                                        @else
                                            Free
                                        @endif
                                    </span>
                                    @if($event->category)
                                        <span class="text-[11px] text-gray-500">
                                            {{ $event->category }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-4 sm:hidden">
                    {{ $events->onEachSide(1)->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Simple geolocation helper using OpenStreetMap Nominatim --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const btn = document.getElementById('use-location');
            const locInput = document.getElementById('filter-loc');
            const form = document.getElementById('filters-form');

            if (!btn || !locInput || !form) return;

            btn.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    alert('Location is not supported in this browser.');
                    return;
                }

                btn.disabled = true;
                btn.textContent = 'Locating…';

                navigator.geolocation.getCurrentPosition(async (pos) => {
                    try {
                        const { latitude, longitude } = pos.coords;
                        const res = await fetch(
                            `https://nominatim.openstreetmap.org/reverse?lat=${latitude}&lon=${longitude}&format=json`
                        );
                        const data = await res.json();
                        const addr = data.address || {};
                        const city = addr.city || addr.town || addr.village || addr.county || '';
                        const country = addr.country || '';

                        locInput.value = [city, country].filter(Boolean).join(', ');
                        btn.textContent = 'Use my location';
                        btn.disabled = false;

                        form.submit();
                    } catch (e) {
                        console.error(e);
                        btn.textContent = 'Use my location';
                        btn.disabled = false;
                        alert('Sorry, we could not detect your city. You can type it manually.');
                    }
                }, (err) => {
                    console.warn(err);
                    btn.textContent = 'Use my location';
                    btn.disabled = false;
                    alert('Location permission was denied. You can type your city manually.');
                });
            });
        });
    </script>
</x-app-layout>
