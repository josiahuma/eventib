{{-- resources/views/tickets/show.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Your ticket — {{ $event->name }}
            </h2>
            <a href="{{ route('my.tickets') }}" class="text-sm text-gray-600 hover:text-gray-800 underline">Back</a>
        </div>
    </x-slot>

    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">

            @php
                $count = $siblings->count();
                $pos   = $siblings->search(fn($t) => $t->id === $ticket->id);
            @endphp

            @if($count > 1)
                <div class="mb-4 flex items-center gap-2 text-sm">
                    <span class="text-gray-600">Ticket {{ $pos+1 }} of {{ $count }}</span>
                    @if($pos > 0)
                        <a class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200"
                           href="{{ route('tickets.show', ['event'=>$event,'registration'=>$registration,'ticket'=>$siblings[$pos-1]->id]) }}">
                            ‹ Prev
                        </a>
                    @endif
                    @if($pos < $count-1)
                        <a class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200"
                           href="{{ route('tickets.show', ['event'=>$event,'registration'=>$registration,'ticket'=>$siblings[$pos+1]->id]) }}">
                            Next ›
                        </a>
                    @endif

                    <select class="ml-auto border rounded px-2 py-1"
                            onchange="if(this.value){window.location=this.value}">
                        @foreach($siblings as $i => $s)
                            <option value="{{ route('tickets.show', ['event'=>$event,'registration'=>$registration,'ticket'=>$s->id]) }}"
                                @selected($s->id === $ticket->id)>
                                Ticket #{{ $i+1 }} — {{ $s->serial }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- QR --}}
            <div class="flex flex-col items-center text-center">
                <div class="p-4 bg-white rounded-xl border inline-block">
                    @if($qrSvg)
                        {!! $qrSvg !!}
                    @else
                        <div class="text-xs text-gray-500">QR library not installed — show payload instead</div>
                        <code class="text-xs">{{ $qrPayload }}</code>
                    @endif
                </div>

                <div class="mt-3 text-sm text-gray-600">
                    Serial: <span class="font-medium text-gray-900">{{ $ticket->serial }}</span>
                </div>

                <div class="mt-2 text-xs text-gray-500">
                    Please keep this QR visible on your phone at the entrance.
                </div>

                <div class="mt-4">
                    <a href="{{ route('tickets.ticket.pdf', ['event'=>$event,'registration'=>$registration,'ticket'=>$ticket]) }}"
                       class="inline-flex items-center px-3 py-1.5 rounded-md bg-gray-100 hover:bg-gray-200 text-sm text-gray-800">
                        Download PDF
                    </a>
                </div>
            </div>

            @if($registration->sessions && $registration->sessions->count())
                <div class="mt-6 text-sm text-gray-700">
                    Sessions:
                    <span class="font-medium">
                        {{ $registration->sessions->pluck('session_name')->join(', ') }}
                    </span>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
