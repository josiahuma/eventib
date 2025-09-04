{{-- resources/views/tickets/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My tickets</h2>
            <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-800 underline">Back</a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-700 border border-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if ($registrations->isEmpty())
            <div class="bg-white rounded-2xl border border-gray-200 p-8 text-center text-gray-600">
                You don’t have any tickets yet.
            </div>
        @else
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-4 border-b text-sm text-gray-600">
                    {{ $registrations->total() }} {{ Str::plural('registration', $registrations->total()) }}
                </div>

                <div class="divide-y">
                    @foreach ($registrations as $r)
                        @php $event = $r->event; @endphp
                        <div class="p-4 flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-medium text-gray-900 truncate">
                                    {{ $event->name ?? 'Event deleted' }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    Registered: {{ $r->created_at?->format('d M Y, g:ia') }}
                                    · Status: {{ ucfirst($r->status ?? '—') }}
                                </div>
                            </div>

                            <div class="shrink-0">
                                {{-- Go to the list page (generates tickets if missing) --}}
                                <a
                                    href="{{ route('tickets.list', ['event' => $event, 'registration' => $r]) }}"
                                    class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700"
                                >
                                    View tickets
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="p-4">
                    {{ $registrations->links() }}
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
