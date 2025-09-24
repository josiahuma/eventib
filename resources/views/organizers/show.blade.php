<x-app-layout>
    <div class="max-w-5xl mx-auto py-10 px-4">

        {{-- Organizer Profile Card --}}
        <div class="bg-white rounded-xl shadow-xl p-10 text-center">
            <div class="w-24 h-24 mx-auto rounded-full border-4 border-white shadow -mt-16 flex items-center justify-center bg-indigo-100 overflow-hidden">
                @if ($organizer->avatar_url)
                    <img src="{{ asset('storage/' . $organizer->avatar_url) }}"
                        alt="{{ $organizer->name }}"
                        class="w-full h-full object-cover">
                @else
                    <span class="text-indigo-700 text-3xl font-bold">
                        {{ strtoupper(substr($organizer->name, 0, 1)) }}
                    </span>
                @endif
            </div>


            <h1 class="text-3xl font-extrabold mt-4">{{ $organizer->name }}</h1>

            <div class="flex justify-center items-center gap-6 mt-4">
                {{-- Follow Button --}}
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

                {{-- Contact Button (optional placeholder) --}}
                <!-- Contact Modal Trigger -->
                <button onclick="document.getElementById('contactModal').classList.remove('hidden')"
                        class="text-blue-600 font-medium hover:underline">
                    Contact
                </button>

            </div>

            {{-- Follower + Event Stats --}}
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
            <div class="flex items-center gap-4 mb-6">
                <a href="#"
                   class="px-4 py-2 rounded-full border text-blue-600 border-blue-600 font-medium hover:bg-blue-50">
                    Upcoming ({{ $organizer->events->where('sessions.*.session_date', '>', now())->count() }})
                </a>
                <a href="#"
                   class="px-4 py-2 rounded-full border text-gray-600 border-gray-300 font-medium hover:bg-gray-100">
                    Past ({{ $organizer->events->where('sessions.*.session_date', '<=', now())->count() }})
                </a>
            </div>

            {{-- Events Grid --}}
            @if ($organizer->events->isEmpty())
                <p class="text-gray-500 text-center">No events available.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($organizer->events as $event)
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
