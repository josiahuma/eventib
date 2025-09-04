{{-- resources/views/tickets/event-index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Tickets — {{ $event->name }}
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('events.tickets.export-pdf', $event) }}"
                   class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
                    Export PDF
                </a>
                <a href="{{ route('events.registrants', $event) }}"
                   class="text-sm text-gray-600 hover:text-gray-800 underline">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white border rounded-2xl shadow-sm overflow-hidden divide-y">
            @forelse($registrations as $r)
                @php
                    $payload = json_encode([
                        'type'=>'ticket',
                        'event'=>$event->public_id ?? $event->id,
                        'registration'=>$r->public_id ?? $r->id,
                    ]);
                @endphp
                <div class="p-4 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-medium text-gray-900 truncate">{{ $r->name ?? 'Guest' }} · {{ $r->email }}</div>
                        <div class="text-xs text-gray-500">#{{ $r->public_id ?? $r->id }}</div>
                    </div>
                    <div class="hidden sm:block">
                        {!! QrCode::size(100)->margin(0)->generate($payload) !!}
                    </div>
                    <div class="shrink-0">
                        <a href="{{ route('tickets.show', $r) }}"
                           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                            Open QR
                        </a>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-600">No tickets yet.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
