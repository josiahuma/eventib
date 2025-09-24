@props(['organizer'])

@if ($organizer)
<div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center overflow-hidden">
            @if ($organizer->avatar_url)
                <img src="{{ asset('storage/' . $organizer->avatar_url) }}"
                     alt="{{ $organizer->name }}"
                     class="h-full w-full object-cover">
            @else
                <span class="text-indigo-700 text-sm font-semibold">
                    {{ strtoupper(substr($organizer->name, 0, 1)) }}
                </span>
            @endif
        </div>
        <div>
            <div class="text-sm text-gray-500">Organizer</div>
            <a href="{{ route('organizers.show', $organizer->slug) }}"
               class="text-base font-medium text-indigo-600 hover:underline">
                {{ $organizer->name }}
            </a>
        </div>
    </div>
</div>
@endif
