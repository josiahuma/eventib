<x-app-layout>
<div class="max-w-4xl mx-auto px-4 py-10">
    <div class="ev-intro"><p class="ev-kicker">YOUR COMMUNITIES</p><h1>Stay in the loop.</h1><p>Choose which organisers can email you when they create a new event. Following and email alerts are separate choices.</p></div>
    @if(session('success'))<p class="mb-6 text-emerald-700" role="status">{{ session('success') }}</p>@endif
    @forelse($subscriptions as $subscription)
    <div class="form-card mb-4 flex flex-wrap items-center justify-between gap-4">
        <div><a href="{{ route('organizers.show', $subscription->slug) }}" class="font-semibold">{{ $subscription->name }}</a><p class="text-sm text-gray-600 mt-2">{{ $subscription->enabled ? 'Email alerts on' : 'Email alerts off' }}</p></div>
        <form method="POST" action="{{ route('organizer-alerts.update', $subscription->slug) }}">@csrf<input type="hidden" name="enabled" value="{{ $subscription->enabled ? 0 : 1 }}"><button class="form-secondary-btn">{{ $subscription->enabled ? 'Turn off emails' : 'Enable emails' }}</button></form>
    </div>
    @empty <div class="form-card"><p>No email alerts chosen yet. Open an organiser’s profile to opt in.</p><a href="{{ route('events.find') }}" class="inline-block mt-4 text-orange-700">Discover events →</a></div>@endforelse
</div>
</x-app-layout>
