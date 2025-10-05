<nav x-data="{ open: false }" class="fixed top-0 inset-x-0 z-[100] bg-white border-b border-gray-100 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('homepage') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Left links (visible to all) -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('how')" :active="request()->routeIs('how')">How it works</x-nav-link>
                    <x-nav-link :href="route('pricing')" :active="request()->routeIs('pricing')">Pricing</x-nav-link>
                    <x-nav-link :href="route('about')" :active="request()->routeIs('about')">About</x-nav-link>
                    @auth
                        <x-nav-link :href="route('my.tickets')" :active="request()->routeIs('my.tickets*')">My Tickets</x-nav-link>
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Manage Events</x-nav-link>
                        <x-nav-link :href="route('events.create')" :active="request()->routeIs('events.create')">Create Events</x-nav-link>
                    @endauth
                    <x-nav-link :href="route('contact')" :active="request()->routeIs('contact')">Contact</x-nav-link>
                </div>
            </div>

            <!-- Right side (desktop) -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                @auth
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition">
                                <div>{{ Auth::user()->name }}</div>
                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </div>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            @if(Auth::user()?->is_admin)
                                <x-dropdown-link :href="route('admin.dashboard')">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.25a.75.75 0 01.35.086l7.5 3.75a.75.75 0 01.4.664v5.25c0 4.424-2.942 8.458-7.35 9.72a.75.75 0 01-.2 0C7.292 20.458 4.35 16.424 4.35 12V6.75a.75.75 0 01.4-.664l7.5-3.75A.75.75 0 0112 2.25z"/></svg>
                                        Admin dashboard
                                    </span>
                                </x-dropdown-link>
                                <div class="my-1 border-t border-gray-100"></div>
                            @endif
                            <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>
                            <x-dropdown-link :href="route('my.tickets')">My Tickets</x-dropdown-link>
                            <x-dropdown-link :href="route('profile.payouts')">Add Payout methods</x-dropdown-link>
                            @if(Auth::user()->is_admin || !Auth::user()->organizer)
                                <x-dropdown-link :href="route('organizers.create')">
                                    Create Organizer
                                </x-dropdown-link>
                            @endif
                            @if(Auth::user()->organizer)
                                <x-dropdown-link :href="route('organizers.edit', Auth::user()->organizer->slug)">
                                    Edit Organizer
                                </x-dropdown-link>
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
                    <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-800 px-4">Login</a>
                    <a href="{{ route('register') }}" class="text-gray-600 hover:text-gray-800">Register</a>
                @endauth
            </div>

            <!-- Mobile hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = true" class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none">
                    <span class="sr-only">Open menu</span>
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile off-canvas --}}
    <div x-cloak x-show="open" class="sm:hidden">
        {{-- Backdrop --}}
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/40 z-[90]" @click="open=false" aria-hidden="true"></div>

        {{-- Drawer --}}
        <aside x-show="open"
               x-transition:enter="transform transition ease-in-out duration-300"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transform transition ease-in-out duration-300"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               class="fixed right-0 top-0 bottom-0 z-[100] w-80 max-w-[90%] bg-white shadow-xl border-s overflow-y-auto"
               role="dialog" aria-modal="true" aria-label="Mobile menu">

            <div class="h-16 px-4 flex items-center justify-between border-b bg-white sticky top-0">
                <a href="{{ route('homepage') }}" class="flex items-center gap-2">
                    <x-application-logo class="block h-7 w-auto fill-current text-gray-800" />
                </a>
                <button @click="open=false" class="p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="px-4 py-3">
                <div class="space-y-1">
                    <x-responsive-nav-link :href="route('how')" :active="request()->routeIs('how')">How it works</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('pricing')" :active="request()->routeIs('pricing')">Pricing</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('about')" :active="request()->routeIs('about')">About</x-responsive-nav-link>
                    @auth
                        <x-responsive-nav-link :href="route('my.tickets')" :active="request()->routeIs('my.tickets*')">My Tickets</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Manage Events</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('events.create')" :active="request()->routeIs('events.create')">Create Events</x-responsive-nav-link>
                    @endauth
                    <x-responsive-nav-link :href="route('contact')" :active="request()->routeIs('contact')">Contact</x-responsive-nav-link>
                </div>

                @auth
                    <div class="mt-4 pt-4 border-t">
                        <div class="px-1">
                            <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                            <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                        </div>
                        <div class="mt-3 space-y-1">
                            @if(Auth::user()?->is_admin)
                                <x-responsive-nav-link :href="route('admin.dashboard')">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.25a.75.75 0 01.35.086l7.5 3.75a.75.75 0 01.4.664v5.25c0 4.424-2.942 8.458-7.35 9.72a.75.75 0 01-.2 0C7.292 20.458 4.35 16.424 4.35 12V6.75a.75.75 0 01.4-.664l7.5-3.75A.75.75 0 0112 2.25z"/></svg>
                                        Admin dashboard
                                    </span>
                                </x-responsive-nav-link>
                            @endif
                            <x-responsive-nav-link :href="route('profile.edit')">Profile</x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('profile.payouts')">Add Payout methods</x-responsive-nav-link>
                            @if(Auth::user()->is_admin || !Auth::user()->organizer)
                                <x-responsive-nav-link :href="route('organizers.create')">Create Organizer</x-responsive-nav-link>
                            @endif
                            @if(Auth::user()->organizer)
                                <x-responsive-nav-link :href="route('organizers.edit', Auth::user()->organizer->slug)">Edit Organizer</x-responsive-nav-link>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log Out</x-responsive-nav-link>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="mt-4 pt-4 border-t space-y-1">
                        <x-responsive-nav-link :href="route('login')">Login</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('register')">Register</x-responsive-nav-link>
                    </div>
                @endauth
            </div>
        </aside>
    </div>
</nav>
