{{-- resources/views/tickets/list.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            My Tickets — {{ $event->name }}
        </h2>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 grid gap-4">
        @foreach($tickets as $t)
            <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-500">Ticket #{{ $t->serial }}</div>
                        <div class="text-xs text-gray-500">
                            {{ $t->checked_in_at ? 'Checked in' : 'Not checked in' }}
                        </div>
                    </div>
                    <a class="text-sm text-indigo-600 hover:text-indigo-700 underline"
                       href="{{ route('tickets.show', [$event, $registration, $t]) }}">
                        Open
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
{{-- Note: each ticket links to its own page with a QR code, see tickets/show.blade.php --}}