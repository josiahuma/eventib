<x-app-layout>
<div class="max-w-7xl mx-auto px-4 py-12">
    <p class="text-sm font-semibold text-orange-600">YOUR NEXT GOOD TIME</p>
    <h1 class="text-4xl font-bold mt-3">My Plans</h1>
    <p class="text-gray-600 mt-3 mb-8">Your shortlist of things to look forward to. Saving an event does not reserve a ticket.</p>
    @if(session('discovery_status'))<p role="status" class="mb-4 text-emerald-700">{{ session('discovery_status') }}</p>@endif
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($events as $event)
            <div>
                @include('events.partials._event_card')
                <div class="flex items-center justify-between mt-3">
                    <a href="{{ route('discovery.share', $event) }}" class="text-sm font-semibold text-orange-700">Invite your people ↗</a>
                    <form method="POST" action="{{ route('discovery.save', $event) }}">@csrf<input type="hidden" name="saved" value="0"><button class="text-sm underline">Remove</button></form>
                </div>
            </div>
        @empty
            <div class="rounded-2xl bg-white p-8 sm:col-span-2">
                <h2 class="text-2xl font-bold">A good plan starts with a little curiosity.</h2>
                <p class="my-4 text-gray-600">Open an event and tap Save this plan. It will be waiting here when you return.</p>
                <a href="{{ route('events.find') }}" class="font-semibold text-orange-700">Discover events →</a>
            </div>
        @endforelse
    </div>
    <div class="mt-8">{{ $events->links() }}</div>
</div>
</x-app-layout>
