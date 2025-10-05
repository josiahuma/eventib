<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
            Past Events
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if ($past->count())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach ($past as $event)
                    @include('events.partials._event_card', ['event' => $event])
                @endforeach
            </div>

            <div class="mt-6">
                {{ $past->links('pagination::tailwind') }}
            </div>
        @else
            <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-12 text-center">
                <h3 class="text-lg font-semibold text-gray-800">No past events found</h3>
                <p class="mt-1 text-sm text-gray-500">Try again later or explore upcoming events.</p>
            </div>
        @endif
    </div>
</x-app-layout>
