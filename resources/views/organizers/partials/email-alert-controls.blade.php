<div class="form-card my-6">
    <h2 class="form-section-title">Don’t miss their next event.</h2>
    <p class="text-sm text-gray-600 mb-4">Choose to receive new-event emails from {{ $organizer->name }}. Following alone does not turn on emails.</p>
    @if(session('success'))<p class="text-sm text-emerald-700 mb-4" role="status">{{ session('success') }}</p>@endif
    @auth
        @php $alertsOn = \Illuminate\Support\Facades\DB::table('organizer_alert_subscriptions')->where('user_id', auth()->id())->where('organizer_id', $organizer->id)->where('enabled', true)->exists(); @endphp
        <form method="POST" action="{{ route('organizer-alerts.update', $organizer) }}">@csrf<input type="hidden" name="enabled" value="{{ $alertsOn ? 0 : 1 }}"><button class="{{ $alertsOn ? 'form-secondary-btn' : 'form-primary-btn' }}">{{ $alertsOn ? 'Email alerts on · turn off' : 'Email me about new events' }}</button></form>
        <a href="{{ route('organizer-alerts.index') }}" class="inline-block mt-4 text-sm underline">Manage all my alerts</a>
    @else <a href="{{ route('login') }}" class="form-primary-btn">Sign in to choose alerts</a>@endauth
</div>
