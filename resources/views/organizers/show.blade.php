<x-app-layout>
    <div class="max-w-5xl mx-auto py-10 px-4">

        @php
            $bgColors = [
                ['bg-indigo-100', 'text-indigo-700'],
                ['bg-pink-100', 'text-pink-700'],
                ['bg-green-100', 'text-green-700'],
                ['bg-yellow-100', 'text-yellow-700'],
                ['bg-purple-100', 'text-purple-700'],
                ['bg-red-100', 'text-red-700'],
                ['bg-blue-100', 'text-blue-700'],
                ['bg-teal-100', 'text-teal-700'],
            ];

            // Pick based on organizer id (deterministic)
            $colorSet = $bgColors[$organizer->id % count($bgColors)];
        @endphp

        {{-- Organizer Profile Card --}}
        <div class="bg-white rounded-xl shadow-xl p-10 text-center relative">

            {{-- Avatar --}}
            <div class="flex justify-center">
                <x-avatar :model="$organizer" size="w-24 h-24 border-4 border-white shadow -mt-16" />
            </div>


            {{-- Name --}}
            <h1 class="text-3xl font-extrabold mt-4">{{ $organizer->name }}</h1>

            {{-- Buttons --}}
            <div class="flex justify-center items-center gap-6 mt-4">
                @auth
                    @if (auth()->id() === $organizer->user_id)
                        <a href="{{ route('organizers.edit', $organizer) }}"
                           class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md font-medium">
                            Edit
                        </a>
                    @else
                        @php
                            $isFollowing = $organizer->followers->contains(auth()->user());
                        @endphp
                        <form method="POST"
                              action="{{ $isFollowing
                                  ? route('organizers.unfollow', $organizer)
                                  : route('organizers.follow', $organizer) }}">
                            @csrf
                            <button type="submit"
                                    class="{{ $isFollowing ? 'bg-gray-500 hover:bg-gray-600' : 'bg-blue-600 hover:bg-blue-700' }} text-white px-4 py-2 rounded-md font-semibold shadow">
                                {{ $isFollowing ? 'Unfollow' : 'Follow' }}
                            </button>
                        </form>
                    @endif
                @else
                    <form method="POST" action="{{ route('store.redirect') }}">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-semibold shadow">
                            Follow
                        </button>
                    </form>
                @endauth

                <button onclick="document.getElementById('contactModal').classList.remove('hidden')"
                        class="text-blue-600 font-medium hover:underline">
                    Contact
                </button>
            </div>

            {{-- Stats --}}
            <div class="flex justify-center mt-6 text-center gap-12 text-lg font-semibold text-gray-800">
                <div>
                    {{ $organizer->followers()->count() }}
                    <div class="text-sm font-normal text-gray-500">Followers</div>
                </div>
                <div class="border-l border-gray-300 h-6"></div>
                <div>
                    {{ $organizer->events()->count() }}
                    <div class="text-sm font-normal text-gray-500">Total events</div>
                </div>
            </div>

            {{-- Bio --}}
            <div class="mt-6 text-gray-600 leading-relaxed text-center">
                {{ $organizer->bio ?? 'No bio provided yet.' }}
            </div>
        </div>


        {{-- Events Section --}}
        <div class="mt-12">
            <h2 class="text-xl font-semibold mb-4">Events</h2>

            {{-- Tabs: Upcoming / Past --}}
            @php
                $upcomingEvents = $organizer->events->filter(function ($event) {
                    return $event->sessions->min('session_date') > now();
                });

                $pastEvents = $organizer->events->filter(function ($event) {
                    return $event->sessions->max('session_date') <= now();
                });

                $activeTab = request()->get('tab', 'upcoming'); // default to upcoming
            @endphp

            {{-- Tabs --}}
            <div class="flex items-center gap-4 mb-6">
                <a href="{{ route('organizers.show', ['organizer' => $organizer->slug, 'tab' => 'upcoming']) }}"
                class="px-4 py-2 rounded-full border font-medium {{ $activeTab === 'upcoming' ? 'text-blue-600 border-blue-600 bg-blue-50' : 'text-gray-600 border-gray-300 hover:bg-gray-100' }}">
                    Upcoming ({{ $upcomingEvents->count() }})
                </a>
                <a href="{{ route('organizers.show', ['organizer' => $organizer->slug, 'tab' => 'past']) }}"
                class="px-4 py-2 rounded-full border font-medium {{ $activeTab === 'past' ? 'text-blue-600 border-blue-600 bg-blue-50' : 'text-gray-600 border-gray-300 hover:bg-gray-100' }}">
                    Past ({{ $pastEvents->count() }})
                </a>
            </div>

            {{-- Events Grid --}}
            @if ($activeTab === 'past')
                @if ($pastEvents->isEmpty())
                    <p class="text-gray-500 text-center">No past events.</p>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($pastEvents as $event)
                            <a href="{{ route('events.show', $event->public_id) }}"
                            class="bg-white rounded-lg overflow-hidden shadow hover:shadow-lg transition">
                                <img src="{{ $event->banner_url ? asset('storage/' . $event->banner_url) : asset('default-banner.jpg') }}"
                                    alt="{{ $event->name }}"
                                    class="w-full h-40 object-cover">
                                <div class="p-4">
                                    <h3 class="text-lg font-bold">{{ $event->name }}</h3>
                                    @if ($event->sessions->min('session_date'))
                                        <p class="text-sm text-gray-500 mt-1">
                                            {{ \Carbon\Carbon::parse($event->sessions->min('session_date'))->format('M j, Y') }}
                                        </p>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            @else
                @if ($upcomingEvents->isEmpty())
                    <p class="text-gray-500 text-center">No upcoming events.</p>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($upcomingEvents as $event)
                            <a href="{{ route('events.show', $event->public_id) }}"
                            class="bg-white rounded-lg overflow-hidden shadow hover:shadow-lg transition">
                                <img src="{{ $event->banner_url ? asset('storage/' . $event->banner_url) : asset('default-banner.jpg') }}"
                                    alt="{{ $event->name }}"
                                    class="w-full h-40 object-cover">
                                <div class="p-4">
                                    <h3 class="text-lg font-bold">{{ $event->name }}</h3>
                                    @if ($event->sessions->min('session_date'))
                                        <p class="text-sm text-gray-500 mt-1">
                                            {{ \Carbon\Carbon::parse($event->sessions->min('session_date'))->format('M j, Y') }}
                                        </p>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
    <!-- Contact Organizer Modal -->
    <div id="contactModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg shadow-xl relative">
            <button class="absolute top-2 right-2 text-gray-500 hover:text-gray-700"
                    onclick="document.getElementById('contactModal').classList.add('hidden')">&times;</button>

            <h2 class="text-xl font-bold mb-4">Contact {{ $organizer->name }}</h2>

            <form method="POST" action="{{ route('organizers.contact', $organizer) }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium">Your Name</label>
                    <input type="text" name="name" required class="w-full border rounded px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium">Your Email</label>
                    <input type="email" name="email" required class="w-full border rounded px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium">Message</label>
                    <textarea name="message" rows="4" required class="w-full border rounded px-3 py-2"></textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>

</x-app-layout>
