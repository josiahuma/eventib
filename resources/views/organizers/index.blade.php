<x-app-layout>
<div class="max-w-5xl mx-auto px-4 py-8"><h1 class="text-3xl font-bold mb-6">Your organiser profiles</h1>
<div class="space-y-4">@forelse($organizers as $organizer)<div class="bg-white rounded-xl border p-5 flex justify-between gap-4"><strong>{{ $organizer->name }}</strong><a class="underline" href="{{ route('organizers.edit', $organizer) }}">Edit profile</a></div>@empty<p>No organiser profile yet.</p>@endforelse</div>
<a class="inline-block mt-6 underline" href="{{ route('organizers.create') }}">Create organiser profile</a></div>
</x-app-layout>
