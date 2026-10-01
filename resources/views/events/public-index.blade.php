<x-app-layout>
<link rel="stylesheet" href="{{ asset('css/eventib-home.css') }}?v=20261001-4">
<div class="ev-home">
<div class="ev-wrap">
    <section class="ev-hero" aria-labelledby="home-heading">
        <img class="ev-hero-photo" src="{{ asset('images/eventib-community-hero.png') }}" alt="" width="1672" height="941" fetchpriority="high" decoding="async">
        <div class="ev-hero-content">
            <p class="ev-eyebrow">GOOD PLANS START HERE</p>
            <h1 id="home-heading">Find your next<br><em>good time.</em></h1>
            <p class="ev-copy">Something to do. People to meet.<br>A reason to get together.</p>
            <a href="#discover-events" class="ev-hero-link">Discover events <span aria-hidden="true">→</span></a>
        </div>
    </section>
    <form action="{{ route('events.find') }}" class="ev-search" role="search" aria-label="Find an event">
        <div><label for="home-search">What would you like to do?</label><input id="home-search" name="q" value="{{ $q }}" placeholder="Search events or interests" maxlength="150"></div>
        <div><label for="home-location">Where?</label><input id="home-location" name="loc" value="{{ $loc }}" placeholder="City or venue" maxlength="150"></div>
        <div><label for="home-date">When?</label><select id="home-date" name="when"><option value="">Any time</option><option value="today">Today</option><option value="weekend">This weekend</option><option value="week">Next 7 days</option></select></div>
        <button class="ev-button">Find events <span aria-hidden="true">→</span></button>
    </form>
    <section id="discover-events" class="ev-section ev-discover" aria-labelledby="discover-heading">
        <div class="ev-section-head"><div><h2 id="discover-heading">Find something you’ll love</h2><p class="ev-discovery-copy">Explore what’s coming up on Eventib.</p></div><a class="ev-saved-link" href="{{ route('discovery.saved') }}">♡ Saved plans</a></div>
        <form method="GET" action="{{ url('/') }}#discover-events" class="ev-filter-form">
            @if($q)<input type="hidden" name="q" value="{{ $q }}">@endif
            @if($loc)<input type="hidden" name="loc" value="{{ $loc }}">@endif
            <div class="ev-categories" role="group" aria-label="Filter events by category">
                <button class="ev-category {{ $homeCategory === '' ? 'is-active' : '' }}" name="category" value="" aria-pressed="{{ $homeCategory === '' ? 'true' : 'false' }}">All events</button>
                @foreach($homeCategories as $homeCat)<button class="ev-category {{ $homeCategory === $homeCat->category ? 'is-active' : '' }}" name="category" value="{{ $homeCat->category }}" aria-pressed="{{ $homeCategory === $homeCat->category ? 'true' : 'false' }}">{{ $homeCat->category }} <span>{{ $homeCat->event_count }}</span></button>@endforeach
            </div>
            <details class="ev-more-filters"><summary>More filters <span aria-hidden="true">+</span></summary>
            <div class="ev-filter-row"><div><label for="home-when">When</label><select id="home-when" name="when"><option value="">Any time</option><option value="today" @selected($homeWhen==='today')>Today</option><option value="weekend" @selected($homeWhen==='weekend')>This weekend</option><option value="week" @selected($homeWhen==='week')>Next 7 days</option></select></div><div><label for="home-price">Price</label><select id="home-price" name="price"><option value="">All prices</option><option value="free" @selected($homePrice==='free')>Free events</option><option value="paid" @selected($homePrice==='paid')>Paid events</option></select></div><button class="ev-button" name="category" value="{{ $homeCategory }}">Show events</button></div>
            </details>
        </form>
        @if($homeCategory || $homeWhen || $homePrice || $q || $loc)<div class="ev-filter-status"><span>Showing {{ $homeCategory ?: 'all categories' }}{{ $homePrice ? ' · '.ucfirst($homePrice).' events' : '' }}{{ $homeWhen ? ' · '.(['today'=>'Today','weekend'=>'This weekend','week'=>'Next 7 days'][$homeWhen]) : '' }}{{ $loc ? ' · '.$loc : '' }}{{ $q ? ' · Search: '.$q : '' }}</span><a href="{{ url('/') }}#discover-events">Clear filters</a></div>@endif
    </section>
    @if($featured->isNotEmpty())
    <section class="ev-section">
        <div class="ev-section-head"><h2>In the spotlight</h2><span class="text-sm text-gray-500">Promoted events</span></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">@foreach($featured as $event) @include('events.partials._event_card') @endforeach</div>
        <div class="mt-6">{{ $featured->withQueryString()->links() }}</div>
    </section>
    @endif
    <section class="ev-section">
        <div class="ev-section-head"><h2>{{ $homeCategory ? $homeCategory . ' events' : 'Upcoming events' }}</h2><a href="{{ route('events.find') }}" class="text-sm font-semibold">Explore all →</a></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($upcoming as $event) @include('events.partials._event_card')
            @empty <div class="ev-empty sm:col-span-2"><h3 class="text-xl font-bold">No events match this selection yet.</h3><p class="mt-2 text-gray-600">Choose another category or date, or clear your filters to see all upcoming events.</p><a class="ev-button mt-4" href="{{ url('/') }}#discover-events">View all upcoming events</a></div> @endforelse
        </div>
        <div class="mt-6">{{ $upcoming->withQueryString()->links() }}</div>
    </section>
    <section class="ev-section"><div class="ev-panel"><div><p class="ev-eyebrow" style="color:#d8ef88">You bring the idea. We bring the tools.</p><h2>Give people a reason<br>to come together.</h2><p>From the first invite to the final check-in. Create your event, sell tickets and give your community something to share.</p></div><a class="ev-button" href="{{ route('events.create') }}">Let’s make it happen ↗</a></div></section>
    @if($sponsorSkin && $sponsorLogoUrl)
    <section class="ev-section"><p class="ev-eyebrow mb-4">Our sponsor</p>@if($sponsorBgUrl)<img src="{{ $sponsorBgUrl }}" alt="" class="w-full rounded-2xl mb-4" style="max-height:240px;object-fit:cover" loading="lazy">@endif
    @if($sponsorSkin->website_url && preg_match('/^https?:\/\//i', $sponsorSkin->website_url))<a href="{{ $sponsorSkin->website_url }}" rel="noopener noreferrer">@endif
    <img src="{{ $sponsorLogoUrl }}" alt="{{ $sponsorSkin->name }}" style="max-height:90px;max-width:240px">
    @if($sponsorSkin->website_url && preg_match('/^https?:\/\//i', $sponsorSkin->website_url))</a>@endif</section>
    @endif
    @if(!$homeCategory && !$homeWhen && !$homePrice && $past->isNotEmpty())
    <section class="ev-section"><div class="ev-section-head"><h2>Previously on Eventib</h2><a href="{{ route('events.past') }}">Past events →</a></div><div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">@foreach($past->take(3) as $event) @include('events.partials._event_card') @endforeach</div></section>
    @endif
</div>
</div>
</x-app-layout>
