{{-- resources/views/tickets/checkin-hub.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Check-in — {{ $event->name }}
            </h2>
            <a href="{{ route('events.edit', $event) }}"
               class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                ← Back to dashboard
            </a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        {{-- Normal check-in --}}
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Normal check-in</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Scan attendee QR codes at the door. Works for both free and paid tickets.
                    </p>
                </div>

                <a href="{{ route('tickets.scan', $event) }}"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                          bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700
                          focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M5 3a2 2 0 0 0-2 2v4h2V5h4V3H5zm10 0v2h4v4h2V5a2 2 0 0 0-2-2h-4zM3 13v4a2 2 0 0 0 2 2h4v-2H5v-4H3zm16 0v4h-4v2h4a2 2 0 0 0 2-2v-4h-2z"/>
                    </svg>
                    Check-in with QR code
                </a>
            </div>
        </div>

        {{-- Digital Pass check-in --}}
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Digital Pass check-in</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        For attendees who opted into Digital Pass, you can match them by their voice
                        (and soon face) instead of scanning a QR code.
                    </p>
                    @if($mode === 'off')
                        <p class="mt-2 text-xs text-amber-600">
                            Digital Pass is currently <span class="font-semibold">off</span> for this event.
                            You can enable it when editing the event settings.
                        </p>
                    @endif
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                {{-- Voice pass --}}
                <a href="{{ route('tickets.digital-checkin', $event) }}"
                   class="inline-flex flex-1 items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                          bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700
                          focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 2a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/>
                        <path d="M5 11a1 1 0 0 1 2 0 5 5 0 0 0 10 0 1 1 0 1 1 2 0 7 7 0 0 1-6 6.93V21h3a1 1 0 1 1 0 2H8a1 1 0 1 1 0-2h3v-3.07A7 7 0 0 1 5 11z"/>
                    </svg>
                    Check-in with Voice Pass
                </a>

                {{-- Face ID (coming soon / stub) --}}
                <a href="{{ route('tickets.face-checkin', $event) }}"
                   class="inline-flex flex-1 items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                          border border-dashed border-slate-300 bg-slate-50 text-slate-700 text-sm font-semibold
                          hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-400">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M5 3a2 2 0 0 0-2 2v3h2V5h3V3H5zm11 0v2h3v3h2V5a2 2 0 0 0-2-2h-3zM3 14v3a2 2 0 0 0 2 2h3v-2H5v-3H3zm16 0v3h-3v2h3a2 2 0 0 0 2-2v-3h-2z"/>
                        <circle cx="12" cy="11" r="3"/>
                        <path d="M8 16a4 4 0 0 0 8 0z"/>
                    </svg>
                    Check-in with Face ID
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[11px]">
                        Coming soon
                    </span>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
