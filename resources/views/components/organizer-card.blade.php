@props(['organizer'])

@if ($organizer)
<div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
            <span class="text-indigo-700 text-sm font-semibold">
                <img src="{{ $organizer->avatar_url ? asset('storage/' . $organizer->avatar_url) : asset('default-avatar.png') }}"
                 alt="{{ $organizer->name }}">
            </span>
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
