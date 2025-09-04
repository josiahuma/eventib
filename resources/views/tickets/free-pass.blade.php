{{-- resources/views/tickets/free-pass.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Your pass — {{ $event->name }}
            </h2>
            <a href="{{ route('my.tickets') }}" class="text-sm text-gray-600 hover:text-gray-800 underline">Back</a>
        </div>
    </x-slot>

    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 text-center">
            <div class="text-sm text-gray-600 mb-2">Party size</div>
            <div class="text-2xl font-semibold text-gray-900 mb-4">{{ $party }}</div>

            <div class="p-4 bg-white rounded-xl border inline-block">
                @if($qrSvg)
                    {!! $qrSvg !!}
                @else
                    <div class="text-xs text-gray-500">QR library not installed — show payload instead</div>
                    <code class="text-xs">{{ $payload }}</code>
                @endif
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
