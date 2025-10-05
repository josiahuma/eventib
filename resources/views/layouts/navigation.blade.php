{{-- resources/views/layouts/navigation.blade.php --}}
<nav x-data="{ open: false }" class="fixed top-0 inset-x-0 z-[100] bg-white border-b border-gray-100 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            {{-- Left side --}}
            <div class="flex items-center">
                <a href="{{ route('homepage') }}">
                    <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                </a>
                <div class="hidden sm:flex space-x-8 sm:ml-10">
                    <x-nav-link :href="route('homepage')" :active="request()->routeIs('homepage')">Find Events</x-nav-link>
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

            {{-- Right side (desktop) --}}
            <div class="hidden sm:flex items-center space-x-4">
                @auth
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 focus:outline-none">
                                <div>{{ Auth::user()->name }}</div>
                                <svg class="ms-1 h-4 w-4 fill-current" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            @if(Auth::user()?->is_admin)
                                <x-dropdown-link :href="route('admin.dashboard')">Admin Dashboard</x-dropdown-link>
                                <div class="my-1 border-t border-gray-100"></div>
                            @endif
                            <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>
                            <x-dropdown-link :href="route('profile.payouts')">Add Payout Methods</x-dropdown-link>
                            @if(Auth::user()->is_admin || !Auth::user()->organizer)
                                <x-dropdown-link :href="route('organizers.create')">Create Organizer</x-dropdown-link>
                            @endif
                            @if(Auth::user()->organizer)
                                <x-dropdown-link :href="route('organizers.edit', Auth::user()->organizer->slug)">Edit Organizer</x-dropdown-link>
                            @endif
                            <div class="my-1 border-t border-gray-100"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
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

            {{-- Hamburger (mobile) --}}
            <div class="-mr-2 flex items-center sm:hidden">
                <button 
                    @click="open = !open"
                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Offcanvas Menu (outside flow, no height issues) --}}
    <template x-if="open">
        <div>
            <div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/40 z-[90]" @click="open=false"></div>
            <aside
                x-show="open"
                x-transition:enter="transform transition ease-in-out duration-300"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in-out duration-300"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="fixed right-0 top-0 bottom-0 z-[100] w-80 max-w-[90%] bg-white text-gray-900 shadow-xl border-s overflow-y-auto"
                role="dialog" aria-modal="true"
            >
                <div class="h-16 px-4 flex items-center justify-between border-b sticky top-0 bg-white z-[110]">
                    <a href="{{ route('homepage') }}">
                        <x-application-logo class="block h-7 w-auto fill-current text-gray-800" />
                    </a>
                    <button @click="open=false" class="p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-4 py-4 space-y-1">
                    <x-responsive-nav-link :href="route('homepage')" :active="request()->routeIs('homepage')">Find Events</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('how')" :active="request()->routeIs('how')">How it works</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('pricing')" :active="request()->routeIs('pricing')">Pricing</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('about')" :active="request()->routeIs('about')">About</x-responsive-nav-link>
                    @auth
                        <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Manage Events</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('events.create')" :active="request()->routeIs('events.create')">Create Events</x-responsive-nav-link>
                    @endauth
                    <x-responsive-nav-link :href="route('contact')" :active="request()->routeIs('contact')">Contact</x-responsive-nav-link>
                </div>

                @auth
                    <div class="mt-4 pt-4 border-t">
                        <div class="px-4">
                            <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                            <div class="text-sm text-gray-500">{{ Auth::user()->email }}</div>
                        </div>
                        <div class="mt-3 space-y-1 px-4">
                            <x-responsive-nav-link :href="route('profile.edit')">Profile</x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('profile.payouts')">Add Payout methods</x-responsive-nav-link>
                            @if(Auth::user()->is_admin)
                                <x-responsive-nav-link :href="route('admin.dashboard')">Admin dashboard</x-responsive-nav-link>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log Out</x-responsive-nav-link>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="mt-4 pt-4 border-t px-4 space-y-1">
                        <x-responsive-nav-link :href="route('login')">Login</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('register')">Register</x-responsive-nav-link>
                    </div>
                @endauth
            </aside>
        </div>
    </template>
</nav>
