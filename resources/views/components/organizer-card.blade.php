@props(['organizer'])

@if ($organizer)
@php
    $bgColors = [
        ['bg-indigo-100', 'text-indigo-700'],
        ['bg-pink-100', 'text-pink-700'],
        ['bg-green-100', 'text-green-700'],
        ['bg-yellow-100', 'text-yellow-700'],
        ['bg-purple-100', 'text-purple-700'],
        ['bg-red-100', 'text-red-700'],
        ['bg-blue-100', 'text-blue-700'],
        ['bg-teal-100', 'text-teal-700'],
    ];
    $colorSet = $bgColors[$organizer->id % count($bgColors)];
    $isUnknown = strtolower(trim($organizer->name)) === 'unknown';
@endphp

<div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
    <div class="flex items-center gap-3">
        <x-avatar :model="$organizer" size="h-10 w-10" />

        <div>
            <div class="text-sm text-gray-500">Organizer</div>

            @if ($isUnknown)
                <span class="text-base font-medium text-gray-700">
                    {{ $organizer->name }}
                </span>
            @else
                <a href="{{ route('organizers.show', $organizer->slug) }}"
                   class="text-base font-medium text-indigo-600 hover:underline">
                    {{ $organizer->name }}
                </a>
            @endif
        </div>
    </div>
</div>
@endif
