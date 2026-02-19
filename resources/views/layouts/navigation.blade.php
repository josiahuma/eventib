<nav
    x-data="{ open:false }"
    @keydown.escape.window="open=false"
    class="fixed top-0 inset-x-0 z-[100] bg-white/90 backdrop-blur border-b border-gray-100"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="h-16 flex items-center justify-between">

            {{-- LEFT: logo + primary nav --}}
            <div class="flex items-center gap-8 min-w-0">
                <a href="{{ route('homepage') }}" class="shrink-0 flex items-center">
                    <x-application-logo class="block h-9 w-auto fill-current text-gray-900" />
                </a>

                {{-- Primary links (desktop) --}}
                <div class="hidden lg:flex items-center gap-6 min-w-0">
                    <a href="{{ route('events.find') }}"
                       class="text-sm font-medium {{ request()->routeIs('events.find') ? 'text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">
                        Find Events
                    </a>

                    <a href="{{ route('how') }}"
                       class="text-sm font-medium {{ request()->routeIs('how') ? 'text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">
                        How it works
                    </a>

                    <a href="{{ route('pricing') }}"
                       class="text-sm font-medium {{ request()->routeIs('pricing') ? 'text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">
                        Pricing
                    </a>

                    <a href="{{ route('about') }}"
                       class="text-sm font-medium {{ request()->routeIs('about') ? 'text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">
                        About
                    </a>

                    <a href="{{ route('contact') }}"
                       class="text-sm font-medium {{ request()->routeIs('contact') ? 'text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">
                        Contact
                    </a>
                </div>
            </div>

            {{-- RIGHT: CTA + auth --}}
            <div class="hidden lg:flex items-center gap-3 shrink-0">

                {{-- Primary CTA (Eventbrite-ish) --}}
                @auth
                    <a href="{{ route('events.create') }}"
                       class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold bg-[#F05537] text-white hover:opacity-95 transition">
                        Create event
                    </a>
                @else
                    <a href="{{ route('events.create') }}"
                       class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold bg-[#F05537] text-white hover:opacity-95 transition">
                        Create event
                    </a>
                @endauth

                @auth
                    {{-- Optional: My Tickets as subtle link --}}
                    <a href="{{ route('my.tickets') }}"
                       class="text-sm font-medium {{ request()->routeIs('my.tickets*') ? 'text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">
                        My Tickets
                    </a>

                    {{-- Dashboard as subtle link (or remove if you want it only inside dropdown) --}}
                    <a href="{{ route('dashboard') }}"
                       class="text-sm font-medium {{ request()->routeIs('dashboard') ? 'text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">
                        Dashboard
                    </a>

                    {{-- User dropdown --}}
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-2 px-3 py-2 rounded-full hover:bg-gray-50 transition">
                                <span class="text-sm font-medium text-gray-800 max-w-[140px] truncate">
                                    {{ Auth::user()->name }}
                                </span>
                                <svg class="h-4 w-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            @if(Auth::user()?->is_admin)
                                <x-dropdown-link :href="route('admin.dashboard')">Admin dashboard</x-dropdown-link>
                                <div class="my-1 border-t border-gray-100"></div>
                            @endif

                            <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>
                            <x-dropdown-link :href="route('digital-pass.setup')">Digital Pass</x-dropdown-link>
                            <x-dropdown-link :href="route('profile.payouts')">Payout methods</x-dropdown-link>

                            @if(Auth::user()->is_admin || !Auth::user()->organizer)
                                <x-dropdown-link :href="route('organizers.create')">Create Organizer</x-dropdown-link>
                            @endif

                            @if(Auth::user()->organizer)
                                <x-dropdown-link :href="route('organizers.edit', Auth::user()->organizer->slug)">Edit Organizer</x-dropdown-link>
                            @endif

                            <div class="my-1 border-t border-gray-100"></div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                    Log Out
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-gray-900 px-2 py-2">
                        Log in
                    </a>
                    <a href="{{ route('register') }}"
                       class="text-sm font-semibold text-gray-900 px-3 py-2 rounded-full hover:bg-gray-50 transition">
                        Sign up
                    </a>
                @endauth
            </div>

            {{-- Mobile button --}}
            <div class="lg:hidden flex items-center gap-2">
                <a href="{{ route('events.create') }}"
                   class="inline-flex items-center rounded-full px-3 py-2 text-sm font-semibold bg-[#F05537] text-white hover:opacity-95 transition">
                    Create
                </a>

                <button
                    @click="open = !open"
                    class="p-2 rounded-md text-gray-700 hover:bg-gray-100 transition"
                    aria-label="Open menu"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile drawer --}}
    <div x-cloak x-show="open" class="lg:hidden">
        <div class="fixed inset-0 bg-black/40 z-[90]" @click="open=false"></div>

        <aside
            x-show="open"
            x-transition:enter="transform transition ease-in-out duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in-out duration-300"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="fixed right-0 top-0 bottom-0 z-[100] w-80 max-w-[90%] bg-white shadow-xl border-s overflow-y-auto"
            role="dialog" aria-modal="true" aria-label="Mobile menu"
        >
            <div class="h-16 px-4 flex items-center justify-between border-b sticky top-0 bg-white">
                <a href="{{ route('homepage') }}" class="flex items-center">
                    <x-application-logo class="block h-7 w-auto fill-current text-gray-900" />
                </a>
                <button @click="open=false" class="p-2 rounded-md text-gray-700 hover:bg-gray-100 transition">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="px-4 py-4 space-y-1">
                <x-responsive-nav-link :href="route('events.find')" :active="request()->routeIs('events.find')">Find Events</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('how')" :active="request()->routeIs('how')">How it works</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('pricing')" :active="request()->routeIs('pricing')">Pricing</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('about')" :active="request()->routeIs('about')">About</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('contact')" :active="request()->routeIs('contact')">Contact</x-responsive-nav-link>

                <div class="pt-3 mt-3 border-t"></div>

                @auth
                    <x-responsive-nav-link :href="route('my.tickets')" :active="request()->routeIs('my.tickets*')">My Tickets</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('events.create')" :active="request()->routeIs('events.create')">Create event</x-responsive-nav-link>

                    <div class="pt-3 mt-3 border-t"></div>

                    <div class="px-2">
                        <div class="text-sm font-semibold text-gray-900">{{ Auth::user()->name }}</div>
                        <div class="text-xs text-gray-500">{{ Auth::user()->email }}</div>
                    </div>

                    <div class="mt-2 space-y-1">
                        @if(Auth::user()?->is_admin)
                            <x-responsive-nav-link :href="route('admin.dashboard')">Admin dashboard</x-responsive-nav-link>
                        @endif

                        <x-responsive-nav-link :href="route('profile.edit')">Profile</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('digital-pass.setup')">Digital Pass</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('profile.payouts')">Payout methods</x-responsive-nav-link>

                        @if(Auth::user()->is_admin || !Auth::user()->organizer)
                            <x-responsive-nav-link :href="route('organizers.create')">Create Organizer</x-responsive-nav-link>
                        @endif

                        @if(Auth::user()->organizer)
                            <x-responsive-nav-link :href="route('organizers.edit', Auth::user()->organizer->slug)">Edit Organizer</x-responsive-nav-link>
                        @endif

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                Log Out
                            </x-responsive-nav-link>
                        </form>
                    </div>
                @else
                    <x-responsive-nav-link :href="route('login')">Log in</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('register')">Sign up</x-responsive-nav-link>
                @endauth
            </div>
        </aside>
    </div>
</nav>
