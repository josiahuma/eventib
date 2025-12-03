{{-- resources/views/tickets/face-checkin.blade.php --}}
<x-app-layout>
    @php($mode = $mode ?? 'off')

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Face ID check-in — {{ $event->name }}
            </h2>
            <a href="{{ route('events.checkin', $event) }}"
               class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                ← Back to check-in hub
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-900">Face ID check-in (coming soon)</h3>

            <p class="text-sm text-gray-600">
                We’re working on secure Face ID support so you can check in attendees
                using their Digital Pass photo, without scanning QR codes.
            </p>

            @if($mode === 'off')
                <p class="mt-2 text-xs text-amber-600">
                    Digital Pass is currently <span class="font-semibold">off</span> for this event.
                    You can enable it on the event settings page to prepare for future Face ID support.
                </p>
            @endif

            <div class="mt-4 rounded-xl bg-slate-50 border border-dashed border-slate-200 p-4 text-sm text-slate-600">
                <p class="font-medium text-slate-800 mb-1">What will this page do?</p>
                <ul class="list-disc ml-5 space-y-1">
                    <li>Show the attendee’s face snapshot from their Digital Pass.</li>
                    <li>Let you quickly confirm identity visually at the door.</li>
                    <li>Optionally pair with automated face-matching in a later version.</li>
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
